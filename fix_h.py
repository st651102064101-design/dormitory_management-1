with open('Reports/report_utility.php', 'r') as f:
    text = f.read()

old = '<div class="floor-header">ชั้นที่ <?php echo $floorNum; ?></div>'
new = '''<?php
                            if ($floorNum === 'vacant') {
                                $headerLabel = 'ห้องพักที่ไม่มีผู้เช่า';
                            } else {
                                $fn = str_replace('floor_', '', (string)$floorNum);
                                $headerLabel = 'ชั้นที่ ' . $fn;
                            }
                        ?>
                        <div class="floor-header"><?php echo htmlspecialchars($headerLabel); ?></div>'''

text = text.replace(old, new)

with open('Reports/report_utility.php', 'w') as f:
    f.write(text)
