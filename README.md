# BigDump 2.21 - Staggered MySQL Dump Importer

[![PHP Version](https://img.shields.io/badge/php-8.1+-yellow.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Package Version](https://img.shields.io/badge/version-2.21-blue.svg)](https://php.net/)
[![Build Assets](https://img.shields.io/badge/build-GitHub_Actions-2088FF.svg)](https://github.com/w3spi5/bigdump/actions)

<p align="center">
  <img src="docs/logo.png" alt="BigDump Logo" width="400">
</p>

BigDump is a PHP tool for importing large MySQL dumps on web servers with strict execution time limits. This major version 2 is a complete refactoring using object-oriented MVC architecture.

See [CHANGELOG.md](docs/CHANGELOG.md) for detailed version history.

## Features

- **Staggered Import**: Imports dumps in sessions to bypass timeout limits
- **Multi-format Support**: `.sql`, `.gz` (gzip), and `.csv` files
- **SSE Streaming**: Real-time progress with Server-Sent Events and elapsed timer
- **SQL Preview**: Preview file contents and queries before importing
- **Import History**: Track all import operations with statistics
- **Session Persistence**: Resume imports after browser refresh or server restart
- **Modern Interface**: Tailwind CSS with dark mode, drag & drop upload, smooth animations
- **Zero-CDN**: Self-hosted purged assets (~47KB total vs ~454KB CDN)
- **Auto-Tuning**: Dynamic batch size based on available RAM (up to 1.5M lines/batch)
- **Enhanced Security**: Protection against path traversal, XSS, and other vulnerabilities
- **UTF-8 Support**: Proper handling of multi-byte characters and BOM

## Performance Optimizations (v2.16)

BigDump 2.16 includes several performance optimizations that significantly reduce import time:

### MySQL Pre-queries (Enabled by Default)
```php
'pre_queries' => [
    'SET autocommit=0',        // Batch commits instead of per-INSERT
    'SET unique_checks=0',     // Skip unique index verification
    'SET foreign_key_checks=0', // Skip FK constraint checks
    'SET sql_log_bin=0',       // Disable binary logging
],
```
**Impact:** 5-10x faster imports by eliminating per-row overhead.

### Optimized SQL Parsing
- **Quote Analysis**: Uses `strpos()` jumps instead of character-by-character iteration
- **INSERT Detection**: String functions replace complex regex patterns
- **Buffered Reading**: 64KB read buffer reduces system calls

### Performance Comparison

| Optimization | Before | After | Improvement |
|-------------|--------|-------|-------------|
| MySQL autocommit | Per-INSERT commit | Batch commit | ~10x |
| Quote parsing | O(n) per char | O(1) jumps | ~3x |
| INSERT detection | Complex regex | String functions | ~2x |
| File I/O | 16KB fgets | 64KB buffer | ~2x |

## Performance Profiles (v2.19)

BigDump 2.19 introduces a **performance profile system** allowing you to choose between optimized configurations:

### Conservative Mode (Default)
Best for shared hosting environments with limited memory (64MB).

```php
'performance_profile' => 'conservative',
```

### Aggressive Mode
For dedicated servers with 128MB+ memory, providing +20-30% throughput improvement.

```php
'performance_profile' => 'aggressive',
```

### Profile Comparison

| Setting | Conservative | Aggressive |
|---------|-------------|------------|
| `insert_batch_size` | 2,000 | 5,000 |
| `file_buffer_size` | 64KB | 128KB |
| `max_batch_bytes` | 16MB | 32MB |
| `commit_frequency` | Every batch | Every 3 batches |
| Memory limit | <64MB | <128MB |

**Note:** Aggressive mode automatically falls back to conservative if PHP `memory_limit` is below 128MB.

## Requirements

- PHP 8.1 or higher
- MySQLi extension
- MySQL/MariaDB server
- Write permissions on the `uploads/` directory

## Installation

### Option 1: Download ZIP (Recommended for Shared Hosting)

1. **Download** the latest release from GitHub:
   - Go to [Releases](https://github.com/w3spi5/bigdump/releases)
   - Download the ZIP file of the latest version
   - Extract and upload the `bigdump` folder to your web server via FTP or your hosting's File Manager

2. **Configure** the database:
   - Copy `config/config.example.php` to `config/config.php`
   - Edit `config/config.php` with your database credentials:
   ```php
   return [
       'db_server' => 'localhost',
       'db_name' => 'your_database',
       'db_username' => 'your_username',
       'db_password' => 'your_password',
       'db_connection_charset' => 'utf8mb4',
   ];
   ```

3. **Set permissions** (if needed):
   - Ensure the `uploads/` directory is writable (755 or 775)
   - Most hosting control panels allow this via File Manager → Permissions

4. **Access** BigDump via your browser:
   ```
   http://your-site.com/bigdump/
   ```

### Option 2: Git Clone (For Developers with Terminal Access)

```bash
git clone https://github.com/w3spi5/bigdump.git
cd bigdump
cp config/config.example.php config/config.php
# Edit config/config.php with your credentials
chmod 755 uploads/
```

> **Note:** If you have SSH access with `mysql` CLI available, you can import dumps directly with `mysql -u user -p database < dump.sql`. BigDump is specifically designed for environments where this isn't possible (shared hosting with only phpMyAdmin/web access).

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

## How It Works

### Staggered Import (Progress in Steps)

BigDump uses a **staggered import** approach - you'll notice the progress counters increment in steps (every ~5 seconds) rather than continuously. **This is by design:**

- **Avoids PHP timeouts**: Each batch completes within `max_execution_time`
- **Server breathing room**: Prevents overloading shared hosting environments
- **Shared hosting compatible**: Works on hosts with strict execution limits
- **Resume capability**: If interrupted, import can resume from the last batch

The batch size is automatically tuned based on your server's available RAM (see Auto-Tuning section).

### Real-time Progress with SSE

BigDump uses **Server-Sent Events (SSE)** for real-time progress updates:
- Single persistent HTTP connection (no polling overhead)
- Progress updates sent after each batch completes
- Elapsed time counter updates every second
- Automatic reconnection if connection drops

## Troubleshooting

### SSE "Connecting..." Modal Stuck

If the progress modal stays on "Connecting..." indefinitely but the import actually works (data appears in database), your server is buffering SSE responses.

**Solutions by server type:**

| Server | Configuration File | Fix |
|--------|-------------------|-----|
| **Apache + mod_fcgid** | `conf/extra/httpd-fcgid.conf` | Add `FcgidOutputBufferSize 0` |
| **Apache + mod_proxy_fcgi** | VirtualHost config | Add `flushpackets=on` to ProxyPass |
| **nginx + PHP-FPM** | `nginx.conf` | Add `proxy_buffering off;` and `fastcgi_buffering off;` |
| **Laragon (Windows)** | Uses mod_fcgid | Edit `laragon/bin/apache/httpd-2.4.x/conf/extra/httpd-fcgid.conf` |

**Quick diagnostic**: Test with PHP's built-in server:
```bash
cd /path/to/bigdump
php -S localhost:8000
```
If the built-in server works but Apache/nginx doesn't, it's definitely a server buffering issue.

### Import Errors

- **"Table already exists"**: Use the "Drop & Restart" button to drop tables and restart
- **"No active import session"**: Refresh the page and try again (timing issue, auto-retries)
- **Timeout errors**: Reduce `linespersession` in config or enable `auto_tuning`

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