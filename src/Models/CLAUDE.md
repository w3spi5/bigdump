# Models Module

## Overview

Data handling layer for BigDump. Manages database connections, file operations, SQL parsing, and import session state.

## Files

### `Database.php`

MySQLi connection wrapper with pre/post query execution.

**Key Methods:**
- `connect()`: Establishes connection, executes pre-queries
- `query()`: Executes SQL with error tracking
- `executePostQueries()`: Restores settings after import

### `FileHandler.php`

Buffered file reader supporting SQL and gzip files.

**Performance Optimization (v2.16):**
- 64KB internal read buffer (`$bufferSize = 65536`)
- `readLine()` extracts lines from buffer, reducing system calls
- `tell()` accounts for unconsumed buffer data
- `seek()` clears buffer to maintain consistency

**Buffer State Management:**
```php
// tell() returns actual read position
return $pos - strlen($this->readBuffer);

// seek() clears buffer
$this->readBuffer = '';

// eof() checks buffer first
if ($this->readBuffer !== '') return false;
```

**Important:** When modifying buffered reading, always update `tell()`, `seek()`, `eof()`, and `close()` to maintain buffer consistency.

### `SqlParser.php`

SQL query parser with quote and delimiter handling.

**Performance Optimization (v2.16):**

`analyzeQuotes()` uses `strpos()` jumps instead of character iteration:

```php
// Before: O(n) character loop
while ($i < $length) { $char = $line[$i]; ... }

// After: O(1) position jumps
$quotePos = strpos($line, $this->activeQuote, $pos);
```

**Handles:**
- Single and double quotes
- Escaped quotes (`\'`, `\"`, `''`, `""`)
- Backslash sequences (`\\`)
- DELIMITER commands
- Multi-line strings

### `ImportSession.php`

Session state container for resumable imports.

**State Fields:**
- File position (`currentOffset`, `currentLine`)
- Parser state (`pendingQuery`, `inString`, `activeQuote`)
- Progress tracking (`totalQueries`, `finished`, `error`)

## Modification Guidelines

### Changing buffer size

Edit `FileHandler.php`:
```php
private int $bufferSize = 65536;  // 64KB default
```

Larger buffers reduce system calls but increase memory usage.

### Adding quote handling

Edit `SqlParser::analyzeQuotes()`. Ensure:
1. `strpos()` is used for position finding
2. Both single and double quotes are handled
3. Escape sequences are properly skipped

### Session state changes

When adding session fields:
1. Add property to `ImportSession.php`
2. Add to `toSession()` and `fromSession()` methods
3. Update `ImportService::executeSession()` to save/restore
