<?php
session_start();
require_once('database.php');
require_once('unblock_users.php');
checkAndUnblockUsers();

if (!isset($_SESSION['user'])) {
    header('Location: /phprequest/index.php');
    exit();
}

if (isset($_GET['users_id']) && is_numeric($_GET['users_id'])) {
    $userId = $_GET['users_id'];
    $_SESSION['current_users_id'] = $userId;

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

    if (pg_num_rows($result) === 0) {
        $_SESSION['edit_user_error'] = 'Пользователь не найден';
        header('Location: /phprequest/src/pages/admin.php');
        exit();
    }

    $user = pg_fetch_assoc($result);
    $unlimited_password_expiry = $user['unlimited_password_expiry'] === 't';
    $blocked = $user['blocked'] === 't';
    $blocked_until = $user['blocked_until'] ? date('Y-m-d\TH:i', strtotime($user['blocked_until'])) : '';
} else {
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
    <?php
    if (isset($_SESSION['update_user_success'])) {
        echo "<p class='success-message'>{$_SESSION['update_user_success']}</p>";
        unset($_SESSION['update_user_success']);
    } elseif (isset($_SESSION['update_user_error'])) {
        echo "<p class='error-message'>{$_SESSION['update_user_error']}</p>";
        unset($_SESSION['update_user_error']);
    }
    ?>
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
            $roleQuery = "SELECT id_user_role, name_user_role FROM phprequest_schema.users_roles";
            $roleResult = pg_query($db_conn, $roleQuery);
            if ($roleResult) {
                while ($role = pg_fetch_assoc($roleResult)) {
                    $selected = ($role['id_user_role'] == $user['role_id']) ? 'selected' : '';
                    echo "<option value='{$role['id_user_role']}' $selected>{$role['name_user_role']}</option>";
                }
            }
            ?>
        </select>
        <label for="unlimited_password_expiry">Сделать текущий пароль бессрочным?
            <input type="checkbox" id="unlimited_password_expiry" name="unlimited_password_expiry" <?php echo $unlimited_password_expiry ? 'checked' : ''; ?>>
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
            <input type="checkbox" id="blocked" name="blocked" <?php echo $blocked ? 'checked' : ''; ?>>
            <label for="blocked_until"><span class="block-unblock-users-form-warning">Пустая графа будет означать бессрочную блокировку!</span><br>Установить время до разблокировки?</label>
            <input type="datetime-local" id="blocked_until" name="blocked_until" value="<?php echo $blocked_until; ?>">
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
