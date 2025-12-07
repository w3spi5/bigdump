# BigDump 2.6 - Staggered MySQL Dump Importer

[![PHP Version](https://img.shields.io/badge/php-8.1+-yellow.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Package Version](https://img.shields.io/badge/version-2.6-blue.svg)](https://php.net/)

<p align="center">
  <img src="2025-12-06_04h29_29.png" alt="BigDump Screenshot" width="800">
</p>

BigDump is a PHP tool for importing large MySQL dumps on web servers with strict execution time limits. This major version 2 is a complete refactoring using object-oriented MVC architecture.

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## Features

- **Staggered Import**: Imports dumps in sessions to bypass timeout limits
- **Multi-format Support**: `.sql`, `.gz` (gzip), and `.csv` files
- **AJAX Mode**: Import without page refresh (recommended)
- **Modern Interface**: Drag & drop upload with progress bars
- **Auto-Tuning**: Dynamic batch size based on available RAM
- **Enhanced Security**: Protection against path traversal, XSS, and other vulnerabilities
- **UTF-8 Support**: Proper handling of multi-byte characters and BOM

## Requirements

- PHP 8.1 or higher
- MySQLi extension
- MySQL/MariaDB server
- Write permissions on the `uploads/` directory

## Installation

1. **Download** the project to your web server:
   ```bash
   git clone https://github.com/w3spi5/bigdump.git
   ```

2. **Configure** the database:
   ```bash
   cp config/config.example.php config/config.php
   ```
   Then edit `config/config.php` with your database credentials:
   ```php
   return [
       'db_server' => 'localhost',
       'db_name' => 'your_database',
       'db_username' => 'your_username',
       'db_password' => 'your_password',
       'db_connection_charset' => 'utf8mb4',
   ];
   ```

3. **Set permissions**:
   ```bash
   chmod 755 uploads/
   ```

4. **Access** BigDump via your browser:
   ```
   http://your-site.com/bigdump/public/index.php
   ```

## Configuration

### Auto-Tuning (RAM-based)

```php
return [
    'auto_tuning' => true,      // Enable dynamic batch sizing
    'min_batch_size' => 3000,   // Safety floor
    'max_batch_size' => 50000,  // Ceiling
];
```

| Available RAM | Batch Size |
|---------------|------------|
| < 512 MB | 3,000 |
| 512 MB - 1 GB | 8,000 |
| 1 GB - 2 GB | 15,000 |
| 2 GB - 4 GB | 25,000 |
| > 4 GB | 40,000 |

### Windows Optimization

For accurate RAM detection on Windows, enable the COM extension in `php.ini`:

```ini
extension=com_dotnet
```

### Import Options

```php
return [
    'linespersession' => 3000,  // Lines per session (if auto_tuning disabled)
    'delaypersession' => 0,     // Delay between sessions (ms)
    'ajax' => true,             // AJAX mode (recommended)
    'test_mode' => false,       // Parse without executing
];
```

### CSV Import

```php
return [
    'csv_insert_table' => 'my_table',
    'csv_preempty_table' => false,
    'csv_delimiter' => ',',
    'csv_enclosure' => '"',
];
```

### Pre-queries

```php
return [
    'pre_queries' => [
        'SET foreign_key_checks = 0',
        'SET unique_checks = 0',
    ],
];
```

## Project Structure

```
bigdump/
├── config/
│   ├── config.example.php
│   └── config.php
├── public/
│   └── index.php
├── src/
│   ├── Config/Config.php
│   ├── Controllers/BigDumpController.php
│   ├── Core/
│   │   ├── Application.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Router.php
│   │   └── View.php
│   ├── Models/
│   │   ├── Database.php
│   │   ├── FileHandler.php
│   │   ├── ImportSession.php
│   │   └── SqlParser.php
│   ├── Services/
│   │   ├── AjaxService.php
│   │   ├── AutoTunerService.php
│   │   └── ImportService.php
│   └── Views/
│       ├── error.php
│       ├── home.php
│       ├── import.php
│       └── layout.php
├── uploads/
├── CHANGELOG.md
├── LICENSE
└── README.md
```

## Security

- **NEVER** leave BigDump and your dump files on a production server after use
- Dump files may contain sensitive data
- The `uploads/` directory is protected by `.htaccess`
- Delete the application as soon as the import is complete

## License

[MIT](LICENSE)

## Credits

- **Original**: Alexey Ozerov (http://www.ozerov.de/bigdump)
- **MVC Refactoring**: Version 2 by [w3spi5](https://github.com/w3spi5)
