<?php
/**
 * View: Error page
 *
 * Displays a generic error message with contextual help.
 *
 * @var \BigDump\Core\View $view
 * @var string $error Error message
 */

$errorMessage = $error ?? 'An unknown error occurred';
$isNoFilename = stripos($errorMessage, 'No filename specified') !== false;
$isFileNotFound = stripos($errorMessage, 'File not found') !== false;
?>

<div class="alert alert-error">
    <strong>Error</strong><br>
    <?= $view->e($errorMessage) ?>
</div>

<?php if ($isNoFilename): ?>
<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6 mb-6">
    <div class="flex items-center gap-3 mb-4">
        <svg class="w-6 h-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 01.67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 11-.671-1.34l.041-.022zM12 9a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/>
        </svg>
        <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">Why does this happen?</h3>
    </div>
    <div class="text-sm text-blue-700 dark:text-blue-300 space-y-3">
        <p>This error occurs when:</p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li><strong>Session expired</strong> - Your PHP session timed out during a long import</li>
            <li><strong>Direct URL access</strong> - You navigated directly to the import page</li>
            <li><strong>Page refresh</strong> - You refreshed the page after the import completed</li>
            <li><strong>Browser back button</strong> - You used browser navigation after completion</li>
        </ul>

        <h4 class="font-semibold text-blue-800 dark:text-blue-200 mt-4 mb-2">How to fix it:</h4>
        <ol class="list-decimal list-inside space-y-1 ml-2">
            <li>Click <strong>"Back to Home"</strong> below</li>
            <li>Select your SQL dump file from the file list</li>
            <li>Click <strong>"Start Import"</strong> to begin</li>
            <li>Wait for the import to complete without refreshing the page</li>
        </ol>

        <p class="bg-blue-100 dark:bg-blue-800/30 px-4 py-3 rounded-lg mt-4">
            <strong>Tip:</strong> If your session expired during an import, don't worry!
            Simply restart the import from the home page - it will resume from where it left off.
        </p>
    </div>
</div>
<?php endif; ?>

<?php if ($isFileNotFound): ?>
<div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl p-6 mb-6">
    <div class="flex items-center gap-3 mb-4">
        <svg class="w-6 h-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
            <path fill-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 01.67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 11-.671-1.34l.041-.022zM12 9a.75.75 0 100-1.5.75.75 0 000 1.5z" clip-rule="evenodd"/>
        </svg>
        <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">Why does this happen?</h3>
    </div>
    <div class="text-sm text-blue-700 dark:text-blue-300 space-y-3">
        <p>The SQL dump file was not found in the <code class="code">uploads/</code> directory. This can happen when:</p>
        <ul class="list-disc list-inside space-y-1 ml-2">
            <li><strong>File was deleted</strong> - Someone removed the file from the server</li>
            <li><strong>File was moved</strong> - The file was relocated to another directory</li>
            <li><strong>Upload failed</strong> - The file upload did not complete successfully</li>
            <li><strong>Wrong filename</strong> - The filename in URL doesn't match actual file</li>
        </ul>

        <h4 class="font-semibold text-blue-800 dark:text-blue-200 mt-4 mb-2">How to fix it:</h4>
        <ol class="list-decimal list-inside space-y-1 ml-2">
            <li>Click <strong>"Back to Home"</strong> below</li>
            <li>Check the file list to see available files</li>
            <li>Upload your SQL dump file again if needed</li>
            <li>Make sure the file has <code class="code">.sql</code>, <code class="code">.gz</code>, or <code class="code">.csv</code> extension</li>
        </ol>

        <p class="bg-blue-100 dark:bg-blue-800/30 px-4 py-3 rounded-lg mt-4">
            <strong>Tip:</strong> Check that the <code class="code">uploads/</code> directory has write permissions
            and enough disk space for your dump file.
        </p>
    </div>
</div>
<?php endif; ?>

<div class="text-center mt-3">
    <a href="<?= $view->e($scriptUri) ?>" class="btn btn-cyan">
        Back to Home
    </a>
</div>
