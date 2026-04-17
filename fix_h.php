<?php
$c = file_get_contents('Reports/report_utility.php');
$c = str_replace(
'<div class="floor-header">ชั้นที่ <?php echo $floorNum; ?></div>',
'<?php
                            if ($floorNum === \'vacant\') {
                                $headerLabel = \'ห้องพักที่ไม่มีผู้เช่า\';
                            } else {
                                $fn = str_replace(\'floor_\', \'\', (string)$floorNum);
                                $headerLabel = \'ชั้นที่ \' . $fn;
                            }
                        ?>
                        <div class="floor-header"><?php echo htmlspecialchars($headerLabel); ?></div>',
$c
);
file_put_contents('Reports/report_utility.php', $c);
