<?php
session_start();

// Уничтожаем сессию пользователя
session_destroy();

// Перенаправляем пользователя на главную страницу после выхода
header('Location: /phprequest/index.php');
exit();