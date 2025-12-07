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
            background: #212830;
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
            background: linear-gradient(135deg, #0096be 0%, #531f15 100%);
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

        .eta-container {
            font-size: 14px;
            color: #4a5568;
        }
        .eta-icon {
            margin-right: 5px;
        }
        .eta-label {
            color: #718096;
        }
        .eta-value {
            font-weight: 600;
            color: #2d3748;
            font-family: 'SF Mono', 'Monaco', 'Inconsolata', 'Fira Code', monospace;
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

        /* FileUpload Component Styles */
        .file-upload {
            margin-top: 10px;
        }

        .file-upload__dropzone {
            border: 2px dashed #cbd5e0;
            border-radius: 12px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            position: relative;
        }

        .file-upload__dropzone:hover {
            border-color: #667eea;
            background: linear-gradient(135deg, #ebf4ff 0%, #e6e6fa 100%);
        }

        .file-upload__dropzone--active {
            border-color: #667eea;
            background: linear-gradient(135deg, #ebf4ff 0%, #e6e6fa 100%);
            transform: scale(1.02);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
        }

        .file-upload__dropzone--error {
            border-color: #e53e3e;
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
        }

        .file-upload__icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 15px;
            opacity: 0.6;
        }

        .file-upload__icon svg {
            width: 100%;
            height: 100%;
            fill: #667eea;
        }

        .file-upload__text {
            color: #4a5568;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .file-upload__text strong {
            color: #667eea;
        }

        .file-upload__hint {
            color: #718096;
            font-size: 13px;
        }

        .file-upload__input {
            display: none;
        }

        /* File List */
        .file-upload__list {
            margin-top: 20px;
        }

        .file-upload__item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: #f7fafc;
            border-radius: 8px;
            margin-bottom: 10px;
            gap: 12px;
        }

        .file-upload__item--uploading {
            background: linear-gradient(135deg, #ebf4ff 0%, #f7fafc 100%);
        }

        .file-upload__item--success {
            background: linear-gradient(135deg, #f0fff4 0%, #c6f6d5 100%);
        }

        .file-upload__item--error {
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
        }

        .file-upload__item-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }

        .file-upload__item-icon--sql {
            background: #ebf8ff;
            color: #2b6cb0;
        }

        .file-upload__item-icon--gz {
            background: #faf5ff;
            color: #6b46c1;
        }

        .file-upload__item-icon--csv {
            background: #f0fff4;
            color: #276749;
        }

        .file-upload__item-info {
            flex: 1;
            min-width: 0;
        }

        .file-upload__item-name {
            font-weight: 500;
            color: #2d3748;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .file-upload__item-meta {
            font-size: 12px;
            color: #718096;
            margin-top: 2px;
        }

        .file-upload__item-progress {
            flex: 1;
            max-width: 200px;
        }

        .file-upload__progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .file-upload__progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .file-upload__progress-text {
            font-size: 11px;
            color: #718096;
            text-align: right;
            margin-top: 3px;
        }

        .file-upload__item-status {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .file-upload__item-status svg {
            width: 20px;
            height: 20px;
        }

        .file-upload__item-remove {
            width: 28px;
            height: 28px;
            border: none;
            background: transparent;
            cursor: pointer;
            opacity: 0.5;
            transition: opacity 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .file-upload__item-remove:hover {
            opacity: 1;
        }

        .file-upload__item-remove svg {
            width: 16px;
            height: 16px;
            fill: #e53e3e;
        }

        /* Upload Actions */
        .file-upload__actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .file-upload__actions .btn {
            flex: 1;
        }

        /* Spinner Animation */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .file-upload__spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        /* Validation Error */
        .file-upload__error {
            color: #e53e3e;
            font-size: 13px;
            margin-top: 5px;
        }

        /* Enhanced Error Display */
        .error-container {
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
            border: 2px solid #fc8181;
            border-radius: 12px;
            margin-bottom: 25px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(229, 62, 62, 0.15);
        }

        .error-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 20px 24px;
            background: linear-gradient(135deg, #c53030 0%, #9b2c2c 100%);
            color: white;
        }

        .error-header__icon {
            flex-shrink: 0;
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .error-header__icon svg {
            width: 28px;
            height: 28px;
            color: #fff;
        }

        .error-header__content {
            flex: 1;
            min-width: 0;
        }

        .error-header__title {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .error-header__summary {
            font-size: 15px;
            line-height: 1.5;
            opacity: 0.95;
            background: rgba(0, 0, 0, 0.15);
            padding: 8px 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            word-break: break-word;
        }

        .error-header__line {
            margin-top: 4px;
        }

        .error-line-badge {
            display: inline-block;
            background: #fff;
            color: #c53030;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .error-details {
            border-top: 1px solid rgba(197, 48, 48, 0.2);
        }

        .error-details__toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 24px;
            background: rgba(197, 48, 48, 0.08);
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #c53030;
            user-select: none;
            list-style: none;
        }

        .error-details__toggle::-webkit-details-marker {
            display: none;
        }

        .error-details__toggle::marker {
            display: none;
        }

        .error-details__toggle:hover {
            background: rgba(197, 48, 48, 0.12);
        }

        .error-details__chevron {
            width: 20px;
            height: 20px;
            transition: transform 0.2s ease;
        }

        .error-details[open] .error-details__chevron {
            transform: rotate(180deg);
        }

        .error-details__content {
            padding: 16px 24px 20px;
            background: #fff;
        }

        .error-details__pre {
            margin: 0;
            padding: 16px;
            background: #2d3748;
            color: #e2e8f0;
            border-radius: 8px;
            font-family: 'SF Mono', Monaco, 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            overflow-x: auto;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Responsive adjustments for error display */
        @media (max-width: 600px) {
            .error-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 16px;
                gap: 12px;
            }

            .error-header__icon {
                width: 40px;
                height: 40px;
            }

            .error-header__icon svg {
                width: 24px;
                height: 24px;
            }

            .error-header__title {
                font-size: 20px;
            }

            .error-header__summary {
                font-size: 14px;
            }

            .error-details__toggle {
                padding: 14px 16px;
                font-size: 13px;
                min-height: 44px;
            }

            .error-details__content {
                padding: 12px 16px 16px;
            }

            .error-details__pre {
                font-size: 12px;
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1><a href="index.php" style="color: inherit; text-decoration: none;">BigDump v<?= $view->e($version) ?></a></h1>
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
