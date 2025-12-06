<?php
/**
 * Vue: Page d'erreur
 *
 * Affiche un message d'erreur générique.
 *
 * @var \BigDump\Core\View $view
 * @var string $error Message d'erreur
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
