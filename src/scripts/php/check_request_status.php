<?php
require_once('database.php');

// Проверить, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit();
}

// Получаем список запросов, у которых download_link равен NULL и request_status_id равен 4
$query = "SELECT requests_id FROM phprequest_schema.requests WHERE download_link IS NULL AND request_status_id = 4";
$result = pg_query(databaseConnection(), $query);

if ($result) {
    while ($row = pg_fetch_assoc($result)) {
        $requestId = $row['requests_id'];

        // Обновляем статус запроса на "В работе" (request_status_id = 2)
        $updateQuery = "UPDATE phprequest_schema.requests SET request_status_id = 2 WHERE requests_id = '$requestId'";
        $updateResult = pg_query(databaseConnection(), $updateQuery);

        if (!$updateResult) {
            $_SESSION['edit_request_error'] = 'Ошибка при обновлении статуса запроса';
        }
    }
}
