<?php

declare(strict_types=1);

namespace BigDump\Services;

/**
 * Service for Server-Sent Events (SSE) streaming
 *
 * Handles SSE headers, event sending, and connection management
 * for real-time progress updates during import
 *
 * @package BigDump\Services
 * @author  w3spi5
 */
class SseService
{
    public function initStream(): void
    {
        // 1. FIRST: Disable PHP buffering at runtime BEFORE anything else
        @ini_set('output_buffering', 'Off');
        @ini_set('zlib.output_compression', 'Off');
        @ini_set('implicit_flush', '1');

        // 2. THEN: Close ALL existing output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        // 3. Enable implicit flush
        ob_implicit_flush(true);

        // 4. NOW send headers (they go directly to client, not buffer)
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        // 5. Disable PHP timeout
        set_time_limit(0);
        ignore_user_abort(false);

        // 6. Send padding to force Apache/mod_fcgid buffer flush (~8KB minimum)
        // SSE comments (lines starting with ':') are ignored by browsers
        $padding = str_repeat(": " . str_repeat("X", 2048) . "\n", 4);
        echo $padding;
        flush();

        // 7. Send initial connection established event
        $this->sendEvent('connected', ['status' => 'ok', 'time' => time()]);
    }

    /**
     * Send an SSE event with data.
     *
     * @param string $event Event name (e.g., 'progress', 'complete', 'error')
     * @param array $data Data to send as JSON
     */
    public function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Send a comment to keep the connection alive.
     *
     * Useful for preventing proxy timeouts during long operations.
     */
    public function sendKeepAlive(): void
    {
        echo ": keepalive\n\n";
        flush();
    }

    /**
     * Check if the client is still connected.
     *
     * @return bool True if client is connected
     */
    public function isClientConnected(): bool
    {
        return !connection_aborted();
    }
}
