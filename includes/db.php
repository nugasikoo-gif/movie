<?php
/**
 * Database Connection File
 * 
 * This file creates a MySQLi connection to the MovieDB database.
 * Include this file in any PHP page that needs database access.
 * 
 * Usage: require_once 'includes/db.php';
 *        Then use the $conn variable for queries.
 */

// ----- Database Configuration -----
$db_host = 'localhost';    // XAMPP MySQL runs on localhost
$db_user = 'root';         // Default XAMPP MySQL username
$db_pass = '';             // Default XAMPP MySQL password (empty)
$db_name = 'moviedb';      // Our database name

// ----- Create Connection -----
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// ----- Check Connection -----
// If the connection fails, stop the script and show an error message.
if ($conn->connect_error) {
    die(
        '<div style="font-family: sans-serif; padding: 40px; text-align: center;">'
        . '<h2 style="color: #e74c3c;">Database Connection Failed</h2>'
        . '<p>Could not connect to the MySQL database.</p>'
        . '<p><strong>Error:</strong> ' . htmlspecialchars($conn->connect_error) . '</p>'
        . '<hr>'
        . '<p><strong>How to fix this:</strong></p>'
        . '<ol style="text-align: left; max-width: 500px; margin: 0 auto;">'
        . '<li>Open the <strong>XAMPP Control Panel</strong></li>'
        . '<li>Make sure <strong>Apache</strong> and <strong>MySQL</strong> are both running (green)</li>'
        . '<li>Open <strong>phpMyAdmin</strong> (http://localhost/phpmyadmin)</li>'
        . '<li>Make sure the <strong>moviedb</strong> database exists</li>'
        . '<li>If not, import the <code>database.sql</code> file</li>'
        . '</ol>'
        . '</div>'
    );
}

// ----- Set Character Encoding -----
// UTF-8 ensures special characters (accents, emojis, etc.) work properly.
$conn->set_charset('utf8mb4');
