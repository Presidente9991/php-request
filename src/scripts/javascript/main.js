// Функция обновления времени каждую секунду
function updateClock() {
    const now = new Date();
    let hours = now.getHours();
    let minutes = now.getMinutes();
    let seconds = now.getSeconds();

    // Добавить ведущий ноль, если число меньше 10
    hours = (hours < 10 ? "0" : "") + hours;
    minutes = (minutes < 10 ? "0" : "") + minutes;
    seconds = (seconds < 10 ? "0" : "") + seconds;

    // Обновить элемент с классом "clock" на текущее время
    document.querySelector('.current-clock').innerHTML = hours + ":" + minutes + ":" + seconds;

    // Вызвать эту функцию каждую секунду
    setTimeout(updateClock, 1000);
}

// Вызов функции обновления времени при загрузке страницы
window.onload = updateClock;

// Функция для обновления таблицы запросов актуальными данными
function updateRequestTable() {
    // Создаем объект XMLHttpRequest для работы с HTTP запросами
    const xhr = new XMLHttpRequest();

    // Получаем текущую дату из параметров URL страницы
    const urlParams = new URLSearchParams(window.location.search);
    const selectedDate = urlParams.get('date');

    // Проверяем, что выбранная дата не равна null
    if (selectedDate !== null) {
        // Настраиваем GET запрос к странице main.php с указанием выбранной даты
        xhr.open('GET', '/phprequest/src/pages/main.php?date=' + selectedDate, true);

        // Отправляем GET запрос
        xhr.send();

        // Обрабатываем ответ от сервера
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                // Создаем временный элемент div для парсинга HTML
                const tempDiv = document.createElement('div');
                // Устанавливаем полученный HTML-код во временный div
                tempDiv.innerHTML = xhr.responseText;
                // Находим элемент таблицы из временного div
                const newTable = tempDiv.querySelector('.request-table-container');
                // Получаем контейнер таблицы из основной страницы
                const requestTableContainer = document.getElementById('php-request-table-container');
                // Очищаем контейнер таблицы
                requestTableContainer.innerHTML = '';
                // Добавляем новую таблицу в контейнер
                requestTableContainer.appendChild(newTable);
            }
        };
    } else {
        console.error("Ошибка: Выбранная дата равна null");
    }
}

// Вызываем функцию для обновления таблицы каждые 5 секунд
setInterval(updateRequestTable, 5000); // Обновляем данные каждые 5 секунд