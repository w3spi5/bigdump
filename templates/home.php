<?php
/**
 * View: Home page
 *
 * Displays the list of available files and the upload form.
 *
 * @var \BigDump\Core\View $view
 * @var array $files List of files
 * @var bool $dbConfigured Database configured
 * @var array|null $connectionInfo Connection information
 * @var bool $uploadEnabled Upload enabled
 * @var int $uploadMaxSize Maximum upload size
 * @var string $uploadDir Upload directory
 * @var string $predefinedFile Predefined file
 * @var string $dbName Database name
 * @var string $dbServer Database server
 * @var bool $testMode Test mode
 */
?>

<?php if ($testMode): ?>
<div class="px-4 py-3 rounded-lg mb-4 text-sm bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700">
    <strong>Test Mode Enabled</strong> - Queries will be parsed but not executed.
</div>
<?php endif; ?>

<?php if (isset($uploadResult)): ?>
    <?php if ($uploadResult['success']): ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-300 dark:border-green-700"><?= $view->e($uploadResult['message']) ?></div>
    <?php else: ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-300 dark:border-red-700"><?= $view->e($uploadResult['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (isset($deleteResult)): ?>
    <?php if ($deleteResult['success']): ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200 border border-green-300 dark:border-green-700"><?= $view->e($deleteResult['message']) ?></div>
    <?php else: ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-300 dark:border-red-700"><?= $view->e($deleteResult['message']) ?></div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!$dbConfigured): ?>
<div class="px-4 py-3 rounded-lg mb-4 text-sm bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-300 dark:border-red-700">
    <strong>Database not configured!</strong><br>
    Please edit <code class="bg-gray-200 dark:bg-gray-700 px-1 py-0.5 rounded text-sm font-mono">config/config.php</code> and set your database credentials.
</div>
<?php elseif ($connectionInfo && !$connectionInfo['success']): ?>
<div class="px-4 py-3 rounded-lg mb-4 text-sm bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-200 border border-red-300 dark:border-red-700">
    <strong>Database connection failed!</strong><br>
    <?= $view->e($connectionInfo['message']) ?>
</div>
<?php elseif ($connectionInfo && $connectionInfo['success']): ?>
<div class="bg-cyan-600 dark:bg-cyan-800 text-white rounded-xl px-6 py-4 mb-6">
    Connected to <strong><?= $view->e($dbName) ?></strong> at <strong><?= $view->e($dbServer) ?></strong><br>
    Connection charset: <strong><?= $view->e($connectionInfo['charset']) ?></strong>
    <span class="text-cyan-100 dark:text-cyan-200">(Your dump file must use the same charset)</span>
</div>
<?php endif; ?>

<?php if (!empty($predefinedFile)): ?>
    <div class="bg-cyan-600 dark:bg-cyan-800 text-white rounded-xl px-6 py-4 mb-6">
        <strong>Predefined file:</strong> <?= $view->e($predefinedFile) ?><br>
        <form method="post" action="" style="display:inline">
            <input type="hidden" name="fn" value="<?= $view->e($predefinedFile) ?>">
            <button type="submit" class="px-4 py-2 rounded-md font-medium text-sm transition-colors cursor-pointer inline-block text-center no-underline bg-blue-600 hover:bg-blue-700 text-white mt-3">Start Import</button>
        </form>
    </div>
<?php else: ?>

    <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">Available Dump Files</h3>

    <?php if (empty($files)): ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700">
            No dump files found in <code class="bg-gray-200 dark:bg-gray-700 px-1 py-0.5 rounded text-sm font-mono"><?= $view->e($uploadDir) ?></code><br>
            Upload a .sql, .gz or .csv file using the form below, or via FTP.
        </div>
    <?php else: ?>
        <table class="w-full border-collapse bg-white dark:bg-gray-800 rounded-lg overflow-hidden shadow-sm">
            <thead class="bg-gray-50 dark:bg-gray-700">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Filename</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Size</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400 border-b-2 border-gray-200 dark:border-gray-600 text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><strong><?= $view->e($file['name']) ?></strong></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $view->formatBytes($file['size']) ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100"><?= $view->e($file['date']) ?></td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100">
                        <?php
                        $typeClass = match($file['type']) {
                            'SQL' => 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200',
                            'GZip' => 'bg-purple-100 dark:bg-purple-900/50 text-purple-800 dark:text-purple-200',
                            'CSV' => 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-200',
                            default => 'bg-blue-100 dark:bg-blue-900/50 text-blue-800 dark:text-blue-200'
                        };
                        ?>
                        <span class="px-2 py-1 rounded text-xs font-medium <?= $typeClass ?>"><?= $view->e($file['type']) ?></span>
                    </td>
                    <td class="px-4 py-3 border-b border-gray-200 dark:border-gray-700 text-gray-900 dark:text-gray-100 text-center">
                        <?php if ($dbConfigured && $connectionInfo && $connectionInfo['success']): ?>
                            <?php if ($file['type'] !== 'GZip' || function_exists('gzopen')): ?>
                                <form method="post" action="" style="display:inline">
                                    <input type="hidden" name="fn" value="<?= $view->e($file['name']) ?>">
                                    <button type="submit" class="px-4 py-2 rounded-md font-medium text-sm transition-colors cursor-pointer inline-block text-center no-underline bg-green-500 hover:bg-green-600 text-white">Import</button>
                                </form>
                            <?php else: ?>
                                <span class="text-gray-500 dark:text-gray-400">GZip not supported</span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <a href="<?= $view->url(['delete' => $file['name']]) ?>"
                           class="px-4 py-2 rounded-md font-medium text-sm transition-colors cursor-pointer inline-block text-center no-underline bg-red-500 hover:bg-red-600 text-white"
                           onclick="return confirm('Delete <?= $view->escapeJs($file['name']) ?>?')">
                            Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr class="border-t border-gray-200 dark:border-gray-700 my-6">

    <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-gray-100">Upload Dump Files</h3>

    <?php if (!$uploadEnabled): ?>
        <div class="px-4 py-3 rounded-lg mb-4 text-sm bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border border-amber-300 dark:border-amber-700">
            Upload disabled. Directory <code class="bg-gray-200 dark:bg-gray-700 px-1 py-0.5 rounded text-sm font-mono"><?= $view->e($uploadDir) ?></code> is not writable.<br>
            Set permissions to 755 or 777, or upload files via FTP.
        </div>
    <?php else: ?>
        <p class="text-gray-500 dark:text-gray-400 mb-3">
            Maximum file size: <strong><?= $view->formatBytes($uploadMaxSize) ?></strong> &bull;
            Allowed types: <strong>.sql, .gz, .csv</strong><br>
            For larger files, use FTP to upload directly to <code class="bg-gray-200 dark:bg-gray-700 px-1 py-0.5 rounded text-sm font-mono"><?= $view->e($uploadDir) ?></code>
        </p>

        <div class="file-upload" id="fileUpload" data-max-file-size="<?= $uploadMaxSize ?>" data-upload-url="<?= $view->e($scriptUri) ?>">
            <!-- Dropzone -->
            <div class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl p-8 text-center cursor-pointer hover:border-blue-500 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors bg-gray-50 dark:bg-gray-800/50" id="dropzone">
                <div class="file-upload__icon">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="text-gray-700 dark:text-gray-300 mb-2">
                    <strong>Click to upload</strong> or drag and drop
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    SQL, GZip or CSV files up to <?= $view->formatBytes($uploadMaxSize) ?>
                </div>
                <input type="file"
                       class="hidden"
                       id="fileInput"
                       accept=".sql,.gz,.csv"
                       multiple>
            </div>

            <!-- File List -->
            <div class="mt-4 space-y-2" id="fileList"></div>

            <!-- Actions -->
            <div class="mt-4 flex gap-3" id="uploadActions" style="display: none;">
                <button type="button" class="px-4 py-2 rounded-md font-medium text-sm transition-colors cursor-pointer inline-block text-center no-underline bg-blue-600 hover:bg-blue-700 text-white" id="uploadBtn">
                    Upload All Files
                </button>
                <button type="button" class="px-4 py-2 rounded-md font-medium text-sm transition-colors cursor-pointer inline-block text-center no-underline bg-gray-500 hover:bg-gray-600 text-white" id="clearBtn">
                    Clear All
                </button>
            </div>
        </div>

    <?php endif; ?>

<?php endif; ?>

<!-- Loading Overlay for Import -->
<div class="fixed inset-0 bg-black/50 items-center justify-center z-50 hidden" id="loadingOverlay">
    <div class="bg-white dark:bg-gray-800 rounded-xl p-8 text-center shadow-xl">
        <div class="w-12 h-12 border-4 border-blue-200 border-t-blue-600 rounded-full animate-spin mx-auto mb-4"></div>
        <div class="text-lg font-medium text-gray-900 dark:text-gray-100">Preparing import...</div>
        <div class="text-sm text-gray-500 dark:text-gray-400 mt-2">Loading file</div>
    </div>
</div>
