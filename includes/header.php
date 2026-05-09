<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="vendix-csrf-token" content="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <title>Vendix - Smart Ecommerce System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="<?php 
        $inPages = strpos($_SERVER['PHP_SELF'], 'pages/') !== false;
        echo ($inPages ? '../assets/css/style.css' : 'assets/css/style.css') . '?v=2.8'; 
    ?>"  >
    <script src="<?php 
        echo ($inPages ? '../assets/js/app.js' : 'assets/js/app.js') . '?v=2.5'; 
    ?>" defer></script>
    <script>
        (function() {
            var theme = localStorage.getItem('vendix_theme');
            if (theme) {
                document.documentElement.classList.add(theme);
            }
        })();
    </script>
</head>
<body>
