# Services Module

## Overview

Business logic layer for BigDump. Orchestrates import operations, handles auto-tuning, INSERT batching, and real-time streaming.

## Files

### `ImportService.php`

Main import orchestrator. Coordinates all components for each import session.

**Import Flow:**
1. `executeSession()` starts timing, connects to DB
2. Opens file at saved offset via `FileHandler`
3. Loops through lines via `processLines()`
4. Each line passes through `SqlParser` then `InsertBatcherService`
5. Flushes batched INSERTs, commits transaction
6. Returns updated `ImportSession` with new state

**Key Method:** `executeSession(ImportSession $session)`

### `InsertBatcherService.php`

Groups consecutive simple INSERTs into multi-value queries for 10-50x speedup.

**Performance Optimization (v2.16):**

`parseSimpleInsert()` uses string functions instead of regex:

```php
// Before: Complex regex
$pattern = '/^INSERT\s+(?:INTO\s+)?...$/is';
preg_match($pattern, $query, $matches);

// After: String functions
if (!str_starts_with($upperQuery, 'INSERT')) return null;
$valuesPos = strpos($upperQuery, 'VALUES');
```

**Batching Logic:**
- Same table + same column list = batch together
- Different table/columns = flush and start new batch
- Byte limit: 16MB max per batched query
- Count limit: `insert_batch_size` config value

**Transforms:**
```sql
INSERT INTO t VALUES (1);
INSERT INTO t VALUES (2);
-- Becomes:
INSERT INTO t VALUES (1), (2);
```

### `AutoTunerService.php`

Dynamic batch sizing based on system RAM and file characteristics.

**Features:**
- Detects available RAM (Linux: `/proc/meminfo`, Windows: COM/WMI)
- File-aware tuning: analyzes file to determine optimal batch size
- Adaptive: adjusts batch size based on speed trends and memory pressure

**RAM-to-Batch Profiles:**
| RAM | Batch Size |
|-----|------------|
| <1GB | 80,000 |
| <4GB | 300,000 |
| <8GB | 620,000 |
| >16GB | 1,500,000 |

### `SseService.php`

Server-Sent Events streaming for real-time progress updates.

### `AjaxService.php`

Legacy AJAX polling support (deprecated, use SSE).

### `FileAnalysisService.php`

Samples SQL files to detect INSERT patterns and estimate complexity.

## Modification Guidelines

### Adding new INSERT detection patterns

Edit `InsertBatcherService::parseSimpleInsert()`:

```php
// Add rejection for new pattern
if (str_contains($upperQuery, 'NEW_PATTERN')) {
    return null;
}
```

### Adjusting auto-tuner profiles

Edit `AutoTunerService::PROFILES` or `BATCH_REFERENCE` constants.

### Adding new streaming events

Edit `SseService.php`:
1. Add event type constant
2. Add send method: `sendNewEvent()`
3. Update JS handler in `assets/src/js/sse-import.js`

## Performance Notes

1. **InsertBatcher is critical path** - Every query passes through `process()`
2. **Avoid regex in hot loops** - Use `strpos()`, `str_contains()`, `str_starts_with()`
3. **Batch flushes are expensive** - Minimize flush triggers
4. **AutoTuner runs once per session** - Batch size set at session start
