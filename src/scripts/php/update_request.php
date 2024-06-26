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

$requestId = null; // Инициализация переменной $requestId

// Проверяем, был ли отправлен POST-запрос для обновления данных запроса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['requests_id']) && !isset($_POST['delete_file'])) {
    // Получаем и экранируем данные из POST-запроса
    $requestId = pg_escape_string(databaseConnection(), $_POST['requests_id']);
    $snilsWithDashes = $_POST['snils_citizen'];
    $snils = preg_replace('/\D/', '', $snilsWithDashes); // Удаляем все символы кроме цифр
    $snils = substr($snils, 0, 11); // Ограничиваем до 11 символов
    $lastName = pg_escape_string(databaseConnection(), $_POST['last_name_citizen']);
    $firstName = pg_escape_string(databaseConnection(), $_POST['first_name_citizen']);
    $middleName = !empty($_POST['middle_name_citizen']) ? pg_escape_string(databaseConnection(), $_POST['middle_name_citizen']) : null;
    $birthday = pg_escape_string(databaseConnection(), $_POST['birthday_citizen']);
    $startDate = pg_escape_string(databaseConnection(), $_POST['requested_date_start']);
    $endDate = pg_escape_string(databaseConnection(), $_POST['requested_date_end']);
    $requestStatusId = null; // Инициализация переменной $requestStatusId

    // Определяем статус запроса в зависимости от наличия или отсутствия файла в download_link
    if (!empty($_FILES['file_upload']['name'])) {
        // Если файл загружен, статус запроса становится "Ответ обработан. Запрос закрыт"
        $requestStatusId = 4;
    } else {
        // Если файл не загружен, определяем текущий статус запроса
        $currentStatusQuery = "SELECT request_status_id, download_link FROM phprequest_schema.requests WHERE requests_id = '$requestId'";
        $currentStatusResult = pg_query(databaseConnection(), $currentStatusQuery);

        if ($currentStatusResult && pg_num_rows($currentStatusResult) > 0) {
            $row = pg_fetch_assoc($currentStatusResult);
            $currentStatus = $row['request_status_id'];
            $downloadLink = $row['download_link'];

            if ($downloadLink !== null) {
                // Если есть ссылка на файл, статус запроса сначала "Ответ по запросу получен и обрабатывается"
                $requestStatusId = 3;
            } else {
                // Если нет ссылки на файл, статус запроса "Запрос принят в работу администратором"
                $requestStatusId = 2;
            }
        }
    }

    // Получить текущую дату для использования в запросах к базе данных
    $currentDate = date('Y-m-d');

    // Проверяем корректность данных перед их использованием
    $errors = array();

    // Проверка корректности СНИЛС
    if (!preg_match('/^\d{11}$/', $snils)) {
        $errors[] = 'Некорректный формат СНИЛСа. СНИЛС должен содержать 11 цифр.';
    }

    // Проверка корректности даты рождения
    if (!strtotime($birthday) || $birthday > $currentDate) {
        $errors[] = 'Некорректная дата рождения. Убедитесь, что вы ввели дату в правильном формате и она не больше текущей даты.';
    }

    // Проверка корректности запрашиваемого периода
    if (!strtotime($startDate) || !strtotime($endDate) || $endDate < $startDate) {
        $errors[] = 'Некорректный запрашиваемый период. Убедитесь, что вы ввели даты в правильном формате и дата окончания больше даты начала.';
    }

    // Проверка на кириллические символы в фамилии, имени и отчестве
    if (!preg_match('/^[а-яёА-ЯЁ\s-]+$/u', $lastName)) {
        $errors[] = 'Фамилия должна содержать только кириллические символы, пробелы и дефисы.';
    }
    if (!preg_match('/^[а-яёА-ЯЁ\s-]+$/u', $firstName)) {
        $errors[] = 'Имя должно содержать только кириллические символы, пробелы и дефисы.';
    }
    if (!empty($middleName) && !preg_match('/^[а-яёА-ЯЁ\s-]*$/u', $middleName)) {
        $errors[] = 'Отчество должно содержать только кириллические символы, пробелы и дефисы.';
    }

    // Проверяем MIME-тип загруженного файла
    if(isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
        $allowedMimeTypes = ['application/pdf', 'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

        // Получаем MIME-тип файла
        $fileMimeType = mime_content_type($_FILES['file_upload']['tmp_name']);

        // Проверяем, соответствует ли MIME-тип разрешенным типам
        if (!in_array($fileMimeType, $allowedMimeTypes)) {
            $_SESSION['edit_request_error'] = 'Недопустимый формат файла. Разрешены только PDF, ODT, ODS, DOC, DOCX, XLS и XLSX.';
            header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
            exit();
        }
    }

    // Если есть ошибки, объединяем их в одну строку
    if (!empty($errors)) {
        $_SESSION['edit_request_error'] = implode("<br>", $errors);
        header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
        exit();
    }

    // Получаем текущую ссылку на файл из базы данных
    $queryGetFile = "SELECT download_link FROM phprequest_schema.requests WHERE requests_id = '$requestId'";
    $resultGetFile = pg_query(databaseConnection(), $queryGetFile);

    if ($resultGetFile && pg_num_rows($resultGetFile) > 0) {
        $row = pg_fetch_assoc($resultGetFile);
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $row['download_link'];

        // Удаляем предыдущий файл, если он существует
        if (!empty($row['download_link']) && file_exists($filePath)) {
            if (unlink($filePath)) {
                // Успешно удалили предыдущий файл
                $_SESSION['edit_request_success'] = 'Предыдущий файл успешно удален';
            } else {
                $_SESSION['edit_request_error'] = 'Ошибка при удалении предыдущего файла';
                header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
                exit();
            }
        }
    }

    // Тогда выполняем запрос на обновление данных запроса
    $query = "UPDATE phprequest_schema.requests 
        SET snils_citizen = '$snils',
            last_name_citizen = '$lastName',
            first_name_citizen = '$firstName',
            middle_name_citizen = '$middleName',
            birthday_citizen = '$birthday',
            requested_date_start = '$startDate',
            requested_date_end = '$endDate',
            request_status_id = '$requestStatusId'";

    // Если файл был загружен
    if(isset($_FILES['file_upload']) && $_FILES['file_upload']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '/phprequest/documents/'; // Каталог для сохранения загруженных файлов
        $uploadFile = $uploadDir . basename($_FILES['file_upload']['name']);

        // Генерируем уникальное имя файла
        $uniqueFileName = uniqid() . '_' . $_FILES['file_upload']['name'];
        $uploadFile = $uploadDir . $uniqueFileName;

        // Перемещаем загруженный файл в указанную директорию
        if(move_uploaded_file($_FILES['file_upload']['tmp_name'], $_SERVER['DOCUMENT_ROOT'] . $uploadFile)) {
            // Добавляем поле download_link к запросу
            $query .= ", download_link = '$uploadFile'";
        } else {
            $_SESSION['edit_request_error'] = 'Ошибка при загрузке файла на сервер';
            header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
            exit();
        }
    }

    // Дополняем запрос условием WHERE
    $query .= " WHERE requests_id = '$requestId'";
    $result = pg_query(databaseConnection(), $query);

    // Проверяем результат выполнения запроса
    if ($result) {
        $_SESSION['edit_request_success'] = 'Данные запроса успешно обновлены';
    } else {
        $_SESSION['edit_request_error'] = 'Ошибка при обновлении данных запроса: ' . pg_last_error(databaseConnection());
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['requests_id']) && isset($_POST['delete_file'])) {
    // Пользователь запросил удаление файла

    $requestId = pg_escape_string(databaseConnection(), $_POST['requests_id']);

    // Получаем текущую ссылку на файл из базы данных
    $queryGetFile = "SELECT download_link FROM phprequest_schema.requests WHERE requests_id = '$requestId'";
    $resultGetFile = pg_query(databaseConnection(), $queryGetFile);

    if ($resultGetFile && pg_num_rows($resultGetFile) > 0) {
        $row = pg_fetch_assoc($resultGetFile);
        $filePath = $_SERVER['DOCUMENT_ROOT'] . $row['download_link'];

        // Удаляем файл, если он существует
        if (file_exists($filePath) && !empty($row['download_link'])) {
            if (unlink($filePath)) {
                // Успешно удалили файл, обновляем ссылку и статус
                if (updateRequestFileLinkAndStatus($requestId)) {
                    $_SESSION['edit_request_success'] = 'Файл успешно удален и статус запроса изменен';
                } else {
                    $_SESSION['edit_request_error'] = 'Ошибка при обновлении статуса запроса';
                }
            } else {
                $_SESSION['edit_request_error'] = 'Ошибка при удалении файла';
            }
        } else {
            // Если файла нет, обновляем ссылку на файл в базе данных на NULL
            if (updateRequestFileLinkAndStatus($requestId)) {
                $_SESSION['edit_request_success'] = 'Файл успешно удален и статус запроса изменен';
            } else {
                $_SESSION['edit_request_error'] = 'Ошибка при обновлении статуса запроса';
            }
        }
    } else {
        $_SESSION['edit_request_error'] = 'Ошибка при получении ссылки на файл';
    }
} else {
    $_SESSION['edit_request_error'] = 'Некорректный запрос';
}

// Функция для обновления ссылки на файл и статуса запроса
function updateRequestFileLinkAndStatus($requestId) {
    $queryDeleteFile = "UPDATE phprequest_schema.requests SET download_link = NULL WHERE requests_id = '$requestId'";
    $resultDeleteFile = pg_query(databaseConnection(), $queryDeleteFile);
    if ($resultDeleteFile) {
        $queryUpdateStatus = "UPDATE phprequest_schema.requests SET request_status_id = 4 WHERE requests_id = '$requestId'";
        return pg_query(databaseConnection(), $queryUpdateStatus);
    }
    return false;
}

// Перенаправляем пользователя на страницу редактирования запроса
header('Location: /phprequest/src/scripts/php/edit_request.php?requests_id=' . $requestId);
exit();