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
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <link rel="stylesheet" href="assets/css/bigdump.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: ['selector', '[data-theme="dark"]'],
            theme: {
                extend: {
                    maxWidth: {
                        'container': '70vw',
                    },
                    animation: {
                        'progress-stripe': 'progress-stripe 1s linear infinite',
                    },
                    keyframes: {
                        'progress-stripe': {
                            '0%': { backgroundPosition: '1rem 0' },
                            '100%': { backgroundPosition: '0 0' },
                        }
                    }
                }
            }
        }
    </script>
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
    <div class="min-h-screen flex flex-col">
        <!-- Sticky Header -->
        <header class="bg-gradient-to-r from-cyan-600 to-rose-900 text-white py-4 sticky top-0 z-50 shadow-lg">
            <div class="max-w-container mx-auto w-full px-8 flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <img src="assets/img/logo.png" alt="BigDump Logo" class="w-10 h-10 rounded-lg shadow-md">
                    <div class="flex items-center">
                        <h1><a href="./" class="text-2xl font-semibold text-white no-underline hover:text-white">BigDump v<?= $view->e($version) ?></a></h1>
                        <span class="text-sm opacity-80 ml-4">Staggered MySQL Dump Importer</span>
                    </div>
                </div>
                <div>
                    <button id="darkModeToggle" type="button" title="Toggle dark mode" aria-label="Toggle dark mode" class="bg-white/15 border-2 border-white/30 text-white text-xl px-3 py-2 rounded-lg cursor-pointer hover:bg-white/25 hover:scale-105 transition-all flex items-center justify-center min-w-[44px] h-10">
                        <i class="fa-solid fa-sun icon-sun"></i>
                        <i class="fa-solid fa-moon icon-moon"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 py-6 px-8 max-w-container mx-auto w-full">
            <?= $view->content() ?>
        </main>

        <!-- Footer -->
        <footer class="bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 py-4 px-8 text-center text-sm text-gray-500 dark:text-gray-400">
            BigDump v<?= $view->e($version) ?> - Refactored MVC Edition<br>
            Original by Alexey Ozerov | Refactored with improved stability and security | Made by w3spi5 (wespify.com) with ❤️
        </footer>
    </div>
    <script src="assets/js/bigdump.js"></script>
    <script src="assets/js/fileupload.js"></script>
</body>
</html>
