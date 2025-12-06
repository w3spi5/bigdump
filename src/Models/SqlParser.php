<?php

declare(strict_types=1);

namespace BigDump\Models;

use BigDump\Config\Config;

/**
 * Classe SqlParser - Analyseur de requêtes SQL
 *
 * Cette classe parse les lignes SQL pour extraire les requêtes complètes,
 * en gérant correctement:
 * - Les chaînes de caractères multi-lignes
 * - Les délimiteurs personnalisés (procédures stockées)
 * - Les commentaires SQL
 * - Les caractères d'échappement
 *
 * Corrections par rapport à l'original:
 * - Gestion correcte de \\\\ (double backslash) avant les quotes
 * - Détection de DELIMITER uniquement hors des chaînes
 * - Protection contre l'accumulation mémoire infinie
 *
 * @package BigDump\Models
 * @author  Refactorisation MVC
 * @version 2.0.0
 */
class SqlParser
{
    /**
     * Configuration
     * @var Config
     */
    private Config $config;

    /**
     * Délimiteur de requête actuel
     * @var string
     */
    private string $delimiter;

    /**
     * Caractère de quote des chaînes
     * @var string
     */
    private string $stringQuote;

    /**
     * Marqueurs de commentaires
     * @var array<int, string>
     */
    private array $commentMarkers;

    /**
     * Indique si on est dans une chaîne de caractères
     * @var bool
     */
    private bool $inString = false;

    /**
     * Requête en cours de construction
     * @var string
     */
    private string $currentQuery = '';

    /**
     * Nombre de lignes de la requête en cours
     * @var int
     */
    private int $queryLineCount = 0;

    /**
     * Nombre maximum de lignes par requête
     * @var int
     */
    private int $maxQueryLines;

    /**
     * Taille mémoire maximale pour une requête
     * @var int
     */
    private int $maxQueryMemory;

    /**
     * Constructeur
     *
     * @param Config $config Configuration
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->delimiter = $config->get('delimiter', ';');
        $this->stringQuote = $config->get('string_quotes', "'");
        $this->commentMarkers = $config->get('comment_markers', ['#', '-- ', '/*!']);
        $this->maxQueryLines = $config->get('max_query_lines', 300);
        $this->maxQueryMemory = $config->get('max_query_memory', 10485760);
    }

    /**
     * Réinitialise l'état du parser
     *
     * @return void
     */
    public function reset(): void
    {
        $this->inString = false;
        $this->currentQuery = '';
        $this->queryLineCount = 0;
    }

    /**
     * Définit le délimiteur de requête
     *
     * @param string $delimiter Nouveau délimiteur
     * @return void
     */
    public function setDelimiter(string $delimiter): void
    {
        $this->delimiter = $delimiter;
    }

    /**
     * Récupère le délimiteur actuel
     *
     * @return string Délimiteur
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Parse une ligne et retourne une requête complète si disponible
     *
     * @param string $line Ligne à parser
     * @return array{query: string|null, error: string|null, delimiter_changed: bool} Résultat du parsing
     */
    public function parseLine(string $line): array
    {
        $result = [
            'query' => null,
            'error' => null,
            'delimiter_changed' => false,
        ];

        // Normaliser les fins de ligne
        $line = str_replace(["\r\n", "\r"], "\n", $line);

        // Détecter les commandes DELIMITER (seulement si pas dans une chaîne)
        if (!$this->inString && $this->isDelimiterCommand($line)) {
            $newDelimiter = $this->extractDelimiter($line);

            if ($newDelimiter !== null) {
                $this->delimiter = $newDelimiter;
                $result['delimiter_changed'] = true;
            }

            return $result;
        }

        // Ignorer les commentaires et lignes vides (seulement si pas dans une chaîne)
        if (!$this->inString && $this->isCommentOrEmpty($line)) {
            return $result;
        }

        // Vérifier la limite de mémoire
        if (strlen($this->currentQuery) + strlen($line) > $this->maxQueryMemory) {
            $result['error'] = "Query exceeds maximum memory limit ({$this->maxQueryMemory} bytes)";
            $this->reset();
            return $result;
        }

        // Analyser les quotes pour savoir si on entre/sort d'une chaîne
        $this->analyzeQuotes($line);

        // Ajouter la ligne à la requête en cours
        $this->currentQuery .= $line;

        // Compter les lignes seulement si pas dans une chaîne
        if (!$this->inString) {
            $this->queryLineCount++;
        }

        // Vérifier la limite de lignes
        if ($this->queryLineCount > $this->maxQueryLines) {
            $result['error'] = "Query exceeds maximum line count ({$this->maxQueryLines} lines). " .
                "This may indicate extended inserts or a very long procedure. " .
                "Increase max_query_lines in config if this is expected.";
            $this->reset();
            return $result;
        }

        // Vérifier si la requête est complète (délimiteur à la fin, hors chaîne)
        if (!$this->inString && $this->isQueryComplete()) {
            $query = $this->extractQuery();
            $this->reset();

            if (!empty(trim($query))) {
                $result['query'] = $query;
            }
        }

        return $result;
    }

    /**
     * Vérifie si une ligne est une commande DELIMITER
     *
     * @param string $line Ligne à vérifier
     * @return bool True si c'est une commande DELIMITER
     */
    private function isDelimiterCommand(string $line): bool
    {
        $trimmed = ltrim($line);
        return stripos($trimmed, 'DELIMITER ') === 0 || strcasecmp(trim($trimmed), 'DELIMITER') === 0;
    }

    /**
     * Extrait le nouveau délimiteur d'une commande DELIMITER
     *
     * @param string $line Ligne contenant la commande
     * @return string|null Nouveau délimiteur ou null
     */
    private function extractDelimiter(string $line): ?string
    {
        $trimmed = trim($line);

        // Format: DELIMITER xxx
        if (preg_match('/^DELIMITER\s+(.+)$/i', $trimmed, $matches)) {
            $delimiter = trim($matches[1]);

            if (!empty($delimiter)) {
                return $delimiter;
            }
        }

        return null;
    }

    /**
     * Vérifie si une ligne est un commentaire ou vide
     *
     * @param string $line Ligne à vérifier
     * @return bool True si commentaire ou vide
     */
    private function isCommentOrEmpty(string $line): bool
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return true;
        }

        foreach ($this->commentMarkers as $marker) {
            if (str_starts_with($trimmed, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Analyse les quotes dans une ligne pour déterminer l'état in-string
     *
     * Cette méthode corrige le bug de l'original qui ne gérait pas
     * correctement les doubles backslashes (\\) avant les quotes.
     * Elle gère également les quotes doublées comme mécanisme d'échappement SQL
     * (ex: 'It''s OK' représente la chaîne "It's OK").
     *
     * @param string $line Ligne à analyser
     * @return void
     */
    private function analyzeQuotes(string $line): void
    {
        $length = strlen($line);
        $i = 0;

        while ($i < $length) {
            $char = $line[$i];

            if ($this->inString) {
                // Dans une chaîne, chercher la fin
                if ($char === $this->stringQuote) {
                    // Vérifier si c'est une quote doublée (échappement SQL: '' -> ')
                    if ($i + 1 < $length && $line[$i + 1] === $this->stringQuote) {
                        // Quote doublée, sauter les deux caractères, reste dans la chaîne
                        $i += 2;
                        continue;
                    }

                    // Compter les backslashes précédents
                    $backslashes = 0;
                    $j = $i - 1;

                    while ($j >= 0 && $line[$j] === '\\') {
                        $backslashes++;
                        $j--;
                    }

                    // Si nombre pair de backslashes (ou zéro), la quote ferme la chaîne
                    // Si nombre impair, la quote est échappée
                    if ($backslashes % 2 === 0) {
                        $this->inString = false;
                    }
                }
            } else {
                // Hors chaîne, chercher le début
                if ($char === $this->stringQuote) {
                    $this->inString = true;
                }
            }

            $i++;
        }
    }

    /**
     * Vérifie si la requête en cours est complète
     *
     * @return bool True si la requête est complète
     */
    private function isQueryComplete(): bool
    {
        if ($this->delimiter === '') {
            return true;
        }

        $trimmed = rtrim($this->currentQuery);

        return str_ends_with($trimmed, $this->delimiter);
    }

    /**
     * Extrait la requête complète (sans le délimiteur final)
     *
     * @return string Requête extraite
     */
    private function extractQuery(): string
    {
        $query = $this->currentQuery;

        // Supprimer le délimiteur final
        if ($this->delimiter !== '') {
            $query = rtrim($query);
            $delimiterLength = strlen($this->delimiter);

            if (str_ends_with($query, $this->delimiter)) {
                $query = substr($query, 0, -$delimiterLength);
            }
        }

        return trim($query);
    }

    /**
     * Retourne une requête en attente incomplète
     *
     * Utilisé à la fin du fichier pour récupérer une éventuelle
     * requête non terminée par un délimiteur.
     *
     * @return string|null Requête ou null si vide
     */
    public function getPendingQuery(): ?string
    {
        $query = trim($this->currentQuery);

        if (empty($query)) {
            return null;
        }

        // Supprimer un éventuel délimiteur final
        if ($this->delimiter !== '' && str_ends_with($query, $this->delimiter)) {
            $query = substr($query, 0, -strlen($this->delimiter));
            $query = trim($query);
        }

        return empty($query) ? null : $query;
    }

    /**
     * Vérifie si le parser est dans une chaîne
     *
     * @return bool True si dans une chaîne
     */
    public function isInString(): bool
    {
        return $this->inString;
    }

    /**
     * Récupère le nombre de lignes de la requête en cours
     *
     * @return int Nombre de lignes
     */
    public function getQueryLineCount(): int
    {
        return $this->queryLineCount;
    }

    /**
     * Convertit une ligne CSV en requête INSERT
     *
     * @param string $line Ligne CSV
     * @param string $table Table de destination
     * @return string Requête INSERT
     */
    public function csvToInsert(string $line, string $table): string
    {
        // Validate table name to prevent SQL injection
        // MySQL table names can contain letters, digits, underscores, and dollar signs
        $safeTable = preg_replace('/[^a-zA-Z0-9_$]/', '', $table);
        if ($safeTable !== $table || empty($safeTable)) {
            throw new \RuntimeException("Invalid table name: {$table}");
        }

        $csvConfig = $this->config->getCsv();
        $delimiter = $csvConfig['delimiter'];
        $enclosure = $csvConfig['enclosure'];
        $addQuotes = $csvConfig['add_quotes'];
        $addSlashes = $csvConfig['add_slashes'];

        // Parser la ligne CSV correctement (gère les champs avec délimiteurs)
        $fields = $this->parseCsvLine($line, $delimiter, $enclosure);

        // Préparer les valeurs
        $values = [];

        foreach ($fields as $field) {
            if ($addSlashes) {
                $field = addslashes($field);
            }

            if ($addQuotes) {
                $values[] = "'" . $field . "'";
            } else {
                $values[] = $field;
            }
        }

        return "INSERT INTO `{$safeTable}` VALUES (" . implode(',', $values) . ")";
    }

    /**
     * Parse une ligne CSV correctement
     *
     * Gère les cas où le délimiteur apparaît dans un champ encadré.
     *
     * @param string $line Ligne CSV
     * @param string $delimiter Délimiteur
     * @param string $enclosure Caractère d'encadrement
     * @return array<int, string> Champs parsés
     */
    private function parseCsvLine(string $line, string $delimiter, string $enclosure): array
    {
        // Supprimer les fins de ligne
        $line = rtrim($line, "\r\n");

        // Utiliser str_getcsv pour un parsing correct
        // Note: str_getcsv retourne toujours un tableau (jamais false)
        return str_getcsv($line, $delimiter, $enclosure);
    }

    /**
     * Vérifie si une ligne est une ligne CSV valide
     *
     * @param string $line Ligne à vérifier
     * @return bool True si CSV valide
     */
    public function isValidCsvLine(string $line): bool
    {
        $trimmed = trim($line);

        // Ignorer les lignes vides
        if ($trimmed === '') {
            return false;
        }

        // Ignorer les lignes de commentaires CSV
        if (str_starts_with($trimmed, '#')) {
            return false;
        }

        return true;
    }
}
