import requests
import time
import random
import argparse
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed

# --- 1. ใส่ URL ของฟอร์ม (อันเดิมที่คุณมี) ---
url = 'https://docs.google.com/forms/u/1/d/e/1FAIpQLSe9hTz43xF2FTZDR6zCV0h7KEsJuI_CSyFpxAoTWIT2RydEUQ/formResponse' 

# --- 2. ตั้งค่าจำนวนรอบ ---
count = 200

# ย้ายฟังก์ชัน random_score ขึ้นมาด้านบนเพื่อหลีกเลี่ยงการประกาศซ้ำในลูป
def random_score(prefer_high=True):
    choices = ['2','3','4','5']
    weights = [10,20,30,50] if prefer_high else [25,25,25,25]
    return random.choices(choices, weights=weights, k=1)[0]

# กำหนดเทมเพลตสำหรับฟิลด์ entry.* (ค่าบางตัวเป็นคงที่ เช่น ข้อความ)
base_entry_template = {
    'entry.1461301075': 'นักศึกษา มรภ.เพชรบูรณ์',
    'entry.525185231': None,
    'entry.292648619': None,
    'entry.2016062213': None,
    'entry.1078961690': None,
    'entry.1468637668': None,
    'entry.1161428570': None,
    'entry.129262836': None,
    'entry.1965298084': None,
    'entry.1240748544': None,
    'entry.479994960': None,
    'entry.1354824061': None,
    'entry.1181474791': None,
    'entry.497000713': None,
    'entry.1024824387': None,
    'entry.1129375054': None,
}

static_items = {
    'fvv': '1',
    'pageHistory': '0,1,2,3,4,5',
    'fbzx': '-4888613081369147255'
}

# ฟังก์ชันสร้าง payload สำหรับแต่ละคำขอ
def make_payload():
    payload = static_items.copy()
    # เติมค่า entry.* โดยสุ่มคะแนนสำหรับค่า None
    for k, v in base_entry_template.items():
        payload[k] = v if v is not None else random_score()
    return payload

if __name__ == '__main__':
    parser = argparse.ArgumentParser(description='ส่งแบบฟอร์มแบบเร็ว (concurrent)')
    parser.add_argument('--count', type=int, default=count, help='จำนวนคำขอที่จะส่ง')
    parser.add_argument('--concurrency', type=int, default=20, help='จำนวนเธรดพร้อมกัน')
    parser.add_argument('--delay', type=float, default=0.0, help='หน่วงระหว่างคำขอต่อเธรด (วินาที)')
    parser.add_argument('--show-every', type=int, default=10, help='พิมพ์สถานะทุก N รอบ')
    args = parser.parse_args()

    count = args.count
    concurrency = max(1, args.concurrency)
    delay = max(0.0, args.delay)
    show_every = max(1, args.show_every)

    print(f"กำลังเริ่มส่งข้อมูล {count} รอบ (concurrency={concurrency})...")

    session = requests.Session()
    session.headers.update({'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115 Safari/537.36'})

    counter = {'sent': 0, 'success': 0}
    lock = threading.Lock()
    start_time = time.time()

    def worker(i):
        payload = make_payload()
        try:
            r = session.post(url, data=payload, timeout=15)
            success = (r.status_code == 200)
        except Exception as e:
            success = False
            r = None
            err = e

        with lock:
            counter['sent'] += 1
            if success:
                counter['success'] += 1
            current = counter['sent']

        # พิมพ์บางส่วนเพื่อลดการชะลอด้วย I/O
        if (i + 1) % show_every == 0 or not success:
            if success:
                sent_scores = [str(v) for k, v in payload.items() if k.startswith('entry.') and str(v).strip().isdigit()]
                sample = ','.join(sent_scores[:6]) if sent_scores else 'ไม่มีตัวอย่าง'
                print(f"[สำเร็จ] #{i+1} ({current}/{count}) - ตัวอย่าง: {sample}")
            else:
                code = r.status_code if r is not None else getattr(err, 'args', err)
                print(f"[ล้มเหลว] #{i+1} ({current}/{count}) - Error/Status: {code}")

        if delay > 0:
            time.sleep(delay)

    # ส่งแบบ concurrent
    with ThreadPoolExecutor(max_workers=concurrency) as executor:
        futures = [executor.submit(worker, i) for i in range(count)]
        for _ in as_completed(futures):
            pass

    duration = time.time() - start_time
    print(f"เสร็จสิ้น: ส่ง {counter['sent']} คำขอ ({counter['success']} สำเร็จ) ใน {duration:.2f}s — {counter['sent']/duration:.2f} req/s")