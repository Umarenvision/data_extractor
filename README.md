# Supplier CSV Checker

A PHP-based tool to analyze CSV files containing supplier information, handle pipe-separated values, and compare against a predefined list of suppliers.

## Features

- Process large CSV files (up to 100MB)
- Handle pipe-separated values (e.g., "MERCEDES|VOLVO|SCANIA")
- Remove duplicates automatically
- Generate downloadable CSV reports
- Show detailed statistics
- Memory-efficient processing

## Requirements

- PHP 8.2 or higher
- Apache web server
- Linux/Ubuntu environment

## Installation

1. Place the files in your web directory:
```bash
/var/www/html/dataextractor/
```

2. Configure PHP settings by creating/modifying:
```bash
sudo bash -c 'cat > /etc/php/8.2/apache2/conf.d/99-custom.ini' << 'EOL'
upload_max_filesize = 100M
post_max_size = 100M
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
EOL
```

3. Restart Apache:
```bash
sudo systemctl restart apache2
```

## Usage

1. Open in browser: `http://your-server/dataextractor/supplier_check.php`
2. Upload your CSV file (must have a 'supplier' column)
3. View results in three categories:
   - Matching Suppliers
   - Missing Suppliers
   - Additional Suppliers
4. Download results using the CSV export buttons

## CSV Format

Your CSV file should:
- Have a header row with a 'supplier' column
- Use pipe character (|) to separate multiple suppliers in one cell
- Example:
```csv
supplier
MERCEDES|VOLVO
SCANIA
EBS|DAF|VOLVO
```

## Reports Generated

1. Matching Suppliers: Suppliers found in both CSV and predefined list
2. Missing Suppliers: Suppliers in predefined list but not in CSV
3. Additional Suppliers: Suppliers in CSV but not in predefined list

## Troubleshooting

If upload fails:
1. Check PHP limits in `/etc/php/8.2/apache2/conf.d/99-custom.ini`
2. Verify Apache has restarted: `sudo systemctl status apache2`
3. Check file permissions: `/var/www/html/dataextractor` should be readable by www-data

## Notes

- The system automatically removes duplicates
- Pipe-separated values are split and processed individually
- All supplier names are converted to uppercase
- Special characters are removed from supplier names
- The system can handle CSV files with 60k+ entries efficiently

## Predefined Suppliers List

The system checks against this predefined list of suppliers:
```
MERCEDES, CASE, CATERPILLAR, FRUEHAUF, CLAAS, CUMMINS,
DENNIS, ERF, EVOBUS, FREIGHTLINER, GENERAL TRAILER, HENDRICKSON,
IRISBUS, ISUZU, IVECO, JCB, JOHN DEERE, KASSBOHRER, KRONE,
KOGEL, LAND ROVER, LEYLAND DAF, LIAZ BUS, LIEBHERR, MAN,
MAGIRUS DEUTZ, MASSEY FERGUSSON, MITSUBISHI, MONTRACON, NEW HOLLAND,
NEOPLAN, NOOTEBOOM, OPTARE, PEUGEOT, RENAULT, SCANIA, SAF,
SCHMITZ, SDC, SOLARIS, VANHOOL, VDL, VOLVO, WRIGHTBUS
```

## Processing Behavior

1. When a cell contains pipe-separated values (e.g., "EBS|VOLVO"):
   - Each value is processed separately
   - "EBS" and "VOLVO" are treated as individual suppliers
   - If "EBS" appears again in another pipe-separated list, it's counted only once

2. Text Processing:
   - All supplier names are converted to uppercase
   - Special characters and extra spaces are removed
   - Only alphanumeric characters, spaces, and dashes are allowed

3. Memory Handling:
   - Uses batch processing for large files
   - Implements garbage collection
   - Optimized for handling 60k+ entries efficiently

## Performance

- Successfully tested with CSV files containing 60,000+ entries
- Memory usage optimized for large datasets
- Processing time scales linearly with file size

## Support & Contact

For issues related to:
- File upload limits: Check the Troubleshooting section and verify PHP configuration
- CSV format: Ensure your file follows the format in the CSV Format section
- Memory issues: Your file might be too large; try splitting it into smaller files

## License

Free to use and modify. Created for supplier data verification and comparison.

---
Last updated: May 2025
