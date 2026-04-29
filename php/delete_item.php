<?php
/**
 * Item Deletion Handler
 * Handles the removal of media items via AJAX requests.
 */

include 'db_config.php';

// Retrieve ID from POST request, defaulting to 0 if not set
$id = $_POST['id'] ?? 0;

if ($id > 0) {
    /**
     * NOTE FOR PORTFOLIO: 
     * In a production environment, using Prepared Statements is recommended 
     * to prevent SQL Injection. For this personal project, we use direct query 
     * for simplicity in local demonstration.
     */
    $sql = "DELETE FROM media_items WHERE id = " . intval($id);
    
    if ($conn->query($sql)) {
        // Return 'success' string for frontend AJAX callback to handle UI removal
        echo 'success'; 
    } else {
        echo 'error';
    }
}

// Close the database connection to free up resources
$conn->close();
?>
