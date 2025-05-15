<?php
require_once __DIR__ . '/config/config.php';

// Clear all session data
session_destroy();

// Clear session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Redirect to login page
flashMessage('Anda telah berhasil logout', 'success');
redirect('/index.php');
