<?php
/**
 * Item Addition Handler
 * This script acts as a bridge between the Web Frontend (PHP) and the Data Collector (Python).
 * It receives POST data, encodes it as JSON, and passes it to the Python backend.
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Prepare item metadata from the POST request
    $item_data = [
        "title"  => $_POST['title'],
        "type"   => $_POST['type'],
        "rating" => $_POST['rating'],
        "poster" => $_POST['poster']
    ];

    // 2. Encode data to JSON for safe command-line transmission
    $json_data = json_encode($item_data, JSON_UNESCAPED_UNICODE);

    /**
     * PATH CONFIGURATION
     * Recommendation: Use absolute paths or __DIR__ to ensure script accessibility 
     * after moving directories within the XAMPP htdocs environment.
     */
    $python_path = "./Media_Library_System/venv/bin/python3";
    $script_path = "./Media_Library_System/VSCode/python/Collector.py";
    
    // 3. Construct the shell command
    // Redirecting 2>&1 ensures Python tracebacks are captured in the $output variable
    $command = "\"$python_path\" \"$script_path\" --add " . escapeshellarg($json_data) . " 2>&1";
    
    // 4. Execute the command and capture the output
    $output = shell_exec($command);

    /**
     * RESPONSE HANDLING
     * Instead of a header redirect, we return a raw string for AJAX to handle the UI transition.
     */
    if (trim($output) === "SUCCESS") {
        echo "SUCCESS"; 
    } else {
        // Return the specific Python error message for debugging purposes
        echo "Backend Error: " . $output;
    }
}
?>
