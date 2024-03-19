<?php
session_start();

// Защитить файл unblock_users.php от просмотра в браузерах
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header("Location: /phprequest/index.php");
    exit();
}

require_once('database.php');

// Функция для проверки и снятия блокировки у пользователей с истекшим сроком
function checkAndUnblockUsers(): void
{
    $db_conn = databaseConnection(); // Используем функцию из database.php
    $query = "SELECT users_id FROM phprequest_schema.users WHERE blocked = true AND blocked_until <= NOW()";
    $result = pg_query($db_conn, $query);

    if ($result) {
        while ($row = pg_fetch_assoc($result)) {
            $userId = $row['users_id'];
            // Снимаем блокировку для пользователя
            $updateQuery = "UPDATE phprequest_schema.users SET blocked = false, blocked_until = NULL WHERE users_id = $userId";
            $updateResult = pg_query($db_conn, $updateQuery);
            if ($updateResult) {
                $_SESSION['unblock_users_message'] .= "Пользователь с ID $userId был разблокирован автоматически в связи с окончанием срока блокировки.<br>";
            } else {
                $_SESSION['unblock_users_message'] .= "Не удалось разблокировать пользователя с ID $userId.<br>";
            }
        }
    } else {
        $_SESSION['unblock_users_message'] .= "Не удалось получить заблокированных пользователей из базы данных.<br>";
    }

    // Закрываем соединение с базой данных
    pg_close($db_conn);
}

// Проверяем статус блокировки пользователя и принудительно завершаем сеанс, если пользователь заблокирован
checkAndUnblockUsers();
