<?php
// delete_item.php
$conn = new mysqli("localhost", "root", "", "media_library_system");
$id = $_POST['id'] ?? 0;

if ($id > 0) {
    $sql = "DELETE FROM media_items WHERE id = $id";
    if ($conn->query($sql)) {
        echo 'success'; // 不要写 header("Location: index.php");
    } else {
        echo 'error';
    }
}
$conn->close();
?>