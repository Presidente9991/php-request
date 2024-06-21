<?php
session_start(); // Начало сессии

require_once('../../src/scripts/php/unblock_users.php'); // Подключаем файл для разблокировки пользователей
checkAndUnblockUsers(); // Проверяем и разблокируем пользователей, если это необходимо

require_once('../../src/scripts/php/calendar.php'); // Подключаем файл календаря
require_once('../../src/scripts/php/database.php'); // Подключаем файл для работы с базой данных
require_once('../../src/scripts/php/main_get_data.php'); // Подключаем файл для получения данных
require_once('../../src/scripts/php/check_request_status.php'); // Подключаем файл для проверки статуса запросов

global $prev_month, $next_month, $current_year, $current_month; // Объявляем глобальные переменные

$db_conn = databaseConnection(); // Устанавливаем соединение с базой данных

// Проверить, существует ли пользователь в сессии
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправить на страницу авторизации
    header('Location: /phprequest/index.php');
    exit(); // Прерываем выполнение скрипта
}

// Проверить статус блокировки учетной записи
if (isset($_SESSION['user']['blocked']) && $_SESSION['user']['blocked']) {
    // Перенаправляем на logout_blocked_users.php для завершения сеанса
    header('Location: /phprequest/src/scripts/php/logout_blocked_users.php');
    exit(); // Прерываем выполнение скрипта
}

// Получить логин пользователя
$login = $_SESSION['user']['login'];
// Получить информацию о пользователе из базы данных, включая role_id и статус блокировки
$query = "SELECT employee_first_name, employee_last_name, employee_middle_name, role_id, blocked FROM phprequest_schema.users WHERE login = '$login'";
$result = pg_query($db_conn, $query);

// Проверяем, если запрос выполнен успешно
if ($result) {
    $user_info = pg_fetch_assoc($result); // Получаем информацию о пользователе

    // Установить значение переменной 'blocked' в сессию
    $_SESSION['user']['blocked'] = ($user_info['blocked'] === 't');
} else {
    echo "Ошибка выполнения запроса: " . pg_last_error($db_conn); // Выводим ошибку, если запрос не удался
    exit(); // Прерываем выполнение скрипта
}

// Получить текущую дату и дату, выбранную пользователем из календаря
$current_datetime = date('d.m.Y');
$selected_date = isset($_GET['date']) ? date('Y-m-d', strtotime($_GET['date'])) : date('Y-m-d');
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" type="image/png" href="/phprequest/src/images/favicons/favicon.png">
    <link rel="stylesheet" href="/phprequest/src/styles/main.css">
    <title>Главная страница</title>
</head>
<body>
<header class="header-section center">
    <p class="current-date-label">Сейчас:
        <span class="current-date"><?php echo $current_datetime;?></span> года<br>
        <span class="current-time-label">Текущее время:</span>
        <span class="current-clock"></span>
    </p>
    <?php
    if (isset($user_info)) { // Добавить проверку на существование переменной $user_info
        echo '<p class="username-label">Вы вошли как <br>'; // Вывести информацию о пользователе
        echo '<span class="username-elements">' . $user_info['employee_last_name'] . ' ' . $user_info['employee_first_name'] . ' ' . $user_info['employee_middle_name'] . '</span>';
        echo '</p>';
    } else {
        echo "Не удалось идентифицировать пользователя";
        exit(); // Выйти из скрипта в случае отсутствия данных о пользователе
    }
    ?>
    <?php
    // Проверить, существует ли пользователь в сессии и его роль не является "Пользователь" (с ID номером 2)
    if (isset($_SESSION['user']['role_id']) && $_SESSION['user']['role_id'] !== '2') {
        // Если пользователь авторизован и его роль не является "Пользователь", показать ссылку на страницу администрирования
        echo '<a class="link-to-admin-page" href="/phprequest/src/pages/admin.php"> Страница администрирования</a>';
    }
    ?>
    <a class="link-to-logout" href="/phprequest/src/scripts/php/logout.php">Выйти</a>
</header>
<article class="article-section">
    <section class="main-section-content center">
        <h2 class="main-content-header">Система запроса справок о назначенных суммах</h2>
        <div class="php-calendar-container">
            <a class="link-to-calendar-month" href="?date=<?php echo $prev_month; ?>">Перейти на предыдущий месяц</a>
            <?php echo generate_calendar($current_year, $current_month); // Генерация календаря ?>
            <a class="link-to-calendar-month" href="?date=<?php echo $next_month; ?>">Перейти на следующий месяц</a>
        </div>
    </section>
</article>
<aside class="aside-section center">
    <a href="/phprequest/src/scripts/php/add_request_form.php" class="create-request-page-link">Создать запрос</a>
    <?php
    if (isset($_SESSION['add_request_success'])) { // Проверяем, если существует сообщение об успешном добавлении запроса
        echo '<div style="color: green;">' . $_SESSION['add_request_success'] . '</div>';
        unset($_SESSION['add_request_success']); // Удаляем сообщение из сессии после его отображения
    }
    ?>
</aside>
<footer class="footer-section center">
    <div id="php-request-table-container" class="request-table-container">
        <?php
        // Проверяем, установлена ли выбранная дата в параметрах GET запроса
        if (isset($_GET['date'])) {
            // Получаем выбранную дату из параметров GET запроса
            $selected_date = $_GET['date'];
            // И выводим её на экран
            echo '<h2 class="footer-content-header">Запросы, созданные ' . date('d.m.Y', strtotime($selected_date)) . '</h2>';
            // Вставляем вызов функции generate_request_table($selected_date), где $selected_date - выбранная пользователем дата
            echo generate_request_table($selected_date);
        } else {
            // Если пользователь не выбрал дату, выводим сообщение об этом
            echo '<h1 class="footer-content-header">Выберите <span class="footer-content-header-important">дату</span> в календаре, чтобы отобразить сделанные запросы.</h1>';
        }
        ?>
    </div>
</footer>
<script src="/phprequest/src/scripts/javascript/main.js"></script>
</body>
</html>
