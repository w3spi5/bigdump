# BigDump 2.14 - Staggered MySQL Dump Importer

[![PHP Version](https://img.shields.io/badge/php-8.1+-yellow.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Package Version](https://img.shields.io/badge/version-2.14-blue.svg)](https://php.net/)
[![Build Assets](https://img.shields.io/badge/build-GitHub_Actions-2088FF.svg)](https://github.com/w3spi5/bigdump/actions)

<p align="center">
  <img src="docs/logo.png" alt="BigDump Logo" width="400">
</p>

BigDump is a PHP tool for importing large MySQL dumps on web servers with strict execution time limits. This major version 2 is a complete refactoring using object-oriented MVC architecture.

See [CHANGELOG.md](docs/CHANGELOG.md) for detailed version history.

## Features

- **Staggered Import**: Imports dumps in sessions to bypass timeout limits
- **Multi-format Support**: `.sql`, `.gz` (gzip), and `.csv` files
- **SSE Streaming**: Real-time progress with Server-Sent Events
- **SQL Preview**: Preview file contents and queries before importing
- **Import History**: Track all import operations with statistics
- **Session Persistence**: Resume imports after browser refresh or server restart
- **Modern Interface**: Tailwind CSS with dark mode, drag & drop upload, smooth animations
- **Zero-CDN**: Self-hosted purged assets (~47KB total vs ~454KB CDN)
- **Auto-Tuning**: Dynamic batch size based on available RAM (up to 1.5M lines/batch)
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
   http://your-site.com/bigdump/
   ```

## Configuration

### Auto-Tuning (RAM-based, NVMe-optimized)

```php
return [
    'auto_tuning' => true,        // Enable dynamic batch sizing
    'min_batch_size' => 10000,    // Safety floor
    'max_batch_size' => 1500000,  // NVMe ceiling
    'force_batch_size' => 0,      // Force specific size (0 = auto)
];
```

| Available RAM | Batch Size |
|---------------|------------|
| < 1 GB | 80,000 |
| < 2 GB | 150,000 |
| < 4 GB | 300,000 |
| < 8 GB | 620,000 |
| < 12 GB | 940,000 |
| < 16 GB | 1,260,000 |
| > 16 GB | 1,500,000 |

### INSERT Batching (x10-50 speedup)

For dumps with simple INSERT statements, BigDump can group them into multi-value INSERTs:

```php
return [
    'insert_batch_size' => 10000,  // Group 10000 INSERTs into 1 query (16MB max)
];
```

This transforms:
```sql
INSERT INTO t VALUES (1);
INSERT INTO t VALUES (2);
-- ... 1000 more
```
Into:
```sql
INSERT INTO t VALUES (1), (2), ... ;  -- Single query
```

### Windows Optimization

For accurate RAM detection on Windows, enable the COM extension in `php.ini`:

```ini
extension=com_dotnet
```

### Import Options

```php
return [
    'linespersession' => 50000, // Lines per session (if auto_tuning disabled)
    'delaypersession' => 0,     // Delay between sessions (ms)
    'ajax' => true,             // AJAX/SSE mode (recommended)
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

### Pre/Post-queries (Recommended for large imports)

```php
return [
    'pre_queries' => [
        'SET autocommit = 0',
        'SET unique_checks = 0',
        'SET foreign_key_checks = 0',
        'SET sql_log_bin = 0',  // Disable binary logging
    ],
    'post_queries' => [
        'COMMIT',
        'SET autocommit = 1',
        'SET unique_checks = 1',
        'SET foreign_key_checks = 1',
    ],
];
```

Pre-queries disable constraints for speed; post-queries restore them automatically after import.

## Project Structure

```
bigdump/
├── config/
│   └── config.php
├── index.php              # Entry point
├── tailwind.config.js     # Tailwind configuration
├── assets/
│   ├── dist/              # Compiled assets (auto-generated)
│   │   ├── app.min.css
│   │   └── *.min.js
│   ├── src/               # Source files
│   │   ├── css/tailwind.css
│   │   └── js/*.js
│   ├── icons.svg          # SVG icon sprite
│   └── img/
│       └── logo.png
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
│   └── Services/
│       ├── AjaxService.php
│       ├── AutoTunerService.php
│       ├── ImportService.php
│       ├── InsertBatcherService.php
│       └── SseService.php
├── templates/
│   ├── error.php
│   ├── error_bootstrap.php
│   ├── home.php
│   ├── import.php
│   └── layout.php
├── uploads/
├── scripts/
│   └── generate-icons.php # SVG sprite generator
├── .github/
│   └── workflows/
│       └── build-assets.yml  # CI asset pipeline
├── docs/
│   ├── CHANGELOG.md
│   └── logo.png
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

---

## Screenshots

<p align="center">
  <img src="docs/demov2.2.png" alt="BigDump Screenshot" width="800">
</p>
