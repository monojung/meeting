<?php
// Destroy session and redirect to login
session_destroy();
header('Location: /auth/login');
exit;
?>