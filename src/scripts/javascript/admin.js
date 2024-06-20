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

    // Обновить элемент с классом "current-clock" на текущее время
    document.querySelector('.current-clock').innerHTML = hours + ":" + minutes + ":" + seconds;

    // Вызвать эту функцию каждую секунду
    setTimeout(updateClock, 1000);
}
// Вызов функции обновления времени при загрузке страницы
window.onload = updateClock;

// Функция для настройки индикатора силы пароля
function setupPasswordStrengthIndicator() {
    document.getElementById("password").addEventListener("input", function() {
        const password = this.value;
        const result = checkPasswordRequirements(password);
        const indicator = document.getElementById("password-strength-indicator");
        indicator.textContent = result.text;
        indicator.className = result.class;
    });
}

// Функция для проверки силы пароля на основе требований
function checkPasswordRequirements(password) {
    const requirements = {
        'length': password.length >= 8,
        'no-cyrillic': !/[а-яА-Я]/.test(password),
        'uppercase': /[A-ZА-Я]/.test(password),
        'lowercase': /[a-zа-я]/.test(password),
        'number': /\d/.test(password),
        'special': /[!"$%&'()+,\-.\/:;<=>?@\[\]^_{|}~`-]/.test(password),
        'no-sequential': !(hasSequentialLetters(password) || hasSequentialDigits(password)),
        'no-spaces': !/\s/.test(password)
    };

    // Проверка каждого требования и установка соответствующих классов
    for (const requirement in requirements) {
        const element = document.getElementById(requirement);
        if (requirements[requirement]) {
            element.classList.remove('red');
            element.classList.add('green');
        } else {
            element.classList.remove('green');
            element.classList.add('red');
        }
    }

    // Проверка на использование распространённых паролей
    if (isCommonPassword(password)) {
        return { text: "Распространённый пароль! Использование опасно!", class: "red" };
    } else {
        return { text: "" };
    }
}

// Функция для проверки наличия последовательных букв в пароле
function hasSequentialLetters(password) {
    const qwerty = ['qwertyuiop', 'asdfghjkl', 'zxcvbnm', 'йцукенгшщзхъ', 'фывапролджэ', 'ячсмитьбю'];
    for (let i = 0; i < qwerty.length; i++) {
        for (let j = 0; j <= qwerty[i].length - 4; j++) {
            const sequence = qwerty[i].substring(j, j + 4);
            if (password.toLowerCase().includes(sequence) || password.toLowerCase().includes(sequence.split('').reverse().join(''))) {
                return true;
            }
        }
    }
    // Проверка на кириллические символы
    return /(.)\1{3}/.test(password.toLowerCase());
}

// Функция для проверки наличия последовательных цифр в пароле
function hasSequentialDigits(password) {
    const digits = '01234567890';
    for (let i = 0; i <= digits.length - 4; i++) {
        const sequence = digits.substring(i, i + 4);
        if (password.includes(sequence) || password.includes(sequence.split('').reverse().join(''))) {
            return true;
        }
    }
    return false;
}

// Функция для проверки, является ли пароль распространённым
function isCommonPassword(password) {
    const commonPasswords = [
        "password",
        "12345",
        "qwerty",
        "qwertyui",
        "qwertyuio",
        "qwertyuiop",
        "asdfg",
        "asdfgh",
        "asdfghj",
        "asdfghjk",
        "asdfghjkl",
        "zxcvb",
        "zxcvbn",
        "zxcvbnm",
        "123456",
        "12345678",
        "abc123",
        "password1",
        "1234567",
        "123123",
        "admin",
        "123456789"
    ];

    return commonPasswords.includes(password);
}

// Вызов функции для настройки индикатора силы пароля при загрузке страницы
setupPasswordStrengthIndicator();

// Функция для обновления таблицы пользователей актуальными данными
function updateUsersTable() {
    // Находим текущую таблицу пользователей
    const currentTable = document.getElementById('php-users-table-container');
    // Запоминаем старое содержимое таблицы
    const oldTableContent = currentTable.innerHTML;

    // Создаем новый запрос на страницу admin.php, чтобы получить обновленное содержимое таблицы
    const xhr = new XMLHttpRequest();
    xhr.open('GET', '/phprequest/src/pages/admin.php', true);

    // Обрабатываем ответ от сервера
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                // Получаем HTML-код обновленной страницы admin.php
                const updatedPageHTML = xhr.responseText;
                // Создаем временный элемент div для парсинга HTML
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = updatedPageHTML;
                // Находим обновленное содержимое таблицы
                const newTable = tempDiv.querySelector('.users-table-container');
                // Заменяем содержимое текущей таблицы на обновленное
                currentTable.innerHTML = newTable.innerHTML;
            } else {
                // Если возникла ошибка при обновлении, восстанавливаем старое содержимое таблицы
                currentTable.innerHTML = oldTableContent;
                console.error("Ошибка: данные не обновлены");
            }
        }
    };

    // Отправляем запрос
    xhr.send();
}

// Вызываем функцию для обновления таблицы каждые 5 секунд
setInterval(updateUsersTable, 5000);
