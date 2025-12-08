<?php
/**
 * View: Error page
 *
 * Displays a generic error message.
 *
 * @var \BigDump\Core\View $view
 * @var string $error Error message
 */
?>

<div class="alert alert-error">
    <strong>Error</strong><br>
    <?= $view->e($error ?? 'An unknown error occurred') ?>
</div>

<div class="text-center mt-3">
    <a href="<?= $view->e($scriptUri) ?>" class="btn btn-primary">
        Back to Home
    </a>
</div>
