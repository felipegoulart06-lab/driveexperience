<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · Drive Xperience</title>
    <link rel="icon" href="/favicon.png" type="image/png">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="stylesheet" href="/assets/css/indicacao.css?v=<?= (int) filemtime(base_path('public/assets/css/indicacao.css')) ?>">
</head>
<body>
<?= $content ?>
</body>
</html>
