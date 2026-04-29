<?php
/**
 * Search Results Preview & Confirmation
 * Acts as an intermediate layer to prevent duplicate entries and confirm metadata before saving.
 */

// --- 1. Database Connection ---
include 'db_config.php';

$name = $_POST['name'] ?? '';
$type = $_POST['type'] ?? '';

// --- 2. Call Python Script for Metadata Retrieval ---
// Note: Ensure the absolute path to the virtual environment is correct for your local setup.
$python_path = "/Users/3dyson_1225/Media_Library_System/venv/bin/python3";
$script_path = "/Users/3dyson_1225/Media_Library_System/VSCode/python/Collector.py";
$command = "\"$python_path\" \"$script_path\" --search " . escapeshellarg($name) . " " . escapeshellarg($type) . " 2>&1";

$output = shell_exec($command);
$results = json_decode($output, true);

// --- 3. Duplicate Prevention Logic ---
// We create a composite key "Title_Type" to allow same-name items of different media types (e.g., Anime vs. Manga).
$existing_keys = []; 
$check_res = $conn->query("SELECT title, type FROM media_items");
if ($check_res) {
    while($row = $check_res->fetch_assoc()){
        $key = $row['title'] . '_' . $row['type'];
        $existing_keys[] = $key;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Confirm Search Results</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f4f4; padding: 20px; color: #333; }
        .search-item { display: flex; border: 1px solid #ddd; margin-bottom: 15px; padding: 15px; border-radius: 8px; background: white; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .search-item img { width: 100px; height: 150px; object-fit: cover; margin-right: 20px; border-radius: 4px; background: #eee; }
        .info { flex: 1; }
        .btn-add { padding: 10px 20px; background: #2ecc71; color: white; border: none; border-radius: 4px; cursor: pointer; transition: background 0.3s; }
        .btn-add:hover { background: #27ae60; }
        .btn-exists { padding: 10px 20px; background: #bdc3c7; color: white; border: none; border-radius: 4px; cursor: not-allowed; }
        .badge-exists { color: #e67e22; font-weight: bold; margin-bottom: 5px; display: block; font-size: 0.85em; }
        .overview-text { color: #666; font-size: 0.9em; line-height: 1.4; margin: 10px 0; }
    </style>
</head>
<body>
    <h2>🎬 Search Results for: "<?php echo htmlspecialchars($name); ?>"</h2>
    
    <?php if ($results): ?>
        <?php foreach ($results as $item): 
            // Generate unique key for current search result to check against database
            $current_key = $item['title'] . '_' . $type; 
            $is_added = in_array($current_key, $existing_keys);
        ?>
            <div class="search-item">
                <img src="<?php echo $item['poster'] ?: 'https://via.placeholder.com/200x280?text=No+Image'; ?>" alt="Poster Preview">
                
                <div class="info">
                    <h3>
                        <?php echo htmlspecialchars($item['title']); ?> 
                        <span style="color: #f1c40f;">(⭐<?php 
                            $r = isset($item['rating']) ? (float)$item['rating'] : 0;
                            echo ($r > 0) ? number_format($r, 1) : 'N/A'; 
                        ?>)</span>
                    </h3>
                    
                    <p class="overview-text">
                        <?php echo mb_substr($item['overview'] ?? $item['summary'] ?? 'No description available.', 0, 150) . '...'; ?>
                    </p>
                    
                    <?php if ($is_added): ?>
                        <span class="badge-exists">⚠️ This "<?php echo htmlspecialchars($type); ?>" is already in your collection.</span>
                        <button class="btn-exists" disabled>Duplicate Entry</button>
                    <?php else: ?>
                       <form onsubmit="window.addItem(event, this)">
                            <input type="hidden" name="title" value="<?php echo htmlspecialchars($item['title']); ?>">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>">
                            <input type="hidden" name="rating" value="<?php echo isset($item['rating']) ? $item['rating'] : 0; ?>">
                            <input type="hidden" name="poster" value="<?php echo $item['poster']; ?>">
                            <button type="submit" class="btn-add">Add to Library</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 50px;">
            <p>No results found. Try shortening your keywords or check your API connection.</p>
        </div>
    <?php endif; ?>
    
    <div style="margin-top: 20px;">
        <a href="index.php" style="color: #3498db; text-decoration: none; font-weight: bold;">← Back to Dashboard</a>
    </div>

    <script src="./scripts/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
