<?php
// 开启错误报告（调试用，正式上线可关闭）
ini_set('display_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "media_library_system");

if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// --- 逻辑 1：处理删除 (通常非 AJAX) ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    $conn->query("DELETE FROM media_items WHERE id = $id");
}

// --- 逻辑 2：处理状态更新 (AJAX) ---
if (isset($_GET['update_id']) && isset($_GET['new_status'])) {
    $id = intval($_GET['update_id']);
    $status = $conn->real_escape_string($_GET['new_status']);
    $conn->query("UPDATE media_items SET status = '$status' WHERE id = $id");
}

// --- 逻辑 3：处理备注更新 (AJAX - 解决你无法保存的问题) ---
if (isset($_GET['update_id']) && isset($_GET['remarks'])) {
    $id = intval($_GET['update_id']);
    $remarks = $conn->real_escape_string($_GET['remarks']);
    $conn->query("UPDATE media_items SET remarks = '$remarks' WHERE id = $id");
}

// --- 逻辑 4：处理文件夹移动/新建 ---
if (isset($_GET['update_folder_id']) && isset($_GET['new_folder'])) {
    $id = intval($_GET['update_folder_id']);
    $folder = $conn->real_escape_string($_GET['new_folder']);
    if (empty($folder)) $folder = '未分类';
    $conn->query("UPDATE media_items SET folder_name = '$folder' WHERE id = $id");
}

// 处理进度更新
if (isset($_GET['update_id']) && isset($_GET['current_ep']) && isset($_GET['total_eps'])) {
    $id = intval($_GET['update_id']);
    $curr = intval($_GET['current_ep']);
    $total = intval($_GET['total_eps']);
    $conn->query("UPDATE media_items SET current_ep = $curr, total_eps = $total WHERE id = $id");
}

// 处理更新周期设定 (可以在详情页或 index 增加此 select)
if (isset($_GET['update_id']) && isset($_GET['update_day'])) {
    $id = intval($_GET['update_id']);
    $day = intval($_GET['update_day']);
    $conn->query("UPDATE media_items SET update_day = $day WHERE id = $id");
}

// 在 update_item.php 的更新逻辑部分加入：
if (isset($_GET['update_id']) && isset($_GET['new_link'])) {
    $id = intval($_GET['update_id']);
    $link = $conn->real_escape_string($_GET['new_link']);
    $conn->query("UPDATE media_items SET link = '$link' WHERE id = $id");
}

// --- 统一判断响应方式 ---

// 只要带有 ajax 参数，统一返回 SUCCESS 并退出
if (isset($_GET['ajax'])) {
    http_response_code(200);
    echo "SUCCESS";
    exit(); // 确保 AJAX 请求在这里就结束，不要往下走 header 跳转
}

// 如果不是 AJAX 请求（比如点击了删除链接），执行传统的页面跳转
$redirect_url = "index.php";
header("Location: " . $redirect_url);
exit();
?>