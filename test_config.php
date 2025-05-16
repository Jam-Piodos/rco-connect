<?php
// Test file to verify our config.php file can be included multiple times without warnings
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
echo "First include successful.\n";

require_once 'config.php';
echo "Second include successful.\n";

echo "DB_HOST: " . DB_HOST . "\n";

echo "getDBConnection() exists: " . (function_exists('getDBConnection') ? 'Yes' : 'No') . "\n";
echo "sendEmail() exists: " . (function_exists('sendEmail') ? 'Yes' : 'No') . "\n";

echo "Test completed without errors.";
?> 