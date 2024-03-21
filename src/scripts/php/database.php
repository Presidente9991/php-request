<?php
// Защитить файл database.php от просмотра в браузерах
if (basename(__FILE__) == basename($_SERVER["SCRIPT_FILENAME"])) {
    header("Location: /phprequest/index.php");
    exit();
}

// Функция по подключению к базе данных PostgreSQL. Для успешного подключения необходимо указать корректные параметры!
function databaseConnection()
{
    $database_host = 'localhost'; // Адрес хоста с базой данных
    $database_port = '5432'; // Порт базы данных
    $database_name = 'phprequest'; // Имя базы данных
    $database_user = 'pgsql'; // Логин пользователя базы данных
    $database_password = 'uncWYee7'; // Пароль пользователя базы данных

    // Записать в отдельную переменную все вышеуказанные параметры подключения и произвести подключение к PostgreSQL
    $db_conn = pg_connect("host=$database_host port=$database_port dbname=$database_name user=$database_user password=$database_password");

    if(!$db_conn) {
        die('Ошибка подключения к базе данных');
    }
    // Вернуть значение переменной для возможности дальнейшего использования этой функции вне этого файла
    return $db_conn;
}