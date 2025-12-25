<?php
// แปลงข้อมูลที่อยู่จาก raw database ให้เป็นโครงสร้างที่ใช้งานง่าย

$rawData = json_decode(file_get_contents('thai_address_full.json'), true);

$provinces = [];

foreach ($rawData as $item) {
    $provinceName = $item['province'];
    $amphoe = $item['amphoe'];
    $district = $item['district'];
    $zipcode = $item['zipcode'];
    
    // สร้างจังหวัดถ้ายังไม่มี
    if (!isset($provinces[$provinceName])) {
        $provinces[$provinceName] = [
            'name_th' => $provinceName,
            'amphoes' => []
        ];
    }
    
    // สร้างอำเภอถ้ายังไม่มี
    if (!isset($provinces[$provinceName]['amphoes'][$amphoe])) {
        $provinces[$provinceName]['amphoes'][$amphoe] = [
            'name_th' => $amphoe,
            'districts' => []
        ];
    }
    
    // เพิ่มตำบล
    $provinces[$provinceName]['amphoes'][$amphoe]['districts'][] = [
        'name_th' => $district,
        'zipcode' => (string)$zipcode
    ];
}

// แปลงเป็น array และเรียงลำดับ
$result = ['provinces' => []];
$id = 1;

foreach ($provinces as $provinceName => $provinceData) {
    $amphoes = [];
    $amphoeId = 1;
    
    foreach ($provinceData['amphoes'] as $amphoeName => $amphoeData) {
        $districts = [];
        $districtId = 1;
        
        foreach ($amphoeData['districts'] as $districtData) {
            $districts[] = [
                'id' => $id * 10000 + $amphoeId * 100 + $districtId,
                'name_th' => $districtData['name_th'],
                'zipcode' => $districtData['zipcode']
            ];
            $districtId++;
        }
        
        $amphoes[] = [
            'id' => $id * 100 + $amphoeId,
            'name_th' => $amphoeName,
            'subdistricts' => $districts
        ];
        $amphoeId++;
    }
    
    $result['provinces'][] = [
        'id' => $id,
        'name_th' => $provinceName,
        'districts' => $amphoes
    ];
    $id++;
}

// บันทึกไฟล์
file_put_contents('thai_provinces.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "✓ สร้างไฟล์ thai_provinces.json สำเร็จ\n";
echo "จำนวนจังหวัด: " . count($result['provinces']) . "\n";

// แสดงตัวอย่าง
$sampleProvince = $result['provinces'][0];
echo "\nตัวอย่างจังหวัด: " . $sampleProvince['name_th'] . "\n";
echo "จำนวนอำเภอ: " . count($sampleProvince['districts']) . "\n";
echo "อำเภอแรก: " . $sampleProvince['districts'][0]['name_th'] . "\n";
echo "จำนวนตำบล: " . count($sampleProvince['districts'][0]['subdistricts']) . "\n";
?>
