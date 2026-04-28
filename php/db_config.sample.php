<?php
// 这是一个配置模板，请将其重命名为 db_config.php 并填写你的信息
$host = 'localhost';
$user = 'YOUR_USERNAME';      // 比如 root
$pass = 'YOUR_PASSWORD';      // 你的数据库密码
$dbname = 'media_library_system';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}
?>
