<?php
session_start();
session_unset();
session_destroy();
header('Location: /AUT_Scheduler/authentication/signin.html');
exit;
