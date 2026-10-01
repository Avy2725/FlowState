<?php
// Copy this file to connection.php and fill in your own credentials.
// connection.php is ignored by git so credentials are never committed.
$conn = new mysqli('localhost', 'db_user', 'db_password', 'db_name');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}
