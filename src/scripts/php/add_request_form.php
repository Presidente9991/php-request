<?php
session_start();

// Проверить, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit();
}

// Вывод сообщений об ошибке добавления запросов в таблицу
if (isset($_SESSION['add_request_error'])) {
    echo '<div style="color: red;">' . $_SESSION['add_request_error'] . '</div>';
    unset($_SESSION['add_request_error']);
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Создание запроса</title>
    <link rel="stylesheet" href="/phprequest/src/styles/add_request_form.css"> <!-- Подключение CSS-стилей для формы -->
</head>
<body>
<header class="header-section center">
    <h1 class="header-content-header">Создать запрос</h1>
</header>
<article class="article-section center">
    <section class="main-section-content">
        <form class="add-request-form" action="/phprequest/src/scripts/php/add_request_database.php" method="post">
            <label for="snils_citizen">СНИЛС гражданина:</label>
            <input type="text" id="snils_citizen" name="snils_citizen" placeholder="Введите СНИЛС в формате XXX-XXX-XXX XX" required maxlength="14" pattern="\d{3}-\d{3}-\d{3} \d{2}">

            <label for="last_name_citizen">Фамилия гражданина:</label>
            <input type="text" id="last_name_citizen" name="last_name_citizen" placeholder="Введите фамилию гражданина" required>

            <label for="first_name_citizen">Имя гражданина:</label>
            <input type="text" id="first_name_citizen" name="first_name_citizen" placeholder="Введите имя гражданина" required>

            <label for="middle_name_citizen">Отчество гражданина (при наличии):</label>
            <input type="text" id="middle_name_citizen" name="middle_name_citizen" placeholder="Введите отчество гражданина">

            <label for="birthday_citizen">Дата рождения гражданина (ДД.ММ.ГГГГ):</label>
            <input type="date" id="birthday_citizen" name="birthday_citizen" placeholder="Укажите дату рождения гражданина" required min="1900-01-01" max="2100-12-31">

            <label for="requested_date_start">Дата начала запрашиваемого периода (ДД.ММ.ГГГГ):</label>
            <input type="date" id="requested_date_start" name="requested_date_start" placeholder="Укажите дату начала запрашиваемого периода" required min="1900-01-01" max="2100-12-31">

            <label for="requested_date_end">Дата окончания запрашиваемого периода (ДД.ММ.ГГГГ):</label>
            <input type="date" id="requested_date_end" name="requested_date_end" placeholder="Укажите дату окончания запрашиваемого периода" required min="1900-01-01" max="2100-12-31">

            <button type="submit" name="add_request" value="submit">Создать запрос</button>
        </form>
    </section>
</article>
<footer class="footer-section center">
    <a href="/phprequest/src/pages/main.php" class="back-to-main-page-link">Вернуться на главную страницу</a>
</footer>
<script>
    // Скрипт для форматирования ввода СНИЛСа
    document.getElementById('snils_citizen').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // Удаление всех нецифровых символов
        if (value.length > 11) {
            value = value.substring(0, 11); // Ограничение ввода до 11 символов
        }
        if (value.length > 2 && value.length < 6) {
            value = value.replace(/^(\d{3})(\d{0,3}).*/, '$1-$2'); // Формат XXX-XXX
        } else if (value.length >= 6 && value.length < 9) {
            value = value.replace(/^(\d{3})(\d{3})(\d{0,3}).*/, '$1-$2-$3'); // Формат XXX-XXX-XXX
        } else if (value.length >= 9) {
            value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2}).*/, '$1-$2-$3 $4'); // Формат XXX-XXX-XXX XX
        }
        e.target.value = value;
    });
</script>
</body>
</html>
