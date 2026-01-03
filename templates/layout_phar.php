<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="-1">
    <meta name="robots" content="noindex, nofollow">
    <title>BigDump v<?= $view->e($version) ?> - MySQL Dump Importer (PHAR Mode)</title>
    <!-- Inlined CSS for PHAR mode -->
    <style><?= $view->getInlinedCss() ?></style>
    <script>
        // Theme init before render (prevent flash)
        (function() {
            var saved = localStorage.getItem('bigdump-theme');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var theme = saved || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <style>
        [data-theme="light"] .icon-sun, :root:not([data-theme]) .icon-sun { display: none; }
        [data-theme="light"] .icon-moon, :root:not([data-theme]) .icon-moon { display: inline; }
        [data-theme="dark"] .icon-moon { display: none; }
        [data-theme="dark"] .icon-sun { display: inline; }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">
    <!-- Inlined SVG Icons for PHAR mode -->
    <div style="display: none;">
        <?= $view->getInlinedIcons() ?>
    </div>

    <div class="min-h-screen flex flex-col">
        <!-- Sticky Header -->
        <header class="bg-gradient-to-r from-cyan-600 to-rose-900 text-white py-4 sticky top-0 z-50 shadow-lg">
            <div class="container-70 px-8 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <!-- Text-based header for PHAR mode (no external images) -->
                    <div class="w-10 h-10 rounded-lg shadow-md bg-white/20 flex items-center justify-center font-bold text-xl">BD</div>
                    <div class="flex items-center">
                        <h1><a href="./" class="text-2xl font-semibold text-white no-underline hover:text-white">BigDump v<?= $view->e($version) ?></a></h1>
                        <span class="text-sm opacity-80 ml-4">Staggered MySQL Dump Importer</span>
                        <span class="ml-2 px-2 py-0.5 text-xs bg-white/20 rounded">PHAR</span>
                    </div>
                </div>
                <div>
                    <button id="darkModeToggle" type="button" title="Toggle dark mode" aria-label="Toggle dark mode" class="bg-white/15 border-2 border-white/30 text-white text-xl px-3 py-2 rounded-lg cursor-pointer hover:bg-white/25 hover:scale-105 transition-all flex items-center justify-center min-w-[44px] h-10">
                        <svg class="icon icon-sun w-5 h-5 fill-current"><use href="#sun"></use></svg>
                        <svg class="icon icon-moon w-5 h-5 fill-current"><use href="#moon"></use></svg>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 py-6 px-8 container-70">
            <?= $view->content() ?>
        </main>

        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-4 px-8 text-center text-sm text-gray-500 dark:text-gray-400">
            BigDump v<?= $view->e($version) ?> - PHAR Edition<br>
            Original by Alexey Ozerov (2003) | Refactored with improved stability and security | Made by w3spi5 (wespify.com)
        </footer>
    </div>

    <!-- Inlined JavaScript for PHAR mode -->
    <script><?= $view->getInlinedJs() ?></script>
</body>
</html>
