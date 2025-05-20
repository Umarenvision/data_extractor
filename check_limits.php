<?php
header('Content-Type: text/plain');
echo "Current PHP Settings:\n\n";
echo "upload_max_filesize = " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size = " . ini_get('post_max_size') . "\n";
echo "memory_limit = " . ini_get('memory_limit') . "\n";
echo "max_execution_time = " . ini_get('max_execution_time') . "\n";
echo "max_input_time = " . ini_get('max_input_time') . "\n";
echo "\nLoaded Configuration File: " . php_ini_loaded_file() . "\n";
echo "\nAdditional .ini files:\n" . php_ini_scanned_files() . "\n";
echo "\nPHP Version: " . phpversion() . "\n";
echo "Server API: " . php_sapi_name() . "\n";
?>

