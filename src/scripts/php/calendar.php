<?php
session_start();

// Проверить, авторизован ли пользователь
if (!isset($_SESSION['user'])) {
    // Если пользователь не авторизован, перенаправляем его на страницу входа
    header('Location: /phprequest/index.php');
    exit();
}

require_once ('unblock_users.php');
checkAndUnblockUsers(); // Проверяем и разблокируем пользователей

// Функция генерации календаря. Для работы необходимо активировать расширение PHP - calendar !

// Установить дату для календаря
$selected_date = isset($_GET['date']) ? date('Y-m-d', strtotime($_GET['date'])) : date('Y-m-d');

// Получить текущий год и месяц
$current_year = date('Y', strtotime($selected_date));
$current_month = date('n', strtotime($selected_date));

// Получить кириллическое название текущего месяца
$months = [
    1 => 'Январь',
    2 => 'Февраль',
    3 => 'Март',
    4 => 'Апрель',
    5 => 'Май',
    6 => 'Июнь',
    7 => 'Июль',
    8 => 'Август',
    9 => 'Сентябрь',
    10 => 'Октябрь',
    11 => 'Ноябрь',
    12 => 'Декабрь'
];
$current_month_name = $months[$current_month];

// Функция для вывода календаря на странице
function generate_calendar($year, $month): string
{
    $calendar = '<div class="calendar-container">';
    // Сгенерировать календарный заголовок
    $calendar .= '<h2 class="calendar-label-current-month">' . $GLOBALS['current_month_name'] . ' ' . $year . '</h2>';
    $calendar .= '<table class="calendar-table">';
    $calendar .= '<thead class="calendar-table-header-days">
                        <tr class="calendar-table-header-row">
                            <th class="calendar-header-day">Пн</th>
                            <th class="calendar-header-day">Вт</th>
                            <th class="calendar-header-day">Ср</th>
                            <th class="calendar-header-day">Чт</th>
                            <th class="calendar-header-day">Пт</th>
                            <th class="calendar-header-day-weekend">Сб</th>
                            <th class="calendar-header-day-weekend">Вс</th>
                        </tr>
                    </thead>';
    $calendar .= '<tbody class="calendar-body">';

    // Получить день недели первого числа месяца
    $first_day = mktime(0, 0, 0, $month, 1, $year);
    $first_day_of_week = date('N', $first_day);

    // Получение количества дней в месяце
    $days_in_month = date('t', $first_day);

    // Начало новой строки для первого числа месяца
    $calendar .= '<tr class="calendar-body-value-row">';

    // Заполнение пустых ячеек до начала месяца
    for ($i = 1; $i < $first_day_of_week; $i++) {
        $calendar .= '<td class="calendar-body-value"></td>';
    }

    // Заполнение календаря днями месяца
    $day = 1;
    while ($day <= $days_in_month) {
        for ($i = $first_day_of_week; $i <= 7; $i++) {
            if ($day <= $days_in_month) {
                $calendar .= '<td class="calendar-body-value"><a class="calendar-body-value-link" href="?date=' . date('Y-m-d', mktime(0, 0, 0, $month, $day, $year)) . '">' . $day . '</a></td>';
                $day++;
            } else {
                $calendar .= '<td class="calendar-body-value-link"></td>';
            }
        }
        if ($day <= $days_in_month) {
            $calendar .= '</tr><tr class="calendar-body-value-row">';
        }
        $first_day_of_week = 1;
    }

    // Завершение таблицы
    $calendar .= '</tr>';
    $calendar .= '</tbody>';
    $calendar .= '</table>';
    $calendar .= '</div>';

    return $calendar;
}

$prev_month = date('Y-m-d', strtotime('-1 month', strtotime($selected_date)));
$next_month = date('Y-m-d', strtotime('+1 month', strtotime($selected_date)));
