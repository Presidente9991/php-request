<?php
session_start(); // Инициализация сессии для хранения временных данных, таких как сообщения об ошибках или успехах.

require_once('database.php'); // Подключение файла с функциями для работы с базой данных.
require_once('unblock_users.php'); // Подключение файла для разблокировки пользователей.
checkAndUnblockUsers(); // Проверка и разблокировка пользователей, если необходимо.

// Проверить, авторизован ли пользователь
if (!isset($_SESSION['user'])) { // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit(); // Завершаем выполнение скрипта
}

$blockedUntilTimestamp = null; // Инициализация переменной для хранения даты разблокировки.

// Проверяем, были ли переданы все необходимые параметры запроса
if (!isset($_POST['users_id'])) {
    $_SESSION['update_user_blocked_error'] = 'Некорректные параметры запроса.';
    header('Location: /phprequest/src/pages/admin.php');
    exit(); // Завершаем выполнение скрипта
}

$userId = $_POST['users_id']; // Получаем идентификатор пользователя из POST-запроса.

// Проверяем, был ли чекбокс активирован
$blocked = isset($_POST['blocked']) && $_POST['blocked'] === 'on';

// Если чекбокс "Заблокировать пользователя?" не активирован, отклоняем попытки установки даты разблокировки
if (!$blocked && !empty($_POST['blocked_until'])) {
    $_SESSION['update_user_blocked_error'] = 'Нельзя устанавливать время до разблокировки пользователю, которому не устанавливается блокировка. Очистите время до разблокировки!';
    header('Location: /phprequest/src/scripts/php/edit_user.php?users_id=' . $userId);
    exit();
}

// Преобразуем значение даты и времени, если оно передано
$blockedUntil = !empty($_POST['blocked_until']) ? $_POST['blocked_until'] : null;

// Проверяем, была ли отправлена дата в столбец blocked_until
if (!empty($blockedUntil)) {
    // Проверяем, является ли отправленная дата будущей для сервера
    $currentTimestamp = strtotime(date('Y-m-d H:i:s'));
    $blockedUntilTimestamp = strtotime($blockedUntil);
    if ($blockedUntilTimestamp < $currentTimestamp) { // Проверка, что дата разблокировки не прошедшая.
        $_SESSION['update_user_blocked_error'] = 'В дату разблокировки недопустимо устанавливать уже прошедшую дату!';
        header('Location: /phprequest/src/scripts/php/edit_user.php?users_id=' . $userId);
        exit();
    }
}

// Функция для обнуления срока блокировки
if (!empty($blockedUntil) || $blocked) {
    // Проверяем, является ли отправленная дата будущей для сервера
    $currentTimestamp = strtotime(date('Y-m-d H:i:s'));
    $blockedUntilTimestamp = strtotime($blockedUntil);
    if ($blockedUntilTimestamp > $currentTimestamp || $blocked) { // Если дата будущая или чекбокс активирован, сохраняем её в столбце blocked_until до достижения текущей даты сервера
        $blocked = true; // Устанавливаем статус блокировки
    } else {
        $blocked = false; // Если дата уже прошла и чекбокс не активирован, оставляем текущее значение блокировки без изменений
        $blockedUntil = null;
    }
} else {
    $blockedUntil = null; // Если дата не указана и чекбокс не активирован, сбрасываем срок блокировки
}

// Выполняем запрос к базе данных для обновления статуса блокировки пользователя
$db_conn = databaseConnection(); // Устанавливаем соединение с базой данных.

// Формируем запрос к базе данных для обновления статуса блокировки пользователя
$query = "UPDATE phprequest_schema.users 
          SET blocked = $1, blocked_until = $2 
          WHERE users_id = $3";

// Определяем параметры для запроса
$params = array($blocked, $blockedUntil, $userId);

// Если чекбокс не был активирован, устанавливаем значение false для статуса блокировки
if (!$blocked) {
    $params[0] = 'false';
}

// Выполняем запрос с использованием параметризованных значений
$result = pg_query_params($db_conn, $query, $params); // Выполнение запроса с параметрами.

// Проверяем результат выполнения запроса
if ($result) {
    // Обновляем значение сессионной переменной ['blocked'] в зависимости от значения $blocked
    $_SESSION['user']['blocked'] = $blocked;

    // Проверяем условия и формируем соответствующие сообщения
    if ($blocked && $blockedUntil) {
        $_SESSION['update_user_blocked_success'] = 'Пользователь успешно заблокирован до ' . date('d.m.Y H:i:s', $blockedUntilTimestamp);
    } elseif ($blocked && !$blockedUntil) {
        $_SESSION['update_user_blocked_success'] = 'Пользователь успешно заблокирован на неопределённый срок.';
    } elseif (!$blocked && !$blockedUntil) {
        $_SESSION['update_user_blocked_success'] = 'Блокировка с пользователя была успешно снята.';
    }
} else {
    // Если произошла ошибка, получаем последнюю ошибку и выводим её
    $error_message = pg_last_error($db_conn);
    $_SESSION['update_user_blocked_error'] = 'Ошибка при обновлении статуса блокировки пользователя: ' . $error_message;
}

// Перенаправляем пользователя обратно на страницу редактирования пользователя
header('Location: /phprequest/src/scripts/php/edit_user.php?users_id=' . $userId);
exit(); // Завершаем выполнение скрипта.