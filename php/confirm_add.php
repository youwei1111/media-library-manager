<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_data = [
        "title" => $_POST['title'],
        "type" => $_POST['type'],
        "rating" => $_POST['rating'],
        "poster" => $_POST['poster']
    ];

    $json_data = json_encode($item_data, JSON_UNESCAPED_UNICODE);

    $python_path = "/Users/3dyson_1225/Media_Library_System/venv/bin/python3";
    $script_path = "/Users/3dyson_1225/Media_Library_System/VSCode/python/Collector.py";
    
    $command = "\"$python_path\" \"$script_path\" --add " . escapeshellarg($json_data) . " 2>&1";
    
    $output = shell_exec($command);

    // --- 修改这里：不再跳转，只输出结果 ---
    if (trim($output) === "SUCCESS") {
        echo "SUCCESS"; 
    } else {
        // 如果失败，输出具体的 Python 错误信息
        echo $output;
    }
}
?>