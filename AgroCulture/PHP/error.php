<?php session_start(); ?>

<!DOCTYPE html>
<html>
<head>
    <title>AgroCulture</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <!-- Bootstrap CSS -->
    <link href="../bootstrap/css/bootstrap.min.css" rel="stylesheet">

    <!-- JS and jQuery -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="../bootstrap/js/bootstrap.min.js"></script>

    <!-- Custom CSS/JS -->
    <script src="../js/jquery.min.js"></script>
    <script src="../js/skel.min.js"></script>
    <script src="../js/skel-layers.min.js"></script>
    <script src="../js/init.js"></script>

    <link rel="stylesheet" href="../css/skel.css" />
    <link rel="stylesheet" href="../css/style.css" />
    <link rel="stylesheet" href="../css/style-xlarge.css" />
</head>

<body>
    <?php require 'menu.php'; ?>

    <section id="banner" class="wrapper">
        <div class="container">
            <header class="major">
                <h2>ERROR</h2>
            </header>
            <p style="color:red; font-weight:bold;">
                <?php
                    // Fallback URL if HTTP_REFERER is not set
                    $page = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../index.php';

                    if (isset($_SESSION['message']) && !empty($_SESSION['message'])) {
                        echo $_SESSION['message'];
                    } else {
                        header("Location: ../index.php");
                        exit();
                    }
                ?>
            </p><br />

            <a href="<?= htmlspecialchars($page) ?>" class="button special">🔁 Retry</a>
        </div>
    </section>

    <?php $_SESSION['message'] = ""; ?>
</body>
</html>
