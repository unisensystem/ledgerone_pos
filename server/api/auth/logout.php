<?php
// Clear session
session_start();
session_destroy();

// Redirect to login
header('Location: ../../../client/pages/auth/login.html');
exit;
?>