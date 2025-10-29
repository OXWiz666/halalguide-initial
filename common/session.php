<?php
session_start();

// Function to check kung naka logged in ang user
function check_login() {
    if(!isset($_SESSION['user_role'])) {
        header("Location: ../login.php");
        exit();
    }
}

// Function to check kung naka logged in ang user pero para sa homepage
function check_login_index() {
    if(!isset($_SESSION['user_role'])) {
        header("Location: login.php");
        exit();
    }
}

// Function to check kung may access ba ang user to a specific user side
function check_access($role) {
    if ($_SESSION['user_role'] !== $role) {
        header("Location: index.php");
        exit();
    }
}

// Function to log out the user
function logout() {
    session_unset();
    session_destroy();
    header("Location: ../");
    exit();
}
?>
