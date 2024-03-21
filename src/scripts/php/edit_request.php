<?php
session_start();
require_once('database.php');
require_once('check_request_status.php');
// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit();
}

// Получаем данные авторизованного пользователя из сессии
$userRole = $_SESSION['user']['role_id'];
$userLogin = $_SESSION['user']['login'];

// Проверяем, был ли передан идентификатор запроса
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['requests_id'])) {
    $requestId = pg_escape_string(databaseConnection(), $_GET['requests_id']);

    // Выполняем запрос к базе данных для получения информации о запросе
    $query = "SELECT * FROM phprequest_schema.requests WHERE requests_id = $1";
    $result = pg_query_params(databaseConnection(), $query, array($requestId));

    // Проверяем, был ли найден запрос с указанным ID
    if ($result && pg_num_rows($result) > 0) {
        $requestData = pg_fetch_assoc($result);

        // Проверяем, имеет ли пользователь право редактировать запрос
        // Пользователь с логином "Requester" или с ролью 1 (Администратор) имеют доступ на редактирование
        if ($userLogin === 'Requester' || ($userRole == 1 || ($userRole == 2 && $requestData['request_status_id'] == 1))) {
            // Отображаем форму для редактирования запроса
            // Данные из базы данных используются для заполнения полей формы
            ?>

            <!DOCTYPE html>
            <html lang="ru">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <link rel="stylesheet" href="/phprequest/src/styles/edit_request.css">
                <title>Редактировать запрос</title>
            </head>
            <body>
            <header class="header-section center">
                <h1 class="header-content-header">Редактировать запрос</h1>
            </header>
            <article class="article-section center">
                <section class="main-section-content">
                    <?php
                    // Выводим ошибку, если таковая была установлена
                    if (isset($_SESSION['edit_request_error'])) {
                        echo "<p class='error-message'>Ошибка: {$_SESSION['edit_request_error']}</p>";
                        unset($_SESSION['edit_request_error']);
                    }
                    if(isset($_SESSION['edit_request_success'])) {
                        echo "<p class='success-message'> {$_SESSION['edit_request_success']}</p>";
                    }
                    ?>
                    <form class="edit-request-form" action="update_request.php" method="post" enctype="multipart/form-data">
                        <label for="snils_citizen">СНИЛС гражданина:</label>
                        <input type="text" id="snils_citizen" name="snils_citizen" value="<?php echo $requestData['snils_citizen']; ?>" required>

                        <label for="last_name_citizen">Фамилия гражданина:</label>
                        <input type="text" id="last_name_citizen" name="last_name_citizen" value="<?php echo $requestData['last_name_citizen']; ?>" required>

                        <label for="first_name_citizen">Имя гражданина:</label>
                        <input type="text" id="first_name_citizen" name="first_name_citizen" value="<?php echo $requestData['first_name_citizen']; ?>" required>

                        <label for="middle_name_citizen">Отчество гражданина:</label>
                        <input type="text" id="middle_name_citizen" name="middle_name_citizen" value="<?php echo $requestData['middle_name_citizen']; ?>">

                        <label for="birthday_citizen">Дата рождения гражданина:</label>
                        <input type="date" id="birthday_citizen" name="birthday_citizen" value="<?php echo $requestData['birthday_citizen']; ?>" required min="1900-01-01" max="2100-12-31">

                        <label for="requested_date_start">Дата начала запрашиваемого периода:</label>
                        <input type="date" id="requested_date_start" name="requested_date_start" value="<?php echo $requestData['requested_date_start']; ?>" required min="1900-01-01" max="2100-12-31">

                        <label for="requested_date_end">Дата окончания запрашиваемого периода:</label>
                        <input type="date" id="requested_date_end" name="requested_date_end" value="<?php echo $requestData['requested_date_end']; ?>" required min="1900-01-01" max="2100-12-31">

                        <?php if ($userRole != 2): ?>
                            <label for="request_status_id">Статус запроса:</label>
                            <select id="request_status_id" name="request_status_id" required>
                        <?php
                        // Запрос для получения текстовых обозначений статусов запросов из базы данных
                        $statusQuery = "SELECT request_statuses.request_statuses_id, status_text FROM phprequest_schema.request_statuses WHERE request_statuses_id != 1"; // Исключаем статус "Запрос создан"
                        $statusResult = pg_query(databaseConnection(), $statusQuery);

                                if ($statusResult) {
                                    // Выводим каждый вариант статуса в виде опции выпадающего списка
                                    while ($row = pg_fetch_assoc($statusResult)) {
                                        $selected = ($row['request_statuses_id'] == $requestData['request_status_id']) ? 'selected' : '';
                                        echo "<option value=\"{$row['request_statuses_id']}\" $selected>{$row['status_text']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        <?php endif; ?>

                        <?php if ($userRole != 2): ?>
                            <label for="file_upload">Загрузить новый файл:</label>
                            <input type="file" id="file_upload" name="file_upload">
                        <?php endif; ?>

                        <input type="hidden" name="requests_id" value="<?php echo $requestId; ?>">
                        <button class="apply-changes" type="submit">Применить изменения в запрос</button>
                    </form>
                </section>
                <section class="main-section-content center">
                    <div class="saved-file">
                        <?php if (!empty($requestData['download_link']) && $userRole != 2): ?>
                            <label class="saved-file-header">Действия с сохранённым файлом:</label>
                            <a class="download-file-link" href="<?php echo $requestData['download_link']; ?>" target="_blank">Скачать сохранённый файл</a>
                            <form action="update_request.php" method="post">
                                <input type="hidden" name="delete_file" value="true">
                                <input type="hidden" name="requests_id" value="<?php echo $requestId; ?>">
                                <button class="delete-file" type="submit">Удалить сохранённый файл</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            </article>
            <footer class="footer-section center">
                <a class="back-to-main-page-link" href="/phprequest/src/pages/main.php">Назад на главную страницу</a>
            </footer>
            <script>
                document.getElementById('snils_citizen').addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) {
                        value = value.substring(0, 11); // Ограничиваем ввод 11 символами
                    }
                    if (value.length > 2 && value.length < 6) {
                        value = value.replace(/^(\d{3})(\d{0,3}).*/, '$1-$2');
                    } else if (value.length >= 6 && value.length < 9) {
                        value = value.replace(/^(\d{3})(\d{3})(\d{0,3}).*/, '$1-$2-$3');
                    } else if (value.length >= 9) {
                        value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{0,2}).*/, '$1-$2-$3 $4');
                    }
                    e.target.value = value;
                });
            </script>
            </body>
            </html>

            <?php
            exit();
        } else {
            $_SESSION['edit_request_error'] = 'У вас нет прав на редактирование запросов';
        }
    } else {
        $_SESSION['edit_request_error'] = 'Запрос с указанным ID не найден';
    }
}

// Если скрипт дошел до этой точки, значит что-то пошло не так или пользователь не имеет прав на редактирование запроса
header('Location: /phprequest/src/pages/main.php');
exit();
?>
