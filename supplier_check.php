<?php
// Handle CSV download requests
if (isset($_POST['download_csv']) && isset($_POST['data'])) {
    $type = $_POST['download_csv'];
    $data = json_decode($_POST['data'], true);
    $filename = $type . '_suppliers_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Generate CSV content
    $output = fopen('php://temp', 'r+');
    fputcsv($output, ['Supplier Name']);
    foreach ($data as $row) {
        fputcsv($output, [$row]);
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    
    echo $csv;
    exit;
}
// Configure PHP for large file handling
// Note: upload_max_filesize and post_max_size can't be changed using ini_set()
// These must be set in php.ini or .htaccess
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
ini_set('max_file_uploads', 20);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check actual PHP configuration limits
$actual_upload_limit = return_bytes(ini_get('upload_max_filesize'));
$actual_post_limit = return_bytes(ini_get('post_max_size'));
$config_warning = '';

if ($actual_upload_limit < 50*1024*1024 || $actual_post_limit < 50*1024*1024) {
    $config_warning = "Warning: Your PHP configuration limits file uploads to " . 
                     formatBytes($actual_upload_limit) . " (upload_max_filesize) and " . 
                     formatBytes($actual_post_limit) . " (post_max_size). " .
                     "For large CSV files, ask your server administrator to increase these limits in php.ini.";
}

// Function to convert PHP ini values to bytes
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// Function to format bytes to human-readable format
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Debug mode - set to true to see detailed information
$debug_mode = true;

// List of provided suppliers
$provided_suppliers = [
    'MERCEDES', 'CASE', 'CATERPILLAR', 'FRUEHAUF', 'CLAAS', 'CUMMINS',
    'DENNIS', 'ERF', 'EVOBUS', 'FREIGHTLINER', 'GENERAL TRAILER', 'HENDRICKSON',
    'IRISBUS', 'ISUZU', 'IVECO', 'JCB', 'JOHN DEERE', 'KASSBOHRER', 'KRONE',
    'KOGEL', 'LAND ROVER', 'LEYLAND DAF', 'LIAZ BUS', 'LIEBHERR', 'MAN',
    'MAGIRUS DEUTZ', 'MASSEY FERGUSSON', 'MITSUBISHI', 'MONTRACON', 'NEW HOLLAND',
    'NEOPLAN', 'NOOTEBOOM', 'OPTARE', 'PEUGEOT', 'RENAULT', 'SCANIA', 'SAF',
    'SCHMITZ', 'SDC', 'SOLARIS', 'VANHOOL', 'VDL', 'VOLVO', 'WRIGHTBUS'
];

// Create a lookup array for faster matching
$provided_suppliers_lookup = array_flip($provided_suppliers);

$results = [];
$error = '';
$processing_info = [];

// Function to get upload error message
function getUploadError($code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
        UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
        UPLOAD_ERR_PARTIAL => 'Partial upload',
        UPLOAD_ERR_NO_FILE => 'No file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
        UPLOAD_ERR_EXTENSION => 'PHP extension stopped upload',
        'NO_FILE' => 'No file selected'
    ];
    return $errors[$code] ?? "Unknown error (code: $code)";
}

// Function to sanitize supplier name
function sanitizeSupplierName($name) {
    // Remove any non-alphanumeric characters except spaces and dashes
    $name = preg_replace('/[^\p{L}\p{N}\s\-]/u', '', $name);
    // Convert to uppercase and trim whitespace
    return strtoupper(trim($name));
}

// Function to convert array to CSV
function arrayToCSV($array) {
    $output = fopen('php://temp', 'r+');
    fputcsv($output, ['Supplier Name']);
    foreach ($array as $row) {
        fputcsv($output, [$row]);
    }
    rewind($output);
    $csv = stream_get_contents($output);
    fclose($output);
    return $csv;
}

// Function to process pipe-separated values
// Already implemented in processSupplierValues below

// Function to split and sanitize pipe-separated supplier values
function processSupplierValues($supplierText) {
    if (empty($supplierText)) {
        return [];
    }
    
    // Split by pipe and get unique values
    $values = array_map('trim', explode('|', $supplierText));
    
    // Remove empty values and sanitize each value
    $processedValues = [];
    foreach ($values as $value) {
        $value = trim($value);
        if (!empty($value)) {
            $sanitized = sanitizeSupplierName($value);
            if (!empty($sanitized)) {
                $processedValues[$sanitized] = true;  // Using array keys ensures uniqueness
            }
        }
    }
    
    // Return unique values only
    return array_keys($processedValues);
}

// Function to display pipe-separated values
function displayPipeSeparatedValue($originalValue, $data, $provided_suppliers) {
    if (empty($originalValue) || !is_array($data)) {
        return '';
    }
    
    $output = '<div class="value-group">';
    $output .= '<div><strong>Original Value:</strong> <div class="original-value">' . htmlspecialchars($originalValue) . '</div></div>';
    
    if (!empty($data['rows'])) {
        $output .= '<div><strong>Found in Row(s):</strong> ' . implode(', ', $data['rows']) . '</div>';
    }
    
    if (!empty($data['processed_values'])) {
        $output .= '<div style="margin-top: 10px;"><strong>Processed Values:</strong><div>';
        foreach (array_keys($data['processed_values']) as $supplier) {
            $inList = in_array($supplier, $provided_suppliers);
            $class = $inList ? 'exists' : 'additional';
            $output .= '<span class="extracted-value ' . $class . '">' . 
                      htmlspecialchars($supplier) . 
                      ($inList ? ' <span style="color: #28a745;">✓</span>' : '') . 
                      '</span> ';
        }
        $output .= '</div></div>';
    }
    
    $output .= '</div>';
    return $output;
}

// Memory-efficient CSV processing using generators
function processCSV($filePath, $supplierIndex) {
    $handle = fopen($filePath, "r");
    if ($handle === FALSE) {
        throw new Exception("Could not open uploaded file");
    }

    $rowNumber = 0;
    $invalidRows = 0;
    $processedSuppliers = [];
    $csvSuppliersFound = [];
    $invalidEntries = [];
    $additionalSuppliers = [];
    $supplierSources = []; // Track which row each supplier came from
    $originalValues = []; // Track original values for each processed supplier
    $originalToProcessed = []; // Map original values to processed values
    $rowsWithPipes = []; // Track rows containing pipe characters
    $valueCount = 0; // Track total values processed (before de-duplication)

    // Skip header row
    fgetcsv($handle);
    $rowNumber++;

    try {
        while (($data = fgetcsv($handle)) !== FALSE) {
            $rowNumber++;
            
            // Check if the supplier column exists in this row
            if (!isset($data[$supplierIndex])) {
                $invalidRows++;
                continue;
            }
            
            $rawSupplier = $data[$supplierIndex];
            
            // Check if this value contains pipes for statistics
            if (strpos($rawSupplier, '|') !== false) {
                $rowsWithPipes[] = $rowNumber;
            }
            
            // Split by pipe character and process each value
            $supplierValues = processSupplierValues($rawSupplier);
            
            // Skip rows with no valid supplier values
            if (empty($supplierValues)) {
                $invalidEntries[] = [
                    'row' => $rowNumber,
                    'value' => htmlspecialchars($rawSupplier),
                    'reason' => 'No valid supplier names found'
                ];
                continue;
            }
            
            $valueCount += count($supplierValues);
            
            // Track the mapping from original value to processed values
            if (!isset($originalToProcessed[$rawSupplier])) {
                $originalToProcessed[$rawSupplier] = [
                    'rows' => [],
                    'processed_values' => []
                ];
            }
            $originalToProcessed[$rawSupplier]['rows'][] = $rowNumber;
            
            // Process each individual supplier value
            foreach ($supplierValues as $supplier) {
                // Store the original value relationship for this processed supplier
                $originalValues[$supplier] = $rawSupplier;
                $originalToProcessed[$rawSupplier]['processed_values'][$supplier] = true;
                
                // Handle duplicate case-insensitively 
                if (isset($processedSuppliers[$supplier])) {
                    // Just update the rows if this is a duplicate
                    if (!is_array($supplierSources[$supplier])) {
                        $supplierSources[$supplier] = [$supplierSources[$supplier]];
                    }
                    $supplierSources[$supplier][] = $rowNumber;
                    continue;
                }
                
                $processedSuppliers[$supplier] = true;
                $csvSuppliersFound[] = $supplier;
                $supplierSources[$supplier] = [$rowNumber]; // Store as array to track multiple occurrences
                
                // Check if this is an additional supplier not in our provided list
                if (!isset($GLOBALS['provided_suppliers_lookup'][$supplier])) {
                    $additionalSuppliers[] = $supplier;
                }
            }
            
            // Free up memory periodically
            if ($rowNumber % 10000 === 0) {
                gc_collect_cycles();
            }
        }
    } finally {
        fclose($handle);
    }

    return [
        'csv_suppliers' => $csvSuppliersFound,
        'additional_suppliers' => $additionalSuppliers,
        'invalid_entries' => $invalidEntries,
        'total_rows' => $rowNumber - 1, // Subtract header row
        'invalid_rows' => $invalidRows,
        'unique_suppliers' => count($processedSuppliers),
        'total_supplier_values' => $valueCount,
        'supplier_sources' => $supplierSources,
        'original_values' => $originalValues,
        'original_to_processed' => $originalToProcessed,
        'rows_with_pipes' => $rowsWithPipes,
        'pipe_separated_count' => count($rowsWithPipes)
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_time = microtime(true);
    
    try {
        // Debug information - show what was submitted
        if ($debug_mode) {
            $debug_info = [];
            $debug_info['POST Vars'] = $_POST;
            $debug_info['FILES Array'] = $_FILES;
            $debug_info['Upload Max Filesize'] = ini_get('upload_max_filesize') . ' (' . formatBytes($actual_upload_limit) . ')';
            $debug_info['Post Max Size'] = ini_get('post_max_size') . ' (' . formatBytes($actual_post_limit) . ')';
            $debug_info['Memory Limit'] = ini_get('memory_limit');
            $debug_info['Max Execution Time'] = ini_get('max_execution_time');
            $debug_info['Max Input Time'] = ini_get('max_input_time');
            $debug_info['Content Type'] = $_SERVER['CONTENT_TYPE'] ?? 'Not set';
            $debug_info['Content Length'] = $_SERVER['CONTENT_LENGTH'] ?? 'Not set';
            $debug_info['Content Length (Formatted)'] = isset($_SERVER['CONTENT_LENGTH']) ? formatBytes((int)$_SERVER['CONTENT_LENGTH']) : 'Not set';
            $debug_info['Server Software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Not set';
            $debug_info['PHP Version'] = phpversion();
            $debug_info['Server API'] = php_sapi_name();
            $debug_info['Loaded PHP INI'] = php_ini_loaded_file();
            $debug_info['Temp Directory'] = sys_get_temp_dir();
            
            // Check if we're actually using PHP-FPM instead of mod_php
            if (function_exists('apache_get_modules')) {
                $debug_info['Apache Modules'] = apache_get_modules();
            } else {
                $debug_info['Apache Modules'] = 'Function apache_get_modules not available - likely using PHP-FPM';
            }
            
            // Store debug info for display later
            $processing_info['debug'] = $debug_info;
            
            // Log this information to help with debugging
            error_log("PHP Upload Debug: Max upload size: " . ini_get('upload_max_filesize') . 
                      ", Post size: " . ini_get('post_max_size') . 
                      ", Content Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'Not set'));
        }
        
        // Check if the form was submitted but no files section exists
        if (empty($_FILES)) {
            // Check if content length exceeds post_max_size
            if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > $actual_post_limit) {
                throw new Exception("The uploaded file exceeds the post_max_size limit of " . formatBytes($actual_post_limit) . 
                                  ". Your file size is approximately " . formatBytes((int)$_SERVER['CONTENT_LENGTH']) . 
                                  ". The server administrator needs to increase limits in php.ini. Specific file: " . php_ini_loaded_file());
            } else {
                throw new Exception("No file data received. This could be due to exceeding post_max_size (" . ini_get('post_max_size') . ") or a missing enctype in the form.");
            }
        }
        
        // Validate file upload
        if (!isset($_FILES['csv_file'])) {
            throw new Exception("Form submitted but 'csv_file' field is missing. Available fields: " . implode(', ', array_keys($_FILES)));
        }
        
        // Check if the file input is empty
        if ($_FILES['csv_file']['size'] === 0 && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            throw new Exception("Empty file submitted. Please select a valid CSV file.");
        }
        
        if ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $error_code = $_FILES['csv_file']['error'];
            $error_msg = getUploadError($error_code);
            $additional_info = "";
            
            // Add more detail for specific errors
            if ($error_code === UPLOAD_ERR_INI_SIZE) {
                $additional_info = " (limit: " . ini_get('upload_max_filesize') . ")";
            } elseif ($error_code === UPLOAD_ERR_FORM_SIZE) {
                $additional_info = " (form limit exceeded)";
            } elseif ($error_code === UPLOAD_ERR_PARTIAL) {
                $additional_info = " - Please check your internet connection and try again.";
            }
            
            throw new Exception("File upload error: " . $error_msg . $additional_info);
        }
        
        // Verify file type (basic check)
        $file_info = pathinfo($_FILES['csv_file']['name']);
        if (!isset($file_info['extension']) || strtolower($file_info['extension']) !== 'csv') {
            throw new Exception("Only CSV files are accepted");
        }
        
        $file_size_mb = round($_FILES['csv_file']['size'] / (1024 * 1024), 2);
        $processing_info['file_size'] = $file_size_mb . ' MB';
        $processing_info['file_name'] = $_FILES['csv_file']['name'];
        
        // Process headers to find supplier column
        $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
        if ($handle === FALSE) {
            throw new Exception("Could not open uploaded file");
        }
        
        // Read and clean headers
        $headers = fgetcsv($handle);
        if ($headers === NULL) {
            fclose($handle);
            throw new Exception("Empty CSV file or invalid format");
        }
        
        // Clean headers and find supplier column
        $headers = array_map(function($h) {
            return strtolower(trim($h, " \t\n\r\0\x0B\xEF\xBB\xBF")); // Remove BOM and whitespace
        }, $headers);
        fclose($handle);
        
        $supplier_index = array_search('supplier', $headers);
        if ($supplier_index === FALSE) {
            throw new Exception("Supplier column not found in CSV headers. Available columns: " . implode(', ', $headers));
        }
        
        // Process the CSV file
        $csv_results = processCSV($_FILES['csv_file']['tmp_name'], $supplier_index);
        
        // Compare with provided suppliers
        $existing = array_intersect($provided_suppliers, $csv_results['csv_suppliers']);
        $missing = array_diff($provided_suppliers, $csv_results['csv_suppliers']);
        
        // Calculate processing time
        $end_time = microtime(true);
        $processing_time = round($end_time - $start_time, 2);
        
        $results = [
            'existing' => $existing,
            'missing' => $missing,
            'additional' => $csv_results['additional_suppliers'],
            'invalid' => $csv_results['invalid_entries'],
            'total_rows' => $csv_results['total_rows'],
            'invalid_rows' => $csv_results['invalid_rows'],
            'unique_suppliers' => $csv_results['unique_suppliers'],
            'total_supplier_values' => $csv_results['total_supplier_values'],
            'supplier_sources' => $csv_results['supplier_sources'],
            'original_values' => $csv_results['original_values'],
            'original_to_processed' => $csv_results['original_to_processed'],
            'rows_with_pipes' => $csv_results['rows_with_pipes'],
            'pipe_separated_count' => $csv_results['pipe_separated_count']
        ];
        
        $processing_info['processing_time'] = $processing_time . ' seconds';
        $processing_info['memory_peak'] = round(memory_get_peak_usage() / 1024 / 1024, 2) . ' MB';
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>CSV Supplier Checker</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
            color: #333;
        }
        h1 {
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .upload-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
        }
        input[type="file"] {
            padding: 10px;
            margin-right: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
        }
        button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background: #0069d9;
        }
        .download-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        .download-btn:hover {
            background: #218838;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .info-box {
            background: #e2f3f7;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #bee5eb;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        table, th, td {
            border: 1px solid #dee2e6;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
        }
        td {
            padding: 10px;
        }
        .exists {
            color: #28a745;
            font-weight: bold;
        }
        .missing {
            color: #dc3545;
            font-weight: bold;
        }
        .additional {
            color: #17a2b8;
            font-weight: bold;
        }
        .invalid {
            color: #ffc107;
        }
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .stat-box {
            flex: 1;
            min-width: 200px;
            background: #f8f9fa;
            padding: 15px;
            margin: 5px;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
        }
        @media screen and (max-width: 768px) {
            .stat-box {
                min-width: 100%;
            }
        }
        .pipe-info {
            font-size: 12px;
            color: #6c757d;
            display: block;
            margin-top: 3px;
        }
        .pipe-info code {
            background: #f8f9fa;
            padding: 2px 4px;
            border-radius: 3px;
            border: 1px solid #dee2e6;
            font-family: monospace;
        }
        
        /* Styles for pipe separated values display */
        .pipe-separator {
            color: #dc3545;
            font-weight: bold;
            padding: 0 5px;
        }
        .value-group {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
        }
        .original-value {
            font-family: monospace;
            background: #e9ecef;
            padding: 5px 10px;
            border-radius: 3px;
            border: 1px solid #ced4da;
            display: inline-block;
            margin-bottom: 10px;
        }
        .row-info {
            font-size: 12px;
            color: #6c757d;
            margin-left: 10px;
        }
        .extracted-value {
            background: #fff;
            border: 1px solid #ced4da;
            border-radius: 3px;
            padding: 3px 8px;
            margin: 3px;
            display: inline-block;
        }
        .collapsible {
            background-color: #f1f1f1;
            color: #444;
            cursor: pointer;
            padding: 18px;
            width: 100%;
            border: none;
            text-align: left;
            outline: none;
            font-size: 15px;
            transition: 0.4s;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .active, .collapsible:hover {
            background-color: #e9ecef;
        }
        .collapsible:after {
            content: '\002B';
            color: #777;
            font-weight: bold;
            float: right;
            margin-left: 5px;
        }
        .active:after {
            content: "\2212";
        }
        .content {
            padding: 0 18px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.2s ease-out;
            background-color: white;
            border-radius: 0 0 5px 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>CSV Supplier Checker</h1>
        
        <div class="upload-form">
            <form method="post" enctype="multipart/form-data">
                <input type="file" name="csv_file" accept=".csv" required>
                <button type="submit">Process CSV File</button>
                <p class="form-help">Select a CSV file with a 'supplier' column. Maximum file size: <?= ini_get('upload_max_filesize') ?> (<?= formatBytes($actual_upload_limit) ?>)</p>
            </form>
            <?php if (!empty($config_warning)): ?>
                <div class="warning-message" style="margin-top: 15px; padding: 10px; background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; border-radius: 4px;">
                    <strong>⚠️ Warning:</strong> <?= $config_warning ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="info-box">
            <h3>How Data is Processed</h3>
            <ol>
                <li>CSV file is read row by row</li>
                <li>For each row, the value in the "supplier" column is extracted</li>
                <li>The value is split by the pipe character (|) into separate supplier values</li>
                <li>Each supplier value is sanitized (non-alphanumeric characters removed, converted to uppercase)</li>
                <li>Duplicate supplier values are filtered out</li>
                <li>Each unique supplier is compared against the provided suppliers list</li>
                <li>Results are displayed in the tables below</li>
            </ol>
        </div>
        
        <!-- Debug information commented out 
        <?php if ($debug_mode && !empty($processing_info['debug'])): ?>
            <div class="info-box">
                <h3>Debug Information</h3>
                <pre><?php 
                    foreach ($processing_info['debug'] as $key => $value) {
                        echo "<strong>$key:</strong>\n";
                        print_r($value);
                        echo "\n\n";
                    }
                ?></pre>
                
                <h3>PHP Configuration Details</h3>
                <pre>
<strong>Loaded PHP Configuration File:</strong>
<?php echo php_ini_loaded_file(); ?>

<strong>Additional .ini Files Loaded:</strong>
<?php 
$scanned_files = php_ini_scanned_files();
if ($scanned_files) {
    echo str_replace(',', "\n", $scanned_files);
} else {
    echo "None";
}
?>

<strong>Actual PHP Settings (as reported by PHP):</strong>
upload_max_filesize: <?php echo ini_get('upload_max_filesize'); ?> (<?php echo formatBytes(return_bytes(ini_get('upload_max_filesize'))); ?>)
post_max_size: <?php echo ini_get('post_max_size'); ?> (<?php echo formatBytes(return_bytes(ini_get('post_max_size'))); ?>)
memory_limit: <?php echo ini_get('memory_limit'); ?> (<?php echo formatBytes(return_bytes(ini_get('memory_limit'))); ?>)
max_execution_time: <?php echo ini_get('max_execution_time'); ?> seconds
max_input_time: <?php echo ini_get('max_input_time'); ?> seconds
max_file_uploads: <?php echo ini_get('max_file_uploads'); ?>
file_uploads: <?php echo ini_get('file_uploads') ? 'Enabled' : 'Disabled'; ?>

<strong>Apache Module Information:</strong>
<?php
$apache_modules = apache_get_modules();
echo "PHP Module: " . (in_array('mod_php8.2', $apache_modules) ? "Loaded (mod_php8.2)" : "Not loaded (mod_php8.2)") . "\n";
echo "PHP Module: " . (in_array('mod_php8.1', $apache_modules) ? "Loaded (mod_php8.1)" : "Not loaded (mod_php8.1)") . "\n";
echo "PHP Module: " . (in_array('mod_php8.3', $apache_modules) ? "Loaded (mod_php8.3)" : "Not loaded (mod_php8.3)") . "\n";
?>

<strong>Form Configuration:</strong>
Form enctype: multipart/form-data
Max POST size: <?php echo ini_get('post_max_size'); ?>
                </pre>
                
                <h3>Troubleshooting Information</h3>
                <div style="background-color: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 5px;">
                    <p>If you're still experiencing upload issues:</p>
                    <ol>
                        <li>Check if the Apache PHP module is correctly loaded (see above)</li>
                        <li>Verify that both upload_max_filesize and post_max_size are large enough for your file (they should be set to 100M)</li>
                        <li>Check if the server has been restarted after configuration changes</li>
                        <li>Ensure that the file upload temporary directory is writable: <?php echo sys_get_temp_dir(); ?></li>
                        <li>Try uploading a smaller file to test if the upload functionality works at all</li>
                    </ol>
                    
                    <p><strong>Technical information for server administrator:</strong></p>
                    <code>
                    sudo bash -c 'cat > /etc/php/8.2/apache2/conf.d/custom.ini' << 'EOL'
                    upload_max_filesize = 100M
                    post_max_size = 100M
                    memory_limit = 512M
                    max_execution_time = 300
                    max_input_time = 300
                    EOL

                    sudo systemctl restart apache2
                    </code>
                </div>
            </div>
        <?php endif; ?>
        -->
        
        <?php if ($error): ?>
            <div class="error-message">
                <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($results)): ?>
            <div class="success-message">
                <strong>Success!</strong> CSV file processed successfully.
            </div>
            
            <!-- Processing Information -->
            <div class="info-box">
                <h3>Processing Information</h3>
                <div class="stats-container">
                    <div class="stat-box">
                        <div>File Name</div>
                        <div><?= htmlspecialchars($processing_info['file_name']) ?></div>
                    </div>
                    <div class="stat-box">
                        <div>File Size</div>
                        <div class="stat-number"><?= $processing_info['file_size'] ?></div>
                    </div>
                    <div class="stat-box">
                        <div>Total Rows</div>
                        <div class="stat-number"><?= number_format($results['total_rows']) ?></div>
                    </div>
                        <div class="stat-box">
                            <div>Total Supplier Values</div>
                            <div class="stat-number"><?= number_format($results['total_supplier_values']) ?></div>
                        </div>
                        <div class="stat-box">
                            <div>Unique Suppliers</div>
                            <div class="stat-number"><?= number_format($results['unique_suppliers']) ?></div>
                        </div>
                        <div class="stat-box">
                            <div>Pipe-Separated Rows</div>
                            <div class="stat-number"><?= number_format($results['pipe_separated_count']) ?></div>
                        </div>
                    <div class="stat-box">
                        <div>Processing Time</div>
                        <div class="stat-number"><?= $processing_info['processing_time'] ?></div>
                    </div>
                    <div class="stat-box">
                        <div>Peak Memory Usage</div>
                        <div class="stat-number"><?= $processing_info['memory_peak'] ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Results -->
            <h2>Analysis Results</h2>
            
            <!-- Matching Suppliers -->
            <h3>Matching Suppliers (<?= count($results['existing']) ?>)</h3>
            <?php if (!empty($results['existing'])): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Supplier Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($results['existing'] as $supplier): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($supplier) ?></td>
                                <td class="exists">Present in CSV</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No matching suppliers found in the CSV file.</p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="download_csv" value="matching">
                <input type="hidden" name="data" value="<?= htmlspecialchars(json_encode($results['existing'])) ?>">
                <button type="submit" class="download-btn">⬇️ Download Matching Suppliers List</button>
            </form>
            
            <!-- Missing Suppliers -->
            <h3>Missing Suppliers (<?= count($results['missing']) ?>)</h3>
            <?php if (!empty($results['missing'])): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Supplier Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($results['missing'] as $supplier): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($supplier) ?></td>
                                <td class="missing">Not Found in CSV</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>All provided suppliers were found in the CSV file. Great!</p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="download_csv" value="missing">
                <input type="hidden" name="data" value="<?= htmlspecialchars(json_encode($results['missing'])) ?>">
                <button type="submit" class="download-btn">⬇️ Download Missing Suppliers List</button>
            </form>
            
            <!-- Additional Suppliers -->
            <h3>Additional Suppliers (<?= count($results['additional']) ?>)</h3>
            <?php if (!empty($results['additional'])): ?>
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Supplier Name</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($results['additional'] as $supplier): ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= htmlspecialchars($supplier) ?></td>
                                <td class="additional">Not in Provided List</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No additional suppliers found in the CSV file.</p>
            <?php endif; ?>
            <form method="post">
                <input type="hidden" name="download_csv" value="additional">
                <input type="hidden" name="data" value="<?= htmlspecialchars(json_encode($results['additional'])) ?>">
                <button type="submit" class="download-btn">⬇️ Download Additional Suppliers List</button>
            </form>
            
            <!-- Detailed Pipe-Separated Values Analysis -->
            <?php if (!empty($results) && !empty($results['original_values'])): ?>
                <h3 class="collapsible">Detailed Pipe-Separated Values Analysis</h3>
                <div class="content">
                    <div class="info-box" style="margin-top: 15px;">
                        <h4>Understanding Pipe-Separated Values</h4>
                        <p>
                            This section shows detailed analysis of pipe-separated values found in your CSV file. The system splits values in the "supplier" column 
                            that contain the pipe character (|) into separate supplier names.
                        </p>
                        <p>
                            For example, a CSV cell containing <code>MERCEDES|VOLVO|SCANIA</code> will be processed as three separate supplier names:
                            <span class="extracted-value">MERCEDES</span>
                            <span class="extracted-value">VOLVO</span>
                            <span class="extracted-value">SCANIA</span>
                        </p>
                    </div>
                    
                    <div class="stats-container" style="margin-top: 20px;">
                        <div class="stat-box">
                            <div>Total Supplier Values</div>
                            <div class="stat-number"><?= number_format($results['total_supplier_values']) ?></div>
                        </div>
                        <div class="stat-box">
                            <div>Unique Supplier Names</div>
                            <div class="stat-number"><?= number_format($results['unique_suppliers']) ?></div>
                        </div>
                        <div class="stat-box">
                            <div>Pipe-Separated Values</div>
                            <div class="stat-number"><?= 
                                number_format(
                                    count(array_filter(
                                        array_values($results['original_values']), 
                                        function($v) { return strpos($v, '|') !== false; }
                                    ))
                                ) 
                            ?></div>
                        </div>
                    </div>
                    
                    <h4 style="margin-top: 20px;">Original Values and Extracted Suppliers</h4>
                    <?php
                    // Process the original_to_processed data for display
                    $originalToExtracted = [];
                    
                    // First, gather all original values with their processed values and rows
                    foreach ($results['original_to_processed'] as $originalValue => $data) {
                        $processedValues = array_keys($data['processed_values']);
                        $originalToExtracted[$originalValue] = [
                            'suppliers' => $processedValues,
                            'rows' => $data['rows']
                        ];
                    }
                    
                    // Sort by original value
                    ksort($originalToExtracted);
                    
                    // Separate pipe-separated values from single values
                    $pipeSeparated = [];
                    $singleValues = [];
                    
                    foreach ($originalToExtracted as $originalValue => $data) {
                        if (strpos($originalValue, '|') !== false) {
                            $pipeSeparated[$originalValue] = $data;
                        } else {
                            $singleValues[$originalValue] = $data;
                        }
                    }
                    
                    if (!empty($pipeSeparated)): 
                    ?>
                        <h5>Pipe-Separated Values</h5>
                        <?php foreach ($pipeSeparated as $originalValue => $data): ?>
                            <div class="value-group">
                                <div>
                                    <strong>Original Value:</strong> 
                                    <div class="original-value"><?= htmlspecialchars($originalValue) ?></div>
                                    <span class="row-info">
                                        Found in: Row<?= count($data['rows']) > 1 ? 's' : '' ?> 
                                        <?= implode(', ', array_slice($data['rows'], 0, 5)) ?>
                                        <?= count($data['rows']) > 5 ? '...' : '' ?>
                                    </span>
                                </div>
                                
                                <div style="margin-top: 10px;">
                                    <strong>Extracted Suppliers:</strong>
                                    <div style="margin-top: 5px;">
                                        <?php
                                        // Display processed values
                                        foreach ($data['suppliers'] as $supplier):
                                            $inProvidedList = in_array($supplier, $provided_suppliers);
                                            $statusClass = $inProvidedList ? 'exists' : 'additional';
                                        ?>
                                            <span class="extracted-value <?= $statusClass ?>">
                                                <?= htmlspecialchars($supplier) ?>
                                                <?php if ($inProvidedList): ?>
                                                    <span style="font-size: 10px; color: #28a745;">✓</span>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No pipe-separated values found in the CSV file.</p>
                    <?php endif; ?>
                    
                    <!-- Single Values Section -->
                    <h5 class="collapsible">Single Values (No Pipe Separator)</h5>
                    <div class="content">
                        <?php
                        $hasSingleValues = false;
                        
                        if (!empty($results['original_to_processed'])):
                        ?>
                            <div style="margin-top: 10px;">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Original Value</th>
                                            <th>Extracted Supplier</th>
                                            <th>Found In Row(s)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        foreach ($results['original_to_processed'] as $originalValue => $data) {
                                            // Skip pipe-separated values
                                            if (strpos($originalValue, '|') !== false) {
                                                continue;
                                            }
                                            
                                            $hasSingleValues = true;
                                            $suppliers = array_keys($data['processed_values']);
                                            if (empty($suppliers)) {
                                                continue;
                                            }
                                            
                                            $supplier = reset($suppliers); // Get the first supplier
                                            $inProvidedList = in_array($supplier, $provided_suppliers);
                                            $statusClass = $inProvidedList ? 'exists' : 'additional';
                                            ?>
                                            <tr>
                                                <td><?= htmlspecialchars($originalValue) ?></td>
                                                <td class="<?= $statusClass ?>">
                                                    <?= htmlspecialchars($supplier) ?>
                                                    <?php if ($inProvidedList): ?>
                                                        <span style="font-size: 10px; color: #28a745;">✓</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?= implode(', ', array_slice($data['rows'], 0, 5)) ?>
                                                    <?= count($data['rows']) > 5 ? '...' : '' ?>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php 
                        if (!$hasSingleValues):
                            echo '<p>No single values found in the CSV file.</p>';
                        endif;
                        else:
                            echo '<p>No single values found in the CSV file.</p>';
                        endif;
                        ?>
                    </div>
                </div>
                
                <script>
                var coll = document.getElementsByClassName("collapsible");
                var i;

                for (i = 0; i < coll.length; i++) {
                  coll[i].addEventListener("click", function() {
                    this.classList.toggle("active");
                    var content = this.nextElementSibling;
                    if (content.style.maxHeight){
                      content.style.maxHeight = null;
                    } else {
                      content.style.maxHeight = content.scrollHeight + "px";
                    } 
                  });
                }
                </script>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
