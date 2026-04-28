<?php
// --- 1. 数据库连接 ---
include 'db_config.php';

$name = $_POST['name'] ?? '';
$type = $_POST['type'] ?? '';

// --- 2. 调用 Python 脚本获取搜索结果 ---
$python_path = "/Users/3dyson_1225/Media_Library_System/venv/bin/python3";
$script_path = "/Users/3dyson_1225/Media_Library_System/VSCode/python/Collector.py";
$command = "\"$python_path\" \"$script_path\" --search " . escapeshellarg($name) . " " . escapeshellarg($type) . " 2>&1";

$output = shell_exec($command);
$results = json_decode($output, true);

// --- 3. 核心修改：获取库中已有的 标题+类型 组合 ---
$existing_keys = []; // 用于存储 "标题_类型" 的唯一标识
$check_res = $conn->query("SELECT title, type FROM media_items");
if ($check_res) {
    while($row = $check_res->fetch_assoc()){
        // 用 标题_类型 作为唯一的 key，解决动漫/漫画同名无法共存的问题
        $key = $row['title'] . '_' . $row['type'];
        $existing_keys[] = $key;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>确认搜索结果</title>
    <style>
        body { font-family: sans-serif; background: #f4f4f4; padding: 20px; }
        .search-item { display: flex; border: 1px solid #ddd; margin-bottom: 15px; padding: 15px; border-radius: 8px; background: white; align-items: center; }
        .search-item img { width: 100px; height: 150px; object-fit: cover; margin-right: 20px; border-radius: 4px; background: #eee; }
        .info { flex: 1; }
        .btn-add { padding: 10px 20px; background: #2ecc71; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn-exists { padding: 10px 20px; background: #bdc3c7; color: white; border: none; border-radius: 4px; cursor: not-allowed; }
        .badge-exists { color: #e67e22; font-weight: bold; margin-bottom: 5px; display: block; }
    </style>
</head>
<body>
    <h2>🎬 为您找到以下结果：</h2>
    
    <?php if ($results): ?>
        <?php foreach ($results as $item): 
            // --- 核心修改：生成当前搜索项的唯一 KEY 并进行比对 ---
            // 注意：这里要确保 $item['type'] 的值与数据库存储的类型（如 anime, manga）一致
            $current_key = $item['title'] . '_' . $type; 
            $is_added = in_array($current_key, $existing_keys);
        ?>
            <div class="search-item">
                <img src="<?php echo $item['poster'] ?: 'https://via.placeholder.com/200x280?text=No+Image'; ?>" alt="Poster">
                
                <div class="info">
                    <h3>
                        <?php echo htmlspecialchars($item['title']); ?> 
                        (⭐<?php 
                            // 这里的逻辑：如果存在且大于0就显示，否则显示 "暂无" 或 "0.0"
                            $r = isset($item['rating']) ? (float)$item['rating'] : 0;
                            echo ($r > 0) ? number_format($r, 1) : '暂无'; 
                        ?>)
                    </h3>
                    
                    <p style="color: #666; font-size: 0.9em;">
                        <?php echo mb_substr($item['overview'] ?? $item['summary'] ?? '暂无简介', 0, 100) . '...'; ?>
                    </p>
                    
                    <?php if ($is_added): ?>
                        <span class="badge-exists">⚠️ 此“<?php echo htmlspecialchars($type); ?>”作品已在您的收藏库中</span>
                        <button class="btn-exists" disabled>无法重复添加</button>
                    <?php else: ?>
                       <form onsubmit="window.addItem(event, this)">
                            <input type="hidden" name="title" value="<?php echo htmlspecialchars($item['title']); ?>">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                            <input type="hidden" name="rating" value="<?php echo isset($item['rating']) ? $item['rating'] : 0; ?>">
                            <input type="hidden" name="poster" value="<?php echo $item['poster']; ?>">
                            <button type="submit" class="btn-add">确认添加</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>找不到结果，请尝试缩短关键词或检查网络连接。</p>
    <?php endif; ?>
    
    <div style="margin-top: 20px;">
        <a href="index.php" style="color: #3498db; text-decoration: none;">← 返回首页</a>
    </div>
     <script src="./scripts/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
