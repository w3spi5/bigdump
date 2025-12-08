<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="-1">
    <meta name="robots" content="noindex, nofollow">
    <title>BigDump v<?= $view->e($version) ?> - MySQL Dump Importer</title>
    <link rel="stylesheet" href="assets/css/bigdump.css">
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><a href="./" style="color: inherit; text-decoration: none;">BigDump v<?= $view->e($version) ?></a></h1>
                <div class="subtitle">Staggered MySQL Dump Importer</div>
            </div>
            <div class="card-body">
                <?= $view->content() ?>
            </div>
        </div>

        <div class="footer">
            BigDump v<?= $view->e($version) ?> - Refactored MVC Edition<br>
            Original by Alexey Ozerov | Refactored with improved stability and security | Made by w3spi5 (wespify.com) with ❤️ for everyone
        </div>
    </div>
</body>
</html>
