<?php
session_start();
require_once('../../src/scripts/php/database.php');
require_once('../../src/scripts/php/check_request_status.php');
require_once ('../../src/scripts/php/unblock_users.php');
checkAndUnblockUsers();

// Проверяем, если пользователь не авторизован, перенаправляем его на страницу авторизации
if (!isset($_SESSION['user'])) {
    header('Location: /phprequest/index.php');
    exit();
}
// Проверяем, если роль пользователя не "Администратор", перенаправляем его на главную страницу
if ($_SESSION['user']['role_id'] !== '1') {
    // Если роль пользователя не равна 1, то перенаправляем его обратно на текущую страницу
    header('Location: /phprequest/src/pages/main.php');
    exit();
}

// Получить текущую дату и дату, выбранную пользователем из календаря
$current_datetime = date('d.m.Y');

$db_conn = databaseConnection();
$query = "SELECT u.*, r.name_user_role, 
                 CASE 
                     WHEN u.password_expiry_date < CURRENT_TIMESTAMP THEN 'Истек'
                     ELSE 'Активен'
                 END AS password_status,
                 CASE 
                     WHEN u.unlimited_password_expiry = 'true' THEN 'Бессрочный'
                     ELSE 'Срочный'
                 END AS password_expiry_type,
                 CASE 
                     WHEN u.blocked = 'true' THEN 'Заблокирован'
                     ELSE 'Не заблокирован'
                 END AS block_status,
                 u.blocked_until
         FROM phprequest_schema.users u
         JOIN phprequest_schema.users_roles r ON u.role_id = r.id_user_role
         ORDER BY u.users_id DESC";



$result = pg_query($db_conn, $query);

// Собираем данные в массив
$user_data = [];
while ($row = pg_fetch_assoc($result)) {
    $user_data[] = $row;
}

// Освобождаем результат запроса
pg_free_result($result);
// Закрываем соединение с базой данных
pg_close($db_conn);

// Проверка на наличие сообщений об ошибках или успехе при регистрации
$registration_error = $_SESSION['registration_error'] ?? null;
$registration_success = $_SESSION['registration_success'] ?? null;

// Очистка сообщений после отображения
unset($_SESSION['registration_error']);
unset($_SESSION['registration_success']);
?>

<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link rel="icon" type="image/png" href="/phprequest/src/images/favicons/favicon.png">
    <link rel="stylesheet" href="/phprequest/src/styles/admin.css">
    <title>Страница администрирования</title>
</head>
<body>
<header class="header-section center">
    <h1 class="header-content-header">Страница администрирования</h1>
    <p class="current-date-label">Сейчас:
        <span class="current-date"><?php echo $current_datetime;?></span> года<br>
        <span class="current-time-label">Текущее время:</span>
        <span class="current-clock"></span>
    </p>
    <?php if (isset($_SESSION['edit_user_error'])): ?>
        <p class="error-message"><?php echo $_SESSION['edit_user_error']; ?></p>
        <?php unset($_SESSION['edit_user_error']); ?>
    <?php endif; ?>
</header>
<article class="article-section center">
    <section class="main-section-content center">
        <h1 class="main-content-header">Таблица пользователей</h1>
        <?php if (isset($success_message)): ?>
            <p class="success-message"><?php echo $success_message; ?></p>
        <?php elseif (isset($error_message)): ?>
            <p class="error-message"><?php echo $error_message; ?></p>
        <?php endif; ?>
        <div id="php-users-table-container" class="users-table-container">
            <table class="users-content-table">
                <thead class="users-table-header">
                <tr class="users-table-rows">
                    <th class="users-table-header">Номер</th>
                    <th class="users-table-header">Логин</th>
                    <th class="users-table-header">Фамилия</th>
                    <th class="users-table-header">Имя</th>
                    <th class="users-table-header">Отчество (при наличии)</th>
                    <th class="users-table-header">Роль</th>
                    <th class="users-table-header">Пароль изменён</th>
                    <th class="users-table-header">Смена пароля через</th>
                    <th class="users-table-header">Состояние пароля</th>
                    <th class="users-table-header">Срок действия пароля</th>
                    <th class="users-table-header">Состояние блокировки</th>
                    <th class="users-table-header">Срок блокировки</th>
                    <th class="users-table-header">Действия</th>
                </tr>
                </thead>
                <tbody class="users-table-body">
                    <?php foreach ($user_data as $row): ?>
                        <tr class="users-table-rows">
                            <td class="users-table-value"><?php echo $row['users_id']; ?></td>
                            <td class="users-table-value"><?php echo $row['login']; ?></td>
                            <td class="users-table-value"><?php echo $row['employee_last_name']; ?></td>
                            <td class="users-table-value"><?php echo $row['employee_first_name']; ?></td>
                            <td class="users-table-value"><?php echo $row['employee_middle_name']; ?></td>
                            <td class="users-table-value"><?php echo $row['name_user_role']; ?></td>
                            <td class="users-table-value"><?php echo date('d.m.Y H:i:s', strtotime($row['password_last_changed_at'])); ?></td>
                            <td class="users-table-value">
                                <?php
                                    try {
                                    $password_expiry_date = new DateTime($row['password_expiry_date']);
                                    $current_date = new DateTime();
                                    $days_remaining = $current_date->diff($password_expiry_date)->days;
                                    echo $days_remaining;
                                    } catch (Exception $e) {
                                        echo "Невозможно определить";
                                    }
                                ?>
                            </td>
                            <td class="users-table-value"><?php echo $row['password_status']; ?></td>
                            <td class="users-table-value"><?php echo $row['password_expiry_type']; ?></td>
                            <td class="users-table-value"><?php echo $row['block_status']; ?></td>
                            <td class="users-table-value">
                                <?php
                                    if ($row['blocked'] === 't') {
                                        if ($row['blocked_until'] === '\N' || $row['blocked_until'] === null) {
                                            echo "Бессрочно";
                                        } else {
                                            echo date('d.m.Y H:i:s', strtotime($row['blocked_until']));
                                        }
                                    } else {
                                        echo "Не заблокирован";
                                    }
                                ?>
                            </td>
                            <td class="users-table-value">
                                <a class="table-value-link" href="/phprequest/src/scripts/php/edit_user.php?users_id=<?php echo $row['users_id']; ?>">Редактировать</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
    // Проверяем результат выполнения запроса на изменение информации о пользователях
    if (isset($_SESSION['update_user_success'])) {
    echo "<p class='success-message'>{$_SESSION['update_user_success']}</p>";
    unset($_SESSION['update_user_success']);
    } elseif (isset($_SESSION['update_user_error'])) {
    echo "<p class='error-message'>{$_SESSION['update_user_error']}</p>";
    unset($_SESSION['update_user_error']);
    }
    ?>
    <section class="main-section-content center">
        <?php
        // Вывод сообщений об разблокировке пользователей из сессии
        if (isset($_SESSION['unblock_users_message'])) {
            echo "<p class='unblock-users-message'>{$_SESSION['unblock_users_message']}</p>";
            unset($_SESSION['unblock_users_message']);
        }
        ?>
        <h1 class="main-content-header">Регистрация нового пользователя</h1>
        <?php if (isset($registration_error) && $registration_error): ?>
            <p class="registration-error"><?php echo $registration_error; ?></p>
        <?php elseif (isset($registration_success) && $registration_success): ?>
            <p class="registration-success"><?php echo $registration_success; ?></p>
        <?php endif; ?>

                <form class="registration-new-user" action="/phprequest/src/scripts/php/registration.php" method="post">
                    <label for="last-name">Фамилия:</label>
                    <input type="text" id="last-name" name="last-name" placeholder="Укажите фамилию" required>

                    <label for="first-name">Имя:</label>
                    <input type="text" id="first-name" name="first-name" placeholder="Укажите имя" required>

                    <label for="middle-name">Отчество (при наличии):</label>
                    <input type="text" id="middle-name" placeholder="Укажите отчество" name="middle-name">

                    <label for="role">Роль пользователя:</label>
                    <select id="role" name="role" required>
                        <option value="1">Администратор</option>
                        <option value="2">Пользователь</option>
                    </select>

                    <label for="login">Логин:</label>
                    <input type="text" id="login" name="login" placeholder="Придумайте логин" required>

                    <label for="password">Пароль:</label>
                    <input type="password" id="password" name="password" placeholder="Придумайте пароль" required>
                    <span id="password-strength-indicator"></span>

                    <label for="confirm-password">Подтверждение пароля:</label>
                    <input type="password" id="confirm-password" name="confirm-password" placeholder="Подтвердите указанный пароль" required>

                    <label for="unlimited-password-expiry">Сделать указанный пароль бессрочным?
                        <input type="checkbox" id="unlimited-password-expiry" name="unlimited-password-expiry">
                    </label>

                    <div class="password-requirements">
                        <h1 class="main-content-header">Требования к паролю</h1>
                        <ul class="list-password-requirements">
                            <li id="length">Не менее 8 символов</li>
                            <li id="no-cyrillic">Не должно быть кириллицы</li>
                            <li id="uppercase">Хотя бы одна заглавная буква</li>
                            <li id="lowercase">Хотя бы одна строчная буква</li>
                            <li id="number">Хотя бы одна цифра</li>
                            <li id="special">Хотя бы один специальный символ</li>
                            <li id="no-sequential">Не должно быть 4 и более одинаковых или идущих подряд букв или цифр</li>
                            <li id="no-spaces">Не должно быть пробелов</li>
                        </ul>
                    </div>
                    <button class="form-register-user-button" type="submit">Регистрация пользователя</button>
                </form>
    </section>
</article>
<footer class="footer-section center">
    <a class="back-to-main-page-link" href="/phprequest/src/pages/main.php">Вернуться на главную страницу</a>
</footer>
<script src="/phprequest/src/scripts/javascript/admin.js"></script>
</body>
</html>
