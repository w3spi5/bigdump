<?php

declare(strict_types=1);

namespace BigDump\Services;

use BigDump\Config\Config;
use BigDump\Models\ImportSession;

/**
 * Classe AjaxService - Service pour les réponses AJAX
 *
 * Ce service génère les réponses XML et JavaScript
 * pour le mode AJAX de l'import.
 *
 * @package BigDump\Services
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class AjaxService
{
    /**
     * Configuration
     * @var Config
     */
    private Config $config;

    /**
     * Constructeur
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Génère une réponse XML pour AJAX
     *
     * @param ImportSession $session Session d'import
     * @return string XML formaté
     */
    public function createXmlResponse(ImportSession $session): string
    {
        $stats = $session->getStatistics();
        $params = $session->getNextSessionParams();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<root>';

        // Données pour les calculs de la prochaine session
        $xml .= $this->xmlElement('linenumber', (string) $params['start']);
        $xml .= $this->xmlElement('foffset', (string) $params['foffset']);
        $xml .= $this->xmlElement('fn', $params['fn']);
        $xml .= $this->xmlElement('totalqueries', (string) $params['totalqueries']);
        $xml .= $this->xmlElement('delimiter', $params['delimiter']);

        // Statistiques pour la mise à jour de l'interface
        // Lignes
        $xml .= $this->xmlElement('elem1', (string) $stats['lines_this']);
        $xml .= $this->xmlElement('elem2', (string) $stats['lines_done']);
        $xml .= $this->xmlElement('elem3', $this->formatNullable($stats['lines_togo']));
        $xml .= $this->xmlElement('elem4', $this->formatNullable($stats['lines_total']));

        // Requêtes
        $xml .= $this->xmlElement('elem5', (string) $stats['queries_this']);
        $xml .= $this->xmlElement('elem6', (string) $stats['queries_done']);
        $xml .= $this->xmlElement('elem7', $this->formatNullable($stats['queries_togo']));
        $xml .= $this->xmlElement('elem8', $this->formatNullable($stats['queries_total']));

        // Octets
        $xml .= $this->xmlElement('elem9', (string) $stats['bytes_this']);
        $xml .= $this->xmlElement('elem10', (string) $stats['bytes_done']);
        $xml .= $this->xmlElement('elem11', $this->formatNullable($stats['bytes_togo']));
        $xml .= $this->xmlElement('elem12', $this->formatNullable($stats['bytes_total']));

        // Ko
        $xml .= $this->xmlElement('elem13', (string) $stats['kb_this']);
        $xml .= $this->xmlElement('elem14', (string) $stats['kb_done']);
        $xml .= $this->xmlElement('elem15', $this->formatNullable($stats['kb_togo']));
        $xml .= $this->xmlElement('elem16', $this->formatNullable($stats['kb_total']));

        // Mo
        $xml .= $this->xmlElement('elem17', (string) $stats['mb_this']);
        $xml .= $this->xmlElement('elem18', (string) $stats['mb_done']);
        $xml .= $this->xmlElement('elem19', $this->formatNullable($stats['mb_togo']));
        $xml .= $this->xmlElement('elem20', $this->formatNullable($stats['mb_total']));

        // Pourcentages
        $xml .= $this->xmlElement('elem21', $this->formatNullable($stats['pct_this']));
        $xml .= $this->xmlElement('elem22', $this->formatNullable($stats['pct_done']));
        $xml .= $this->xmlElement('elem23', $this->formatNullable($stats['pct_togo']));
        $xml .= $this->xmlElement('elem24', (string) $stats['pct_total']);

        // Barre de progression
        $xml .= $this->xmlElement('elem_bar', $this->createProgressBar($stats));

        // État
        $xml .= $this->xmlElement('finished', $stats['finished'] ? '1' : '0');

        // Erreur éventuelle
        if ($session->hasError()) {
            $xml .= $this->xmlElement('error', $session->getError() ?? '');
        }

        $xml .= '</root>';

        return $xml;
    }

    /**
     * Crée un élément XML
     *
     * @param string $name Nom de l'élément
     * @param string $value Valeur
     * @return string Élément XML
     */
    private function xmlElement(string $name, string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        return "<{$name}>{$escaped}</{$name}>";
    }

    /**
     * Formate une valeur nullable
     *
     * @param mixed $value Valeur
     * @return string Valeur formatée
     */
    private function formatNullable(mixed $value): string
    {
        return $value === null ? '?' : (string) $value;
    }

    /**
     * Crée la barre de progression HTML
     *
     * @param array<string, mixed> $stats Statistiques
     * @return string HTML de la barre
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
     * Génère le script JavaScript AJAX initial
     *
     * @param ImportSession $session Session d'import
     * @param string $scriptUri URI du script
     * @return string Code JavaScript
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
     * Construit l'URL pour la prochaine session AJAX
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
     * Extrait une valeur d'un élément XML
     */
    function getXmlValue(xml, tagName) {
        var elem = xml.getElementsByTagName(tagName);
        if (elem && elem[0] && elem[0].firstChild) {
            return elem[0].firstChild.nodeValue;
        }
        return '';
    }

    /**
     * Effectue une requête AJAX
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
     * Gère la réponse AJAX
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

        // Si pas de XML valide, c'est la page finale HTML
        if (!xml || !xml.getElementsByTagName('root').length) {
            document.open();
            document.write(httpRequest.responseText);
            document.close();
            return;
        }

        // Vérifier si terminé
        var finished = getXmlValue(xml, 'finished');
        if (finished === '1') {
            // Recharger pour afficher le message de fin
            location.reload();
            return;
        }

        // Vérifier les erreurs
        var error = getXmlValue(xml, 'error');
        if (error) {
            alert('Import error: ' + error);
            location.reload();
            return;
        }

        // Mettre à jour le numéro de ligne
        var paragraphs = document.getElementsByTagName('p');
        if (paragraphs[1]) {
            paragraphs[1].innerHTML = 'Starting from line: ' + getXmlValue(xml, 'linenumber');
        }

        // Mettre à jour le tableau de statistiques
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

        // Mettre à jour la barre de progression
        if (cells[25]) {
            cells[25].innerHTML = getXmlValue(xml, 'elem_bar');
        }

        // Préparer la prochaine requête
        var nextUrl = buildUrl(
            getXmlValue(xml, 'linenumber'),
            getXmlValue(xml, 'fn'),
            getXmlValue(xml, 'foffset'),
            getXmlValue(xml, 'totalqueries'),
            getXmlValue(xml, 'delimiter')
        );

        // Lancer la prochaine session après le délai
        setTimeout(function() {
            makeRequest(nextUrl);
        }, 500 + delayPerSession);
    }

    // Démarrer la première requête AJAX
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
     * Génère le script de redirection automatique (mode non-AJAX)
     *
     * @param ImportSession $session Session d'import
     * @param string $scriptUri URI du script
     * @return string Code JavaScript
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
     * Vérifie si le mode AJAX est activé
     *
     * @return bool True si AJAX activé
     */
    public function isAjaxEnabled(): bool
    {
        return $this->config->get('ajax', true);
    }

    /**
     * Récupère le délai entre les sessions
     *
     * @return int Délai en millisecondes
     */
    public function getDelay(): int
    {
        return $this->config->get('delaypersession', 0);
    }

    /**
     * Échappe une chaîne pour une utilisation sûre dans du JavaScript
     *
     * Prévient les injections XSS en échappant les caractères spéciaux,
     * incluant les séquences qui pourraient terminer un bloc script.
     *
     * @param string $string Chaîne à échapper
     * @return string Chaîne sécurisée pour JavaScript
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
