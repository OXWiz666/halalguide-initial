<?php
$servername = "localhost";
$username = "u936798932_halalguide";
$password = "Halalguide123";
$database = "u936798932_halalguide";

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
