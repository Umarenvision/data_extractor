<?php
$settings = array(
    'upload_max_filesize',
    'post_max_size',
    'memory_limit',
    'max_execution_time',
    'max_input_time'
);

echo "<pre>\nCurrent PHP Settings:\n\n";
foreach ($settings as $setting) {
    echo $setting . ": " . ini_get($setting) . "\n";
}
echo "\nLoaded Configuration Files:\n";
echo php_ini_loaded_file() . "\n";
echo "\nAdditional .ini files loaded:\n";
print_r(php_ini_scanned_files());
echo "</pre>";
?>
