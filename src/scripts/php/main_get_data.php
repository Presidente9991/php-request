<?php
// Запрещаем кэширование страницы
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

session_start();

// Проверить, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit();
}

require_once('database.php');
require_once('unblock_users.php');
checkAndUnblockUsers();

function generate_request_table($selected_date)
{
    $db_conn = databaseConnection(); // Получить соединение с PostgreSQL

    // Преобразовать выбранную дату в формат, понятный PostgreSQL
    $formatted_date = date('Y-m-d', strtotime($selected_date));

    // Запросить в PostgreSQL выборку данных из таблицы на основании выбранной даты
    $query = "SELECT requests_id, * FROM phprequest_schema.requests WHERE date_creation = '$formatted_date' ORDER BY time_creation DESC";

    // Выполнить запрос
    $result = pg_query($db_conn, $query);

    if (!$result) {
        die('Ошибка выполнения запроса: ' . pg_last_error($db_conn));
    }

    // Создать таблицу для отображения результатов
    $table = '<table class="request-content-table">
                <thead class="request-table-header">
                    <tr class="request-table-rows">
                        <th class="request-table-header">Дата создания</th>
                        <th class="request-table-header">Время создания</th>
                        <th class="request-table-header">ФИО автора запроса</th>
                        <th class="request-table-header">СНИЛС гражданина</th>
                        <th class="request-table-header">Фамилия гражданина</th>
                        <th class="request-table-header">Имя гражданина</th>
                        <th class="request-table-header">Отчество гражданина</th>
                        <th class="request-table-header">Дата рождения гражданина</th>
                        <th class="request-table-header">Запрашиваемый период - начало</th>
                        <th class="request-table-header">Запрашиваемый период - окончание</th>
                        <th class="request-table-header">Статус запроса</th>
                        <th class="request-table-header">Справка</th>
                        <th class="request-table-header">Редактировать</th>
                    </tr>
                </thead>
                <tbody class="request-table-body">';

    // Заполнить таблицу данными из результата запроса
    while ($row = pg_fetch_assoc($result)) {

        // Отформатировать время создания
        $formatted_time = date('H:i:s', strtotime($row['time_creation']));

        // Отформатировать значение столбца "ФИО автора запроса" в формат Фамилия, Имя, Отчество
        $author_info_query = "SELECT employee_first_name, employee_middle_name, employee_last_name  FROM phprequest_schema.users WHERE users_id = " . $row['users_id'];
        $author_info_result = pg_query($db_conn, $author_info_query);
        $author_info_row = pg_fetch_assoc($author_info_result);

        // Отформатировать значение столбца "СНИЛС" в формат XXX-XXX-XXX XX
        $formatted_snils = substr_replace($row['snils_citizen'], '-', 3, 0);
        $formatted_snils = substr_replace($formatted_snils, '-', 7, 0);
        $formatted_snils = substr_replace($formatted_snils, ' ', 11, 0);

        // Отформатировать значение столбцов "Дата создания", "Дата рождения гражданина", "Запрашиваемый период - начало", "Запрашиваемый период - окончание"
        $formatted_creation_date = date('d.m.Y', strtotime($row['date_creation']));
        $formatted_birthday = date('d.m.Y', strtotime($row['birthday_citizen']));
        $formatted_requested_date_start = date('d.m.Y', strtotime($row['requested_date_start']));
        $formatted_requested_date_end = date('d.m.Y', strtotime($row['requested_date_end']));

        // Запросить текстовое значение статуса
        $status_query = "SELECT status_text FROM phprequest_schema.request_statuses WHERE request_statuses_id = " . $row['request_status_id'];
        $status_result = pg_query($db_conn, $status_query);
        $status_row = pg_fetch_assoc($status_result);

        // Условие для определения цвета текста в зависимости от значения столбца status_text
        $status_text_color = match ($status_row['status_text']) {
            'Запрос создан' => '#008000',
            'Запрос принят в работу администратором' => '#ffc40c',
            'Ответ по запросу получен и обрабатывается' => '#ffa500',
            'Ответ обработан. Запрос закрыт' => '#ff0000',
            default => '#000000',
        };

        // Путь к файлу для скачивания
        $file_path = $row['download_link'];

        // Сформировать значения в ранее созданной таблице
        $table .= '<tr class="request-table-rows">';
        $table .= '<td class="request-table-value">' . $formatted_creation_date . '</td>';
        $table .= '<td class="request-table-value">' . $formatted_time . '</td>';
        $table .= '<td class="request-table-value">' . $author_info_row['employee_last_name'] . ' ' . $author_info_row['employee_first_name'] . ' ' . $author_info_row['employee_middle_name'] . '</td>';
        $table .= '<td class="request-table-value">' . $formatted_snils . '</td>';
        $table .= '<td class="request-table-value">' . $row['last_name_citizen'] . '</td>';
        $table .= '<td class="request-table-value">' . $row['first_name_citizen'] . '</td>';
        $table .= '<td class="request-table-value">' . $row['middle_name_citizen'] . '</td>';
        $table .= '<td class="request-table-value">' . $formatted_birthday . '</td>';
        $table .= '<td class="request-table-value">' . $formatted_requested_date_start . '</td>';
        $table .= '<td class="request-table-value">' . $formatted_requested_date_end . '</td>';
        $table .= '<td class="request-table-value" style="color: ' . $status_text_color . ';">' . $status_row['status_text'] . '</td>';
        // Проверка на NULL в столбце download_link
        if ($file_path === null) {
            // Если download_link равен NULL, не создавать ссылку на скачивание
            $table .= '<td class="request-table-value" style="color: #ff0000">Ответ не готов</td>';
        } else {
            // Если download_link не равен NULL, создать ссылку на скачивание файла
            $table .= '<td class="request-table-value"><a class="request-table-value-link" href="' . $file_path . '" download>Скачать</a></td>';
        }

        // Получение статуса запроса из текущей строки результата запроса
        $requestStatusId = $row['request_status_id'];

        // Проверка прав доступа к редактированию запроса
        if ($_SESSION['user']['login'] === "Requester" && $_SESSION['user']['role_id'] == 1) {
            // Разрешаем доступ для администратора с логином "Requester"
            $table .= '<td class="request-table-value"><a class="table-value-link" href="/phprequest/src/scripts/php/edit_request.php?requests_id=' . $row['requests_id'] . '">Редактировать</a></td>';
        } elseif ($requestStatusId == 1) {
            // Разрешаем доступ для всех при статусе "Запрос создан"
            $table .= '<td class="request-table-value"><a class="table-value-link" href="/phprequest/src/scripts/php/edit_request.php?requests_id=' . $row['requests_id'] . '">Редактировать</a></td>';
        } elseif ($requestStatusId == 4) {
            // Запрещаем доступ при статусе "Запрос закрыт"
            $table .= '<td class="request-table-value" style="color: #ff0000">Запрос закрыт</td>';
        } elseif ($_SESSION['user']['role_id'] == 1) {
            // Разрешаем доступ для администраторов при других статусах запроса
            $table .= '<td class="request-table-value"><a class="table-value-link" href="/phprequest/src/scripts/php/edit_request.php?requests_id=' . $row['requests_id'] . '">Редактировать</a></td>';
        } else {
            // Запрещаем доступ для пользователей при других статусах запроса
            $table .= '<td class="request-table-value" style="color: #ff0000">Запрещено</td>';
        }
        $table .= '</tr>';
    }
    $table .= '</tbody></table>';

    // Закрыть соединение с базой данных
    pg_close($db_conn);

    //Вернуть таблицу в виде переменной $table
    return $table;
}
