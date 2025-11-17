<?php
session_start();

// Destroy session
$_SESSION = [];
session_unset();
session_destroy();

// Prevent back button caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to landing_page.php
header("Location: landing_page.php");
exit;
