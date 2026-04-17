import re

with open('Reports/report_utility.php', 'r') as f:
    text = f.read()

# Fix floor header
import re

def repl_header(m):
    # m.group(1) is the whole class="floor-header" line
    return """                        <?php
                            if ($floorNum === 'vacant') {
                                $headerLabel = 'ห้องพักที่ไม่มีผู้เช่า';
                            } else {
                                $fn = str_replace('floor_', '', $floorNum);
                                $headerLabel = 'ชั้นที่ ' . $fn;
                            }
                        ?>
                        <div class="floor-header"><?php echo htmlspecialchars($headerLabel); ?></div>"""

text = re.sub(r'(\s*<div class="floor-header">ชั้นที่ <\?php echo \$floorNum; \?></div>)', repl_header, text)

# Fix old/new meter and usage display for water
def rep_water(m):
    return """<tr>
                                <td class="room-num-cell" data-label="ห้อง"><?php echo htmlspecialchars((string)($util['room_number'] ?? '-')); ?></td>
                                <td class="status-icon" data-label="สถานะ">
                                    <?php if (!empty($util['tnt_name'])): ?>
                                    <svg viewBox="0 0 24 24" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($util['tnt_name']); ?>"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td class="prev-val" data-label="เลขมิเตอร์เดือนก่อนหน้า"><?php echo str_pad((string)(int)($util['utl_water_start'] ?? 0), 7, '0', STR_PAD_LEFT); ?></td>
                                <td data-label="เลขมิเตอร์เดือนล่าสุด">
                                    <?php if (!$util['is_occupied']): ?>
                                        <span class="curr-val" style="color:#aaa;">-</span>
                                    <?php elseif (!$util['has_reading']): ?>
                                        <span class="badge badge-warning" style="background:#fed7aa; color:#9a3412; padding:0.25rem 0.5rem; border-radius:0.25rem; font-size:0.8rem;">ยังไม่ได้จด</span>
                                    <?php else: ?>
                                        <span class="curr-val"><?php echo str_pad((string)(int)($util['utl_water_end'] ?? 0), 7, '0', STR_PAD_LEFT); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="usage-cell" data-label="หน่วยที่ใช้">
                                    <?php if (!$util['is_occupied']): ?>
                                        -
                                    <?php elseif (!$util['has_reading']): ?>
                                        -
                                    <?php else: ?>
                                        <?php echo number_format($waterUsage); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>"""

text = re.sub(r'<tr>\s*<td class="room-num-cell" data-label="ห้อง">.*?<td class="usage-cell" data-label="หน่วยที่ใช้"><\?php echo number_format\(\$waterUsage\); \?></td>\s*</tr>', rep_water, text, flags=re.DOTALL)

# Fix old/new meter and usage display for electric
def rep_elec(m):
    return """<tr>
                                <td class="room-num-cell" data-label="ห้อง"><?php echo htmlspecialchars((string)($util['room_number'] ?? '-')); ?></td>
                                <td class="status-icon" data-label="สถานะ">
                                    <?php if (!empty($util['tnt_name'])): ?>
                                    <svg viewBox="0 0 24 24" data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($util['tnt_name']); ?>"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td class="prev-val" data-label="เลขมิเตอร์เดือนก่อนหน้า"><?php echo str_pad((string)(int)($util['utl_elec_start'] ?? 0), 5, '0', STR_PAD_LEFT); ?></td>
                                <td data-label="เลขมิเตอร์เดือนล่าสุด">
                                    <?php if (!$util['is_occupied']): ?>
                                        <span class="curr-val elec-val" style="color:#aaa;">-</span>
                                    <?php elseif (!$util['has_reading']): ?>
                                        <span class="badge badge-warning" style="background:#fed7aa; color:#9a3412; padding:0.25rem 0.5rem; border-radius:0.25rem; font-size:0.8rem;">ยังไม่ได้จด</span>
                                    <?php else: ?>
                                        <span class="curr-val elec-val"><?php echo str_pad((string)(int)($util['utl_elec_end'] ?? 0), 5, '0', STR_PAD_LEFT); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="usage-cell elec-usage" data-label="หน่วยที่ใช้">
                                    <?php if (!$util['is_occupied']): ?>
                                        -
                                    <?php elseif (!$util['has_reading']): ?>
                                        -
                                    <?php else: ?>
                                        <?php echo number_format($elecUsage); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>"""

text = re.sub(r'<tr>\s*<td class="room-num-cell" data-label="ห้อง">.*?<td class="usage-cell elec-usage" data-label="หน่วยที่ใช้"><\?php echo number_format\(\$elecUsage\); \?></td>\s*</tr>', rep_elec, text, count=1, flags=re.DOTALL)

with open('Reports/report_utility.php', 'w') as f:
    f.write(text)
print('Done frontend report table')
