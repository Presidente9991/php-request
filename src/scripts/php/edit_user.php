<?php
session_start();

require_once('database.php');
require_once ('unblock_users.php');
checkAndUnblockUsers();

// Проверить, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit();
}

// Проверяем, был ли передан идентификатор пользователя в параметрах запроса
if (isset($_GET['users_id']) && is_numeric($_GET['users_id'])) {
    $userId = $_GET['users_id'];

    // Сохраняем идентификатор пользователя в сессии
    $_SESSION['current_users_id'] = $userId;

    // Выполняем запрос к базе данных для получения информации о пользователе и его группе
    $db_conn = databaseConnection();
    $query = "SELECT u.*, r.name_user_role 
              FROM phprequest_schema.users u
              JOIN phprequest_schema.users_roles r ON u.role_id = r.id_user_role
              WHERE users_id = $1";
    $result = pg_query_params($db_conn, $query, array($userId));

    if (!$result) {
        $_SESSION['edit_user_error'] = 'Ошибка при получении информации о пользователе';
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }

    // Проверяем количество строк в результате запроса
    if (pg_num_rows($result) === 0) {
        $_SESSION['edit_user_error'] = 'Пользователь не найден';
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }

    // Получаем данные пользователя
    $user = pg_fetch_assoc($result);

    // Вычисляем дату истечения срока действия пароля (например, 90 дней с текущей даты)
    $expiry_date = date('Y-m-d H:i:s', strtotime('+90 days', strtotime($user['password_last_changed_at'])));

    // Проверяем, был ли установлен чекбокс для бессрочного срока действия пароля
    $unlimited_password_expiry = ($user['unlimited_password_expiry'] === 'TRUE');
} else {
    // Если идентификатор пользователя не был передан или не является числом, перенаправляем обратно на страницу admin.php
    $_SESSION['edit_user_error'] = 'Некорректный идентификатор пользователя';
    header('Location: /phprequest/src/pages/admin.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/phprequest/src/styles/edit_user.css">
    <title>Редактирование пользователя</title>
</head>
<body>
<header class="header-section center">
    <h1 class="header-content-header">Редактирование данных пользователя</h1>
    <?php if (isset($_SESSION['edit_user_error'])): ?>
        <p class="error-message"><?php echo $_SESSION['edit_user_error']; ?></p>
        <?php unset($_SESSION['edit_user_error']); ?>
    <?php endif; ?>
    <form class="update-user-form" action="/phprequest/src/scripts/php/update_user.php" method="post">
        <input type="hidden" name="users_id" value="<?php echo $userId; ?>">
        <label for="login">Логин:</label>
        <input type="text" id="login" name="login" value="<?php echo $user['login']; ?>" required>
        <label for="employee_last_name">Фамилия:</label>
        <input type="text" id="employee_last_name" name="employee_last_name" value="<?php echo $user['employee_last_name']; ?>" required>
        <label for="employee_first_name">Имя:</label>
        <input type="text" id="employee_first_name" name="employee_first_name" value="<?php echo $user['employee_first_name']; ?>" required>
        <label for="employee_middle_name">Отчество (при наличии):</label>
        <input type="text" id="employee_middle_name" name="employee_middle_name" value="<?php echo $user['employee_middle_name']; ?>">
        <label for="role_id">Группа пользователя:</label>
        <select id="role_id" name="role_id">
            <?php
            // Выводим варианты групп пользователей из базы данных
            $rolesQuery = "SELECT * FROM phprequest_schema.users_roles";
            $rolesResult = pg_query($db_conn, $rolesQuery);
            if ($rolesResult && pg_num_rows($rolesResult) > 0) {
                while ($row = pg_fetch_assoc($rolesResult)) {
                    // Помечаем выбранным текущую группу пользователя
                    $selected = ($user['role_id'] == $row['id_user_role']) ? 'selected' : '';
                    echo "<option value=\"{$row['id_user_role']}\" $selected>{$row['name_user_role']}</option>";
                }
            }
            ?>
        </select>
        <label id="unlimited-password-expiry-label" for="unlimited_password_expiry">Сделать текущий пароль бессрочным?
            <input type="checkbox" id="unlimited_password_expiry" name="unlimited_password_expiry" <?php echo ($unlimited_password_expiry) ? 'checked' : ''; ?>>
        </label>

        <button class="update-user-button" type="submit">Сохранить изменения</button>
    </form>
</header>

<article class="article-section center">
    <section class="main-section-content">
        <h1 class="main-content-header">Управление блокировкой/разблокировкой пользователя</h1>
        <?php if (isset($_SESSION['update_user_blocked_error'])): ?>
            <p class="error-message"><?php echo $_SESSION['update_user_blocked_error']; ?></p>
            <?php unset($_SESSION['update_user_blocked_error']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['update_user_blocked_success'])): ?>
            <p class="success-message"><?php echo $_SESSION['update_user_blocked_success']; ?></p>
            <?php unset($_SESSION['update_user_blocked_success']); ?>
        <?php endif; ?>
        <form class="block-unblock-users-form" action="/phprequest/src/scripts/php/update_user_blocked.php" method="post">
            <input type="hidden" name="users_id" value="<?php echo $userId; ?>">
            <label for="blocked"><span class="block-unblock-users-form-warning">Отсутствие галочки снимет блокировку!</span><br> Установить блокировку?</label>
            <input type="checkbox" id="blocked" name="blocked" <?php echo ($user['blocked'] === 'TRUE') ? 'checked' : ''; ?>>
            <label for="blocked_until"><span class="block-unblock-users-form-warning">Пустая графа будет означать бессрочную блокировку!</span><br>Установить время до разблокировки?</label>
            <input type="datetime-local" id="blocked_until" name="blocked_until" value="<?php echo ($user['blocked_until'] !== null) ? date('Y-m-d\TH:i', strtotime($user['blocked_until'])) : ''; ?>">
            <button class="update-block-unblock-status-button" type="submit">Применить изменения</button>
        </form>
    </section>
    <section class="main-section-content">
        <h1 class="main-content-header">Изменение пароля пользователя</h1>
        <?php if (isset($_SESSION['chpasswd_success'])): ?>
            <p class="success-message"><?php echo $_SESSION['chpasswd_success']; ?></p>
            <?php unset($_SESSION['chpasswd_success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['chpasswd_error'])): ?>
            <p class="error-message"><?php echo $_SESSION['chpasswd_error']; ?></p>
            <?php unset($_SESSION['chpasswd_error']); ?>
        <?php endif; ?>
        <form class="change-password-form" action="/phprequest/src/scripts/php/chpasswd.php" method="post" id="change-password-form">
            <input type="hidden" name="users_id" value="<?php echo $userId; ?>">
            <label for="new-password">Новый пароль:</label>
            <input type="password" id="new-password" name="new-password" placeholder="Введите новый пароль" required>
            <label for="new-password-check">Подтверждение пароля:</label>
            <input type="password" id="new-password-check" name="new-password-check" placeholder="Введите пароль ещё раз" required>
            <button class="update-user-password-button" type="submit">Изменить пароль</button>
        </form>
    </section>
</article>

<footer class="footer-section center">
    <a href="/phprequest/src/pages/admin.php" class="back-to-admin-page-link">Вернуться на страницу администрирования</a>
</footer>
</body>
</html>

<?php
// Закрываем соединение с базой данных
pg_close($db_conn);
?>
