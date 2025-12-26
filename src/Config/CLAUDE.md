# Config Module

## Overview

Configuration management for BigDump. Handles loading, validation, and access to all application settings including the performance profile system (v2.19).

## Files

### `Config.php`

Central configuration class with default values and user overrides.

**Key Features:**
- Loads from `config/config.php` (array or legacy variable format)
- Provides typed getters: `get()`, `getDatabase()`, `getCsv()`
- Validates charset, numeric limits, file extensions
- **Performance Profile System** (v2.19): Conservative/aggressive modes with automatic fallback

**Performance Profile Options (v2.19):**

```php
'performance_profile' => 'conservative', // or 'aggressive'
'file_buffer_size' => 65536,    // 64KB (conservative) / 131072 (aggressive)
'insert_batch_size' => 2000,    // 2000 (conservative) / 5000 (aggressive)
'max_batch_bytes' => 16777216,  // 16MB (conservative) / 32MB (aggressive)
'commit_frequency' => 1,        // 1 (conservative) / 3 (aggressive)
```

**Profile Methods:**
- `getEffectiveProfile()` - Returns actual profile after validation
- `wasProfileDowngraded()` - True if aggressive fell back to conservative
- `getProfileInfo()` - Complete profile debugging information

**Performance-Critical Defaults:**

```php
'pre_queries' => [
    'SET autocommit=0',        // Disable per-INSERT commits
    'SET unique_checks=0',     // Skip unique index checks
    'SET foreign_key_checks=0', // Skip FK validation
    'SET sql_log_bin=0',       // Disable binary logging
],
'post_queries' => [
    'SET unique_checks=1',
    'SET foreign_key_checks=1',
    'SET autocommit=1',
],
```

These pre-queries provide 5-10x import speedup by eliminating per-row overhead.

## Modification Guidelines

### Adding a new config option

1. Add default value to `$defaults` array
2. Add to `$legacyVarNames` if supporting old format
3. Add validation in `validate()` if needed
4. Update `config/config.example.php`

### Working with Performance Profiles

Profile validation happens in `validate()`:
- Aggressive mode requires PHP `memory_limit` >= 128MB
- Automatic fallback to conservative with warning if memory insufficient
- Buffer sizes clamped to range: 64KB - 256KB

### Modifying pre-queries

Pre-queries execute at connection time. Post-queries execute after import completion.

**Warning:** `sql_log_bin=0` requires SUPER privilege on some MySQL configurations. If import fails with permission error, remove this line.

## Related Files

- `config/config.example.php` - User-facing configuration template
- `src/Models/Database.php` - Executes pre/post queries
- `src/Services/AutoTunerService.php` - Uses profile for batch calculations
- `src/Services/ImportService.php` - Uses profile for COMMIT frequency
