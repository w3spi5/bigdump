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
    /**
     * Initialize SSE stream with proper headers.
     *
     * Disables output buffering and sets required headers for SSE.
     * Handles Apache/mod_fcgid, nginx, and other proxies.
     */
    public function initStream(): void
    {
        // Disable output buffering at all levels
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Disable implicit flush buffering
        @ini_set('output_buffering', '0');
        @ini_set('zlib.output_compression', '0');
        @ini_set('implicit_flush', '1');

        // Enable implicit flush
        ob_implicit_flush(true);

        // SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable nginx buffering

        // Disable PHP timeout for long-running imports
        set_time_limit(0);

        // Don't ignore user abort - we want to detect disconnections
        ignore_user_abort(false);

        // Send initial connection established event
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
