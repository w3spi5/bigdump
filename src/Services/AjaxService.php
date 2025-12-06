<?php

declare(strict_types=1);

namespace BigDump\Services;

use BigDump\Config\Config;
use BigDump\Models\ImportSession;

/**
 * AjaxService Class - Service for AJAX responses.
 *
 * This service generates XML and JavaScript responses
 * for the AJAX import mode.
 *
 * @package BigDump\Services
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class AjaxService
{
    /**
     * Configuration.
     * @var Config
     */
    private Config $config;

    /**
     * Constructor.
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Generates an XML response for AJAX.
     *
     * @param ImportSession $session Import session
     * @return string Formatted XML
     */
    public function createXmlResponse(ImportSession $session): string
    {
        $stats = $session->getStatistics();
        $params = $session->getNextSessionParams();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<root>';

        // Data for next session calculations
        $xml .= $this->xmlElement('linenumber', (string) $params['start']);
        $xml .= $this->xmlElement('foffset', (string) $params['foffset']);
        $xml .= $this->xmlElement('fn', $params['fn']);
        $xml .= $this->xmlElement('totalqueries', (string) $params['totalqueries']);
        $xml .= $this->xmlElement('delimiter', $params['delimiter']);

        // Statistics for interface update
        // Lines
        $xml .= $this->xmlElement('elem1', (string) $stats['lines_this']);
        $xml .= $this->xmlElement('elem2', (string) $stats['lines_done']);
        $xml .= $this->xmlElement('elem3', $this->formatNullable($stats['lines_togo']));
        $xml .= $this->xmlElement('elem4', $this->formatNullable($stats['lines_total']));

        // Queries
        $xml .= $this->xmlElement('elem5', (string) $stats['queries_this']);
        $xml .= $this->xmlElement('elem6', (string) $stats['queries_done']);
        $xml .= $this->xmlElement('elem7', $this->formatNullable($stats['queries_togo']));
        $xml .= $this->xmlElement('elem8', $this->formatNullable($stats['queries_total']));

        // Bytes
        $xml .= $this->xmlElement('elem9', (string) $stats['bytes_this']);
        $xml .= $this->xmlElement('elem10', (string) $stats['bytes_done']);
        $xml .= $this->xmlElement('elem11', $this->formatNullable($stats['bytes_togo']));
        $xml .= $this->xmlElement('elem12', $this->formatNullable($stats['bytes_total']));

        // KB
        $xml .= $this->xmlElement('elem13', (string) $stats['kb_this']);
        $xml .= $this->xmlElement('elem14', (string) $stats['kb_done']);
        $xml .= $this->xmlElement('elem15', $this->formatNullable($stats['kb_togo']));
        $xml .= $this->xmlElement('elem16', $this->formatNullable($stats['kb_total']));

        // MB
        $xml .= $this->xmlElement('elem17', (string) $stats['mb_this']);
        $xml .= $this->xmlElement('elem18', (string) $stats['mb_done']);
        $xml .= $this->xmlElement('elem19', $this->formatNullable($stats['mb_togo']));
        $xml .= $this->xmlElement('elem20', $this->formatNullable($stats['mb_total']));

        // Percentages
        $xml .= $this->xmlElement('elem21', $this->formatNullable($stats['pct_this']));
        $xml .= $this->xmlElement('elem22', $this->formatNullable($stats['pct_done']));
        $xml .= $this->xmlElement('elem23', $this->formatNullable($stats['pct_togo']));
        $xml .= $this->xmlElement('elem24', (string) $stats['pct_total']);

        // Progress bar
        $xml .= $this->xmlElement('elem_bar', $this->createProgressBar($stats));

        // Status
        $xml .= $this->xmlElement('finished', $stats['finished'] ? '1' : '0');

        // Possible error
        if ($session->hasError()) {
            $xml .= $this->xmlElement('error', $session->getError() ?? '');
        }

        $xml .= '</root>';

        return $xml;
    }

    /**
     * Creates an XML element.
     *
     * @param string $name Element name
     * @param string $value Value
     * @return string XML element
     */
    private function xmlElement(string $name, string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return "<{$name}>{$escaped}</{$name}>";
    }

    /**
     * Formats a nullable value.
     *
     * @param mixed $value Value
     * @return string Formatted value
     */
    private function formatNullable(mixed $value): string
    {
        return $value === null ? '?' : (string) $value;
    }

    /**
     * Creates the HTML progress bar.
     *
     * @param array<string, mixed> $stats Statistics
     * @return string Bar HTML
     */
    private function createProgressBar(array $stats): string
    {
        if ($stats['gzip_mode']) {
            return '<span style="font-family:monospace;">[Not available for gzipped files]</span>';
        }

        $pct = $stats['pct_done'] ?? 0;

        return '<div style="height:15px;width:' . $pct . '%;background-color:#000080;margin:0;"></div>';
    }

    /**
     * Generates the initial AJAX JavaScript script.
     *
     * @param ImportSession $session Import session
     * @param string $scriptUri Script URI
     * @return string JavaScript code
     */
    public function createAjaxScript(ImportSession $session, string $scriptUri): string
    {
        $params = $session->getNextSessionParams();
        $delay = $this->config->get('delaypersession', 0);

        // Escape values for safe JavaScript string embedding (prevents XSS)
        $fn = $this->escapeJsString($params['fn']);
        $delimiter = $this->escapeJsString($params['delimiter']);
        $safeScriptUri = $this->escapeJsString($scriptUri);

        $js = <<<JAVASCRIPT
<script type="text/javascript">
(function() {
    'use strict';

    var scriptUri = '{$safeScriptUri}';
    var delayPerSession = {$delay};
    var httpRequest = null;

    /**
     * Builds the URL for the next AJAX session.
     */
    function buildUrl(linenumber, fn, foffset, totalqueries, delimiter) {
        return scriptUri + '?start=' + linenumber +
            '&fn=' + encodeURIComponent(fn) +
            '&foffset=' + foffset +
            '&totalqueries=' + totalqueries +
            '&delimiter=' + encodeURIComponent(delimiter) +
            '&ajaxrequest=true';
    }

    /**
     * Extracts a value from an XML element.
     */
    function getXmlValue(xml, tagName) {
        var elem = xml.getElementsByTagName(tagName);
        if (elem && elem[0] && elem[0].firstChild) {
            return elem[0].firstChild.nodeValue;
        }
        return '';
    }

    /**
     * Performs an AJAX request.
     */
    function makeRequest(url) {
        if (window.XMLHttpRequest) {
            httpRequest = new XMLHttpRequest();
        } else if (window.ActiveXObject) {
            try {
                httpRequest = new ActiveXObject('Msxml2.XMLHTTP');
            } catch (e) {
                try {
                    httpRequest = new ActiveXObject('Microsoft.XMLHTTP');
                } catch (e2) {
                    httpRequest = null;
                }
            }
        }

        if (!httpRequest) {
            alert('Cannot create XMLHttpRequest');
            return;
        }

        httpRequest.onreadystatechange = handleResponse;
        httpRequest.open('GET', url, true);
        httpRequest.send(null);
    }

    /**
     * Handles the AJAX response
     */
    function handleResponse() {
        if (httpRequest.readyState !== 4) {
            return;
        }

        if (httpRequest.status !== 200) {
            alert('Server error: ' + httpRequest.status);
            return;
        }

        var xml = httpRequest.responseXML;

        // If no valid XML, it's the final HTML page
        if (!xml || !xml.getElementsByTagName('root').length) {
            document.open();
            document.write(httpRequest.responseText);
            document.close();
            return;
        }

        // Check if finished
        var finished = getXmlValue(xml, 'finished');
        if (finished === '1') {
            // Reload to display the completion message
            location.reload();
            return;
        }

        // Check for errors
        var error = getXmlValue(xml, 'error');
        if (error) {
            alert('Import error: ' + error);
            location.reload();
            return;
        }

        // Update line number
        var paragraphs = document.getElementsByTagName('p');
        if (paragraphs[1]) {
            paragraphs[1].innerHTML = 'Starting from line: ' + getXmlValue(xml, 'linenumber');
        }

        // Update statistics table
        var cells = document.getElementsByTagName('td');
        for (var i = 1; i <= 24; i++) {
            if (cells[i]) {
                var value = getXmlValue(xml, 'elem' + i);
                if (cells[i].firstChild) {
                    cells[i].firstChild.nodeValue = value;
                } else {
                    cells[i].textContent = value;
                }
            }
        }

        // Update progress bar
        if (cells[25]) {
            cells[25].innerHTML = getXmlValue(xml, 'elem_bar');
        }

        // Prepare the next request
        var nextUrl = buildUrl(
            getXmlValue(xml, 'linenumber'),
            getXmlValue(xml, 'fn'),
            getXmlValue(xml, 'foffset'),
            getXmlValue(xml, 'totalqueries'),
            getXmlValue(xml, 'delimiter')
        );

        // Launch the next session after the delay
        setTimeout(function() {
            makeRequest(nextUrl);
        }, 500 + delayPerSession);
    }

    // Start the first AJAX request
    var initialUrl = buildUrl(
        {$params['start']},
        '{$fn}',
        {$params['foffset']},
        {$params['totalqueries']},
        '{$delimiter}'
    );

    setTimeout(function() {
        makeRequest(initialUrl);
    }, 500 + delayPerSession);
})();
</script>
JAVASCRIPT;

        return $js;
    }

    /**
     * Generates the automatic redirect script (non-AJAX mode).
     *
     * @param ImportSession $session Import session
     * @param string $scriptUri Script URI
     * @return string JavaScript code
     */
    public function createRedirectScript(ImportSession $session, string $scriptUri): string
    {
        $params = $session->getNextSessionParams();
        $delay = $this->config->get('delaypersession', 0);

        $url = $scriptUri . '?' . http_build_query($params);
        $safeUrl = $this->escapeJsString($url);

        return <<<JAVASCRIPT
<script type="text/javascript">
    window.setTimeout(function() {
        location.href = '{$safeUrl}';
    }, 500 + {$delay});
</script>
JAVASCRIPT;
    }

    /**
     * Checks if AJAX mode is enabled.
     *
     * @return bool True if AJAX enabled
     */
    public function isAjaxEnabled(): bool
    {
        return $this->config->get('ajax', true);
    }

    /**
     * Retrieves the delay between sessions.
     *
     * @return int Delay in milliseconds
     */
    public function getDelay(): int
    {
        return $this->config->get('delaypersession', 0);
    }

    /**
     * Escapes a string for safe use in JavaScript.
     *
     * Prevents XSS injections by escaping special characters,
     * including sequences that could terminate a script block.
     *
     * @param string $string String to escape
     * @return string JavaScript-safe string
     */
    private function escapeJsString(string $string): string
    {
        // Escape control characters, quotes and backslashes
        $escaped = addcslashes($string, "\0..\37\"'\\");

        // Escape sequences that could break out of script context
        // </script> could terminate the script block prematurely
        $escaped = str_replace(['</', '<!--', '-->'], ['<\\/', '<\\!--', '--\\>'], $escaped);

        return $escaped;
    }
}
