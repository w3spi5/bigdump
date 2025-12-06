<?php
/**
 * Vue: Page d'accueil
 *
 * Affiche la liste des fichiers disponibles et le formulaire d'upload.
 *
 * @var \BigDump\Core\View $view
 * @var array $files Liste des fichiers
 * @var bool $dbConfigured Base de données configurée
 * @var array|null $connectionInfo Informations de connexion
 * @var bool $uploadEnabled Upload activé
 * @var int $uploadMaxSize Taille maximale d'upload
 * @var string $uploadDir Répertoire d'upload
 * @var string $predefinedFile Fichier prédéfini
 * @var string $dbName Nom de la base de données
 * @var string $dbServer Serveur de base de données
 * @var bool $testMode Mode test
 */
?>

<?php if ($testMode): ?>
<div class="alert alert-warning">
    <strong>Test Mode Enabled</strong> - Queries will be parsed but not executed.
</div>
<?php endif; ?>

<?php if (isset($uploadResult)): ?>
    <?php if ($uploadResult['success']): ?>
        <div class="alert alert-success"><?= $view->e($uploadResult['message']) ?></div>
    <?php else: ?>
        <div class="alert alert-error"><?= $view->e($uploadResult['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($deleteResult)): ?>
    <?php if ($deleteResult['success']): ?>
        <div class="alert alert-success"><?= $view->e($deleteResult['message']) ?></div>
    <?php else: ?>
        <div class="alert alert-error"><?= $view->e($deleteResult['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!$dbConfigured): ?>
<div class="alert alert-error">
    <strong>Database not configured!</strong><br>
    Please edit <code>config/config.php</code> and set your database credentials.
</div>
<?php elseif ($connectionInfo && !$connectionInfo['success']): ?>
<div class="alert alert-error">
    <strong>Database connection failed!</strong><br>
    <?= $view->e($connectionInfo['message']) ?>
</div>
<?php elseif ($connectionInfo && $connectionInfo['success']): ?>
<div class="alert alert-info">
    Connected to <strong><?= $view->e($dbName) ?></strong> at <strong><?= $view->e($dbServer) ?></strong><br>
    Connection charset: <strong><?= $view->e($connectionInfo['charset']) ?></strong>
    <span class="text-muted">(Your dump file must use the same charset)</span>
</div>
<?php endif; ?>

<?php if (!empty($predefinedFile)): ?>
    <div class="alert alert-info">
        <strong>Predefined file:</strong> <?= $view->e($predefinedFile) ?><br>
        <a href="<?= $view->url(['start' => 1, 'fn' => $predefinedFile, 'foffset' => 0, 'totalqueries' => 0]) ?>" class="btn btn-primary mt-3">
            Start Import
        </a>
    </div>
<?php else: ?>

    <h3 class="mb-3">Available Dump Files</h3>

    <?php if (empty($files)): ?>
        <div class="alert alert-warning">
            No dump files found in <code><?= $view->e($uploadDir) ?></code><br>
            Upload a .sql, .gz or .csv file using the form below, or via FTP.
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Date</th>
                    <th>Type</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                <tr>
                    <td><strong><?= $view->e($file['name']) ?></strong></td>
                    <td><?= $view->formatBytes($file['size']) ?></td>
                    <td><?= $view->e($file['date']) ?></td>
                    <td>
                        <?php
                        $typeClass = match($file['type']) {
                            'SQL' => 'sql',
                            'GZip' => 'gz',
                            'CSV' => 'csv',
                            default => 'sql'
                        };
                        ?>
                        <span class="file-type file-type-<?= $typeClass ?>"><?= $view->e($file['type']) ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($dbConfigured && $connectionInfo && $connectionInfo['success']): ?>
                            <?php if ($file['type'] !== 'GZip' || function_exists('gzopen')): ?>
                                <a href="<?= $view->url(['start' => 1, 'fn' => $file['name'], 'foffset' => 0, 'totalqueries' => 0]) ?>"
                                   class="btn btn-primary">
                                    Import
                                </a>
                            <?php else: ?>
                                <span class="text-muted">GZip not supported</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="<?= $view->url(['delete' => $file['name']]) ?>"
                           class="btn btn-danger"
                           onclick="return confirm('Delete <?= $view->escapeJs($file['name']) ?>?')">
                            Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr class="mt-3 mb-3" style="border: none; border-top: 1px solid #e2e8f0;">

    <h3 class="mb-3">Upload Dump File</h3>

    <?php if (!$uploadEnabled): ?>
        <div class="alert alert-warning">
            Upload disabled. Directory <code><?= $view->e($uploadDir) ?></code> is not writable.<br>
            Set permissions to 755 or 777, or upload files via FTP.
        </div>
    <?php else: ?>
        <p class="text-muted mb-3">
            Maximum file size: <strong><?= $view->formatBytes($uploadMaxSize) ?></strong><br>
            For larger files, use FTP to upload directly to <code><?= $view->e($uploadDir) ?></code>
        </p>

        <form method="POST" action="<?= $view->e($scriptUri) ?>" enctype="multipart/form-data">
            <input type="hidden" name="MAX_FILE_SIZE" value="<?= $uploadMaxSize ?>">

            <div class="form-group">
                <label for="dumpfile">Select dump file (.sql, .gz, .csv)</label>
                <input type="file"
                       name="dumpfile"
                       id="dumpfile"
                       class="form-control"
                       accept=".sql,.gz,.csv"
                       required>
            </div>

            <button type="submit" name="uploadbutton" value="1" class="btn btn-primary">
                Upload File
            </button>
        </form>
    <?php endif; ?>

<?php endif; ?>
