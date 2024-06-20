<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/phprequest/src/styles/reset_password.css">
    <title>Пароль устарел</title>
</head>
<body>
<header class="header-section center">
    <!-- Заголовок с предупреждением о необходимости смены пароля -->
    <h1 class="header-content-header">Внимание! <span class="reset-password-warning">Срок действия пароля истёк!</span></h1>
    <h2 class="header-content-header">По требованию администратора необходимо <span class="reset-password-warning">изменить пароль!</span></h2>
    <h3 class="header-content-header">Срок действия пароля: <span class="reset-password-warning">90 календарных дней</span> со дня установки последнего пароля</h3>
</header>

<?php
session_start();
require_once ('../scripts/php/unblock_users.php');
require_once('../scripts/php/database.php');
checkAndUnblockUsers();

// Вывод ошибок, возникших при задании нового пароля
if (isset($_SESSION['reset_password_error'])) {
    echo '<p class="error-message">' . $_SESSION['reset_password_error'] . '</p>';
    unset($_SESSION['reset_password_error']);
}
?>
<article class="article-section center">
    <section class="main-section-content center">
        <!-- Заголовок формы для смены пароля -->
        <h1 class="main-content-header">Изменить устаревший пароль</h1>
        <form class="reset-password-form" action="/phprequest/src/scripts/php/reset_expired_password.php" method="post">
            <!-- Поля для ввода нового пароля и его подтверждения -->
            <label for="new-password">Новый пароль:</label><br>
            <input type="password" id="new-password" name="new_password" required><br><br>
            <label for="confirm-password">Подтвердите пароль:</label><br>
            <input type="password" id="confirm-password" name="confirm_password" required><br><br>
            <!-- Кнопка для отправки формы -->
            <input class="change-password-button" type="submit" value="Сменить пароль">
        </form>
    </section>
</article>
</body>
</html>
