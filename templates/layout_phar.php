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

        /* Header gradient - Light mode (pastel) */
        [data-theme="light"] .header-gradient, :root:not([data-theme]) .header-gradient {
            background: linear-gradient(to right, #fcd34d, #fdba74, #fbbf24);
        }
        [data-theme="light"] .header-gradient .header-subtitle, :root:not([data-theme]) .header-gradient .header-subtitle {
            color: #78350f;
        }
        [data-theme="light"] .header-gradient .header-title, :root:not([data-theme]) .header-gradient .header-title {
            color: #451a03;
        }
        [data-theme="light"] .header-gradient .header-logo, :root:not([data-theme]) .header-gradient .header-logo {
            color: #d97706;
        }
        [data-theme="light"] .header-gradient .header-badge, :root:not([data-theme]) .header-gradient .header-badge {
            background: rgba(120, 53, 15, 0.2);
            border: 1px solid rgba(120, 53, 15, 0.3);
            color: #78350f;
        }

        /* Header gradient - Dark mode (muted pastel) */
        [data-theme="dark"] .header-gradient {
            background: linear-gradient(to right, #d97706, #ea580c, #c2410c);
        }
        [data-theme="dark"] .header-gradient .header-subtitle {
            color: #fef3c7;
        }
        [data-theme="dark"] .header-gradient .header-title {
            color: #fffbeb;
        }
        [data-theme="dark"] .header-gradient .header-logo {
            color: #f59e0b;
        }
        [data-theme="dark"] .header-gradient .header-badge {
            background: rgba(120, 53, 15, 0.4);
            border: 1px solid rgba(254, 243, 199, 0.3);
            color: #fef3c7;
        }

        /* Dark mode toggle button */
        [data-theme="light"] .header-gradient .dark-toggle, :root:not([data-theme]) .header-gradient .dark-toggle {
            background: rgba(120, 53, 15, 0.2);
            border-color: rgba(120, 53, 15, 0.4);
            color: #78350f;
        }
        [data-theme="dark"] .header-gradient .dark-toggle {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.3);
            color: #ffffff;
        }
    </style>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">
    <!-- Inlined SVG Icons for PHAR mode -->
    <div style="display: none;">
        <?= $view->getInlinedIcons() ?>
    </div>

    <div class="min-h-screen flex flex-col">
        <!-- Sticky Header -->
        <header class="header-gradient py-4 sticky top-0 z-50 shadow-lg">
            <div class="container-70 px-8 flex justify-between items-center">
                <a href="<?= $view->e($scriptUri) ?>" class="flex items-center gap-3 no-underline hover:opacity-90 transition-opacity">
                    <!-- Text-based logo for PHAR mode (no external images) -->
                    <div class="header-logo w-10 h-10 rounded-lg shadow-md bg-white/90 flex items-center justify-center font-bold text-xl">BD</div>
                    <div class="flex items-center">
                        <h1 class="header-title text-2xl font-semibold">BigDump v<?= $view->e($version) ?></h1>
                        <span class="header-subtitle text-sm ml-4">Staggered MySQL Dump Importer</span>
                        <span class="header-badge ml-2 px-2 py-0.5 text-xs rounded">PHAR</span>
                    </div>
                </a>
                <div>
                    <button id="darkModeToggle" type="button" title="Toggle dark mode" aria-label="Toggle dark mode" class="dark-toggle border-2 text-xl px-3 py-2 rounded-lg cursor-pointer hover:scale-105 transition-all flex items-center justify-center min-w-[44px] h-10">
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
