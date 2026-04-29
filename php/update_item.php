<?php
/**
 * Media Library System - Update Controller
 * Handles all asynchronous (AJAX) and synchronous data updates, 
 * including status changes, progress tracking, and folder management.
 */

// Enable error reporting (Development mode only)
ini_set('display_errors', 1);
error_reporting(E_ALL);

include 'db_config.php';

// --- Logic 1: Handle Deletion (Synchronous fallback) ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM media_items WHERE id = $id");
}

// --- Logic 2: Update Status (AJAX) ---
if (isset($_GET['update_id']) && isset($_GET['new_status'])) {
    $id = intval($_GET['update_id']);
    $status = $conn->real_escape_string($_GET['new_status']);
    $conn->query("UPDATE media_items SET status = '$status' WHERE id = $id");
}

// --- Logic 3: Update Remarks (AJAX - Persistent Storage) ---
if (isset($_GET['update_id']) && isset($_GET['remarks'])) {
    $id = intval($_GET['update_id']);
    $remarks = $conn->real_escape_string($_GET['remarks']);
    $conn->query("UPDATE media_items SET remarks = '$remarks' WHERE id = $id");
}

// --- Logic 4: Folder Management (Move/Rename) ---
if (isset($_GET['update_folder_id']) && isset($_GET['new_folder'])) {
    $id = intval($_GET['update_folder_id']);
    $folder = $conn->real_escape_string($_GET['new_folder']);
    if (empty($folder)) $folder = 'Uncategorized'; // Default fallback
    $conn->query("UPDATE media_items SET folder_name = '$folder' WHERE id = $id");
}

// --- Logic 5: Progress Tracking (Episodes/Chapters) ---
if (isset($_GET['update_id']) && isset($_GET['current_ep']) && isset($_GET['total_eps'])) {
    $id = intval($_GET['update_id']);
    $curr = intval($_GET['current_ep']);
    $total = intval($_GET['total_eps']);
    $conn->query("UPDATE media_items SET current_ep = $curr, total_eps = $total WHERE id = $id");
}

// --- Logic 6: Air Day Configuration (Broadcast Schedule) ---
if (isset($_GET['update_id']) && isset($_GET['update_day'])) {
    $id = intval($_GET['update_id']);
    $day = intval($_GET['update_day']);
    $conn->query("UPDATE media_items SET update_day = $day WHERE id = $id");
}

// --- Logic 7: External Link Persistence ---
if (isset($_GET['update_id']) && isset($_GET['new_link'])) {
    $id = intval($_GET['update_id']);
    $link = $conn->real_escape_string($_GET['new_link']);
    $conn->query("UPDATE media_items SET link = '$link' WHERE id = $id");
}

/**
 * RESPONSE HANDLING & ROUTING
 * Determines if the response should be a raw success string (for AJAX) 
 * or a header redirect (for traditional form submissions).
 */

if (isset($_GET['ajax'])) {
    // Return 200 OK for asynchronous JS requests
    http_response_code(200);
    echo "SUCCESS";
    exit(); 
}

// Default Fallback: Redirect back to dashboard for synchronous actions
$redirect_url = "index.php";
header("Location: " . $redirect_url);
exit();
?>
