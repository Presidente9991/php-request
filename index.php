<?php
session_start(); // Начинаем сессию для сохранения и доступа к данным сессии

// Определяем путь к временному файлу сообщения
$tempFilePath = $_SERVER['DOCUMENT_ROOT'] . '/phprequest/documents/temp_message.txt';

// Проверяем наличие временного файла и его содержимого
if (file_exists($tempFilePath)) {
    $message = file_get_contents($tempFilePath); // Читаем содержимое временного файла
    unlink($tempFilePath); // Удаляем временный файл после чтения
} else {
    // Если временного файла нет, получаем сообщение из сессии, если оно существует
    $message = $_SESSION['message'] ?? '';
    unset($_SESSION['message']); // Удаляем сообщение из сессии после получения
}
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <!-- Ссылка на Telegram -->
        <a href="https://t.me/Presidente9991" target="_blank">
            <img class="social-logo-image" src="/phprequest/src/images/logos/telegram.png" alt="telegram-logo">
        </a>
        <p class="social-logo-header">GitHub</p>
        <!-- Ссылка на GitHub -->
        <a href="https://github.com/Presidente9991" target="_blank">
            <img class="social-logo-image" src="/phprequest/src/images/logos/github.png" alt="github-logo">
        </a>
        <p class="social-logo-header">GitFlic</p>
        <!-- Ссылка на GitFlic -->
        <a href="https://gitflic.ru/user/toxicoman9991" target="_blank">
            <img class="social-logo-image" src="/phprequest/src/images/logos/gitflic.png" alt="gitflic-logo">
        </a>
    </div>
</header>
<section class="main-content center">
    <h1 class="main-content-header">Система запроса справок о назначенных суммах</h1>
    <form class="auth-form center" action="/phprequest/src/scripts/php/login.php" method="post">
        <!-- Отображение сообщения, если оно не пустое -->
        <?php if (!empty($message)): ?>
            <p class="wrong-login-password"><?= $message ?></p>
        <?php endif; ?>
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
        <!-- Ссылка на профиль GeekBrains -->
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