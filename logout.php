<?php

session_start();

$_SESSION = [];

session_destroy();

header('Location: /crf-ppu/pages/auth/login.php');
exit;