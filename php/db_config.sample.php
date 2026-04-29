<?php
/**
 * DATABASE CONFIGURATION TEMPLATE
 * * Instructions:
 * 1. Rename this file to 'db_config.php'
 * 2. Update the credentials below with your local MySQL settings
 * 3. Ensure 'db_config.php' is added to your .gitignore to keep your credentials secure
 */

$host   = 'localhost';
$user   = 'YOUR_USERNAME';      // Example: 'root'
$pass   = 'YOUR_PASSWORD';      // Your MySQL password
$dbname = 'media_library_system';

// Create database connection
$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection and terminate on failure
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}
?>
