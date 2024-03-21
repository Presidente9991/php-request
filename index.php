<?php
require_once('src/scripts/php/database.php');
require_once('src/scripts/php/unblock_users.php');
session_start();

checkAndUnblockUsers();

$db_conn = databaseConnection();
$user_id = $_SESSION['user']['id'] ?? null;

if (isset($_SESSION['user'])) {
    header('Location: /phprequest/src/pages/main.php');
    exit();
}
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" type="image/png" href="/phprequest/src/images/favicons/favicon.png">
    <link rel="stylesheet" href="/phprequest/src/styles/index.css">
    <title>Авторизация</title>
</head>
<body>
<header class="header center">
    <h1 class="header-content-header">Социальные сети</h1>
    <div class="social-logo">
        <p class="social-logo-header">Telegram</p>
        <a href="https://t.me/Presidente9991" target="_blank">
            <img class="social-logo-image" src="/phprequest/src/images/logos/telegram.png" alt="telegram-logo">
        </a>
        <p class="social-logo-header">GitHub</p>
        <a href="https://github.com/Presidente9991" target="_blank">
            <img class="social-logo-image" src="/phprequest/src/images/logos/github.png" alt="github-logo">
        </a>
        <p class="social-logo-header">GitFlic</p>
        <a href="https://gitflic.ru/user/toxicoman9991" target="_blank">
            <img class="social-logo-image" src="/phprequest/src/images/logos/gitflic.png" alt="gitflic-logo">
        </a>
    </div>
</header>
<section class="main-content center">
    <?php
    $tempFilePath = '/documents/temp_message.txt';
    if (file_exists($_SERVER['DOCUMENT_ROOT'] . $tempFilePath)) {
        $message = file_get_contents($_SERVER['DOCUMENT_ROOT'] . $tempFilePath);
        unlink($_SERVER['DOCUMENT_ROOT'] . $tempFilePath);
        echo '<p class="blocked-message">' . $message . '</p>';
    }
    ?>

    <?php if (isset($_SESSION['reset_password_success'])): ?>
        <p class="success-message"><?php echo $_SESSION['reset_password_success']; ?></p>
        <?php unset($_SESSION['reset_password_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['reset_password_error'])): ?>
        <p class="error-message"><?php echo $_SESSION['reset_password_error']; ?></p>
        <?php unset($_SESSION['reset_password_error']); ?>
    <?php endif; ?>

    <h1 class="main-content-header">Система запроса справок о назначенных суммах</h1>
    <form class="auth-form center" action="/phprequest/src/scripts/php/login.php" method="post">
        <?php
        if (isset($_SESSION['message'])) {
            echo '<p class="wrong-login-password"> ' . $_SESSION['message'] . ' </p>';
        }
        unset($_SESSION['message']);
        ?>
        <label for="login" class="login-label">Логин
            <input id="login" type="text" name="login" placeholder="Введите ваш логин">
        </label>
        <label for="password" class="password-label">Пароль
            <input id="password" type="password" name="password" placeholder="Введите пароль">
        </label>
        <button type="submit" class="login-button">Войти</button>
    </form>
    <p class="forgot-password">Забыли пароль?<a class="forgot-password-link" href=""> Нажмите сюда</a></p>
</section>
<footer class="footer center">
    <div class="footer-content center">
        <a href="https://gb.ru/users/2ee33a68-028a-49ca-bf37-fdebf86b9dd6" target="_blank">
            <img class="social-logo-image" src="/phprequest/src/images/logos/geek_brains.png" alt="gb-logo">
        </a>
        <h3 class="footer-header">Дипломный проект: <span class="theme-work">Создание веб-приложения "Система запроса справок о назначенных суммах"</span></h3>
        <h4 class="footer-header">Автор: Зенин Александр Юрьевич</h4>
        <h4 class="footer-header">Группа: 5052</h4>
        <h4 class="footer-header">Специализация: Разработчик | Программист | Системный администратор</h4>
        <h1 class="footer-header">Сайт не является <span class="no-commercial">информационной системой</span> или <span class="no-commercial">информационной системой персональных данных</span>!</h1>
        <h1 class="footer-header"><span class="no-commercial">Только для учебных целей</span>!</h1>
        <h1 class="footer-header">Сбор <span class="no-commercial">персональных данных</span> и иное<span class="no-commercial"> коммерческое использование запрещено</span>!</h1>
    </div>
</footer>
<script src="/phprequest/src/scripts/javascript/index.js"></script>
</body>
</html>
