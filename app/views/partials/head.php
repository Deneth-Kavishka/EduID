<?php $config = app_config(); ?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['app_name']) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= base_url('assets/logos/eduid-mark.svg') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/styles.css') ?>">
    <script>
        window.APP_BASE_URL = <?= json_encode(rtrim(base_url(), '/')) ?>;
    </script>
</head>
<body>
