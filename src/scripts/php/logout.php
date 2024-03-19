<?php
session_start();
session_destroy();
header('Location: /phprequest/index.php');
exit();
