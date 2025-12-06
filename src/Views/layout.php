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
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
            color: white;
            padding: 20px 25px;
        }

        .card-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .card-header .subtitle {
            font-size: 14px;
            opacity: 0.8;
        }

        .card-body {
            padding: 25px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }

        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f7fafc;
            font-weight: 600;
            color: #4a5568;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:hover td {
            background: #f7fafc;
        }

        .btn {
            display: inline-block;
            padding: 8px 16px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-1px);
        }

        .btn-danger {
            background: #e53e3e;
            color: white;
        }

        .btn-danger:hover {
            background: #c53030;
        }

        .btn-secondary {
            background: #718096;
            color: white;
        }

        .btn-secondary:hover {
            background: #4a5568;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
        }

        .form-control {
            width: 100%;
            padding: 10px 15px;
            font-size: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 6px;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-muted {
            color: #718096;
            font-size: 13px;
        }

        .mt-3 {
            margin-top: 15px;
        }

        .mb-3 {
            margin-bottom: 15px;
        }

        .progress-container {
            background: #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            height: 24px;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .file-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .file-type-sql {
            background: #ebf8ff;
            color: #2b6cb0;
        }

        .file-type-gz {
            background: #faf5ff;
            color: #6b46c1;
        }

        .file-type-csv {
            background: #f0fff4;
            color: #276749;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: white;
            font-size: 14px;
            opacity: 0.8;
        }

        .footer a {
            color: white;
        }

        @media (max-width: 600px) {
            .card-body {
                padding: 15px;
            }

            th, td {
                padding: 10px;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>BigDump v<?= $view->e($version) ?></h1>
                <div class="subtitle">Staggered MySQL Dump Importer</div>
            </div>
            <div class="card-body">
                <?= $view->content() ?>
            </div>
        </div>

        <div class="footer">
            BigDump v<?= $view->e($version) ?> - Refactored MVC Edition<br>
            Original by Alexey Ozerov | Refactored with improved stability and security
        </div>
    </div>
</body>
</html>
