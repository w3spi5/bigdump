<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>BigDump Error</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 40px; }
        .error { background: white; border-left: 4px solid #e74c3c; padding: 20px; max-width: 600px; margin: 0 auto; }
        h1 { color: #e74c3c; margin-top: 0; font-size: 20px; }
        p { color: #333; line-height: 1.6; }
        code { background: #f8f8f8; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="error">
        <h1>BigDump Error</h1>
        <p><?= htmlspecialchars($message ?? 'An unknown error occurred') ?></p>
        <p>Please check your configuration and try again.</p>
    </div>
</body>
</html>
