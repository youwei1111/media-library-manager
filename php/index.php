<?php
/**
 * Media Library System - Main Dashboard
 * Handles filtering, sorting, and dynamic rendering of media items.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db_config.php';

// --- Logic: Filter & Sort Handling ---
$type_filter = $_GET['type'] ?? '';     
$folder_filter = $_GET['folder'] ?? ''; 
$order_by = $_GET['sort'] ?? 'last_updated'; 
$allowed_sorts = ['last_updated', 'rating', 'title'];

if (!in_array($order_by, $allowed_sorts)) { 
    $order_by = 'last_updated'; 
}

/**
 * Helper function to maintain existing URL parameters while updating specific filters
 */
function build_url($updates) {
    $params = $_GET; 
    foreach ($updates as $key => $value) {
        if ($value === '') { 
            unset($params[$key]); 
        } else { 
            $params[$key] = $value; 
        }
    }
    return "index.php?" . http_build_query($params);
}

// Fetch unique folder list for navigation
$all_folders = [];
$af_res = $conn->query("SELECT DISTINCT folder_name FROM media_items WHERE folder_name IS NOT NULL AND folder_name != '未分类'");
while($f = $af_res->fetch_assoc()) { 
    $all_folders[] = $f['folder_name']; 
}

// Construct SQL query with dynamic filters
$sql = "SELECT * FROM media_items WHERE 1=1";
if ($type_filter) { 
    $sql .= " AND type = '" . $conn->real_escape_string($type_filter) . "'"; 
}
if ($folder_filter) { 
    $sql .= " AND folder_name = '" . $conn->real_escape_string($folder_filter) . "'"; 
}

// Custom sort order: Watching > Up-to-date > Plan to Watch > Completed
$sql .= " ORDER BY FIELD(status, '在看', '追平', '想看', '已看') ASC, $order_by DESC";

$today_day = date('w'); 
$day_names = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
$result = $conn->query($sql);

$all_items = [];
$remaining_count = 0; // Counter for pending items (non-completed)

if ($result) {
    while($row = $result->fetch_assoc()) {
        $all_items[] = $row;
        if ($row['status'] !== '已看') {
            $remaining_count++;
        }
    }
}
$total_display = count($all_items); 
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Media Library</title>
    <link rel="stylesheet" href="./style/style.css?v=<?php echo time(); ?>">
</head>
<body>

    <h1>🎬 Media Library</h1>

    <div class="nav-section">
        <h3>Add New Item</h3>
        <form action="search_result.php" method="POST" class="search-form">
            <input type="text" name="name" placeholder="Enter title name..." required>
            <select name="type">
                <option value="movie">Movie</option>
                <option value="tv">TV Series</option>
                <option value="show">Variety Show</option>
                <option value="manga">Manga</option>
                <option value="anime">Anime</option>
                <option value="book">Book</option>
            </select>
            <button type="submit">Search & Confirm</button>
        </form>
    </div>

    <div class="nav-section">
        <div class="nav">
            <strong>📁 Folders:</strong>
            <a href="<?php echo build_url(['folder' => '']); ?>" class="nav-link <?php echo $folder_filter==''?'active':''; ?>">All</a>
            <?php foreach($all_folders as $fname): ?>
                <a href="<?php echo build_url(['folder' => $fname]); ?>" class="nav-link <?php echo $folder_filter==$fname?'active':''; ?>">📂 <?php echo htmlspecialchars($fname); ?></a>
            <?php endforeach; ?>
        </div>
        
        <div class="nav" style="margin-top: 10px;">
            <strong>🏷️ Categories:</strong>
            <a href="<?php echo build_url(['type' => '']); ?>" class="nav-link <?php echo $type_filter==''?'active':''; ?>">All</a>
            <?php 
            $categories = ['movie'=>'Movie', 'tv'=>'Series', 'show'=>'Variety', 'anime'=>'Anime', 'manga'=>'Manga', 'book'=>'Book'];
            foreach($categories as $key => $val): ?>
                <a href="<?php echo build_url(['type' => $key]); ?>" class="nav-link <?php echo $type_filter==$key?'active':''; ?>"><?php echo $val; ?></a>
            <?php endforeach; ?>
        </div>

        <div class="nav" style="margin-top: 10px;">
            <strong>📊 Sort By:</strong>
            <a href="<?php echo build_url(['sort' => 'last_updated']); ?>" class="nav-link <?php echo $order_by=='last_updated'?'active':''; ?>">Recently Added</a>
            <a href="<?php echo build_url(['sort' => 'rating']); ?>" class="nav-link <?php echo $order_by=='rating'?'active':''; ?>">Highest Rating</a>
            <a href="<?php echo build_url(['sort' => 'title']); ?>" class="nav-link <?php echo $order_by=='title'?'active':''; ?>">Title (A-Z)</a>
        </div>
    </div>

    <div class="status-bar">
        <span>📊 Pending: <strong><?php echo $remaining_count; ?></strong> items</span>
        <span class="total-hint">(Total in list: <?php echo $total_display; ?>)</span>
        <?php if ($type_filter || $folder_filter): ?>
            <span class="filter-tag">Filtered</span>
        <?php endif; ?>
    </div>

   <div class="grid">
    <?php if (!empty($all_items)): ?>
        <?php foreach($all_items as $row): ?>
            
            <div class="card <?php 
                echo ($row['status'] == '已看') ? 'status-done' : 
                    (($row['status'] == '在看') ? 'status-watching' : 
                    (($row['status'] == '追平') ? 'status-uptodate' : '')); 
            ?>">
                
                <a href="<?php echo $row['link']; ?>" target="_blank" class="poster-link">
                    <div class="poster-container">
                        <?php if ($row['update_day'] == $today_day && $row['status'] == '在看'): ?>
                            <div class="update-badge">🔥 Updates Today (<?php echo $day_names[$today_day]; ?>)</div>
                        <?php endif; ?>
                        <img src="<?php echo $row['poster_url'] ?: 'https://via.placeholder.com/200x280?text=No+Image'; ?>" alt="Poster">
                        <div class="type-tag"><?php echo strtoupper($row['type']); ?></div>
                    </div>
                </a>
                
                <div class="info">
                    <h3 class="item-title">
                        <a href="<?php echo $row['link']; ?>" target="_blank" class="poster-link">
                            <?php echo htmlspecialchars($row['title']); ?>
                        </a>
                        <span class="rating-small">(⭐<?php echo $row['rating']; ?>)</span>
                    </h3>
                    
                    <form class="ajax-form card-form">
                        <input type="hidden" name="update_id" value="<?php echo $row['id']; ?>">

                        <select name="new_status" class="input-style auto-submit">
                            <option value="想看" <?php echo $row['status']=='想看'?'selected':''; ?>>Plan to Watch</option>
                            <option value="在看" <?php echo $row['status']=='在看'?'selected':''; ?>>Watching</option>
                            <option value="追平" <?php echo $row['status']=='追平'?'selected':''; ?>>Up to Date</option>
                            <option value="已看" <?php echo $row['status']=='已看'?'selected':''; ?>>Completed</option>
                        </select>

                        <input type="text" name="remarks" class="input-style remarks-input" placeholder="Add remarks..." value="<?php echo htmlspecialchars($row['remarks'] ?? ''); ?>">

                        <?php if ($row['type'] !== 'movie'): ?>
                            <?php $progress = ($row['total_eps'] > 0) ? ($row['current_ep'] / $row['total_eps']) * 100 : 0; ?>
                            <div class="progress-container">
                                <div class="progress-bar" style="width: <?php echo $progress; ?>%;"></div>
                            </div>

                            <div class="ep-row">
                                <div class="ep-input-wrapper">
                                    <input type="number" name="current_ep" class="ep-input" value="<?php echo $row['current_ep']; ?>">
                                    <button type="button" class="mini-plus-btn">+1</button>
                                </div>
                                <span>/</span>
                                <input type="number" name="total_eps" class="ep-input" value="<?php echo $row['total_eps']; ?>">
   
                                <select name="update_day" class="day-select">
                                    <option value="-1">Air Day</option>
                                    <?php foreach($day_names as $i => $n): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ($row['update_day'] == $i) ? 'selected' : ''; ?>><?php echo $n; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <select name="new_folder" class="input-style folder-logic">
                            <option value="未分类">-- Move to Folder --</option>
                            <?php foreach($all_folders as $fname): ?>
                                <option value="<?php echo htmlspecialchars($fname); ?>" <?php echo ($row['folder_name'] == $fname) ? 'selected' : ''; ?>>📂 <?php echo htmlspecialchars($fname); ?></option>
                            <?php endforeach; ?>
                            <option value="NEW_FOLDER" style="color: #3498db;">+ New Folder...</option>
                        </select>
                    </form>

                    <div class="card-footer">
                        <div class="link-wrapper">
                            <button type="button" class="link-toggle-btn" title="Set Link">🔗</button>
                            <input type="url" name="new_link" class="input-style link-input hidden-input" 
                                placeholder="Paste URL..." 
                                value="<?php echo htmlspecialchars($row['link'] ?? ''); ?>">
                        </div>

                        <button type="button" class="delete-icon-btn" onclick="deleteItem(<?php echo $row['id']; ?>, this)">
                            🗑️
                        </button>
                    </div>
                </div>
            </div> 
        <?php endforeach; ?>
    <?php else: ?>
        <p class="empty-msg">No collection found.</p>
    <?php endif; ?>
</div>

<div class="fixed-actions">
    <button id="theme-btn" title="Toggle Theme">🌓</button>
    <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Back to Top">▲</button>
</div>

<script src="./scripts/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
