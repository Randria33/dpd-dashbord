<?php
require_once 'autoload.php';

use Utils\Auth;

Auth::logout();
header('Location: login.php');
exit;