<?php
/**
 * Media Library System - Main Dashboard
 * Handles server-side pagination, dynamic filtering, and optimized rendering.
 * Optimized to resolve high INP issues by limiting DOM nodes per request.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
include 'db_config.php';

// --- 1. Logic: Filter & Sort Handling ---
$type_filter = $_GET['type'] ?? '';     
$folder_filter = $_GET['folder'] ?? ''; 
$order_by = $_GET['sort'] ?? 'last_updated'; 
$allowed_sorts = ['last_updated', 'rating', 'title'];

if (!in_array($order_by, $allowed_sorts)) { 
    $order_by = 'last_updated'; 
}

/**
 * Helper function to maintain existing URL parameters while updating specific filters or pages
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

// --- 2. Pagination Configuration ---
$limit = 24; // Fixed limit per page to ensure smooth Interaction to Next Paint (INP)
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Fetch unique folder list for navigation
$all_folders = [];
$af_res = $conn->query("SELECT DISTINCT folder_name FROM media_items WHERE folder_name IS NOT NULL AND folder_name != 'Uncategorized'");
while($f = $af_res->fetch_assoc()) { 
    $all_folders[] = $f['folder_name']; 
}

// --- 3. Database Query Construction ---
$where_clauses = ["1=1"];
if ($type_filter) { 
    $where_clauses[] = "type = '" . $conn->real_escape_string($type_filter) . "'"; 
}
if ($folder_filter) { 
    $where_clauses[] = "folder_name = '" . $conn->real_escape_string($folder_filter) . "'"; 
}
$where_sql = implode(" AND ", $where_clauses);

// Get total count for current filter (Required for pagination)
$total_res = $conn->query("SELECT COUNT(*) AS total FROM media_items WHERE $where_sql");
$total_count = $total_res->fetch_assoc()['total'];
$total_pages = ceil($total_count / $limit);

// Get Pending Count (Dynamic based on current filter)
$wait_res = $conn->query("SELECT COUNT(*) AS cnt FROM media_items WHERE $where_sql AND status != '已看'");
$remaining_count = $wait_res->fetch_assoc()['cnt'];

// Final execution with Pagination: Watching > Up-to-date > Plan to Watch > Completed
$sql = "SELECT * FROM media_items WHERE $where_sql ";
$sql .= "ORDER BY FIELD(status, '在看', '追平', '想看', '已看') ASC, $order_by DESC ";
$sql .= "LIMIT $limit OFFSET $offset";

$today_day = date('w'); 
$day_names = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
$result = $conn->query($sql);

$all_items = [];
if ($result) {
    while($row = $result->fetch_assoc()) {
        $all_items[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Media Library</title>
    <link rel="stylesheet" href="./style/style.css?v=<?php echo time(); ?>">
    <style>
        /* Pagination UI Enhancements */
        .pagination { display: flex; justify-content: center; align-items: center; margin: 40px 0; gap: 10px; }
        .page-link { padding: 8px 16px; background: #fff; border: 1px solid #ddd; border-radius: 6px; text-decoration: none; color: #333; transition: 0.3s; }
        .page-link:hover { background: #f0f0f0; border-color: #bbb; }
        .page-link.active { background: #3498db; color: #fff; border-color: #3498db; }
        .page-info { color: #666; font-size: 0.9em; }
    </style>
</head>
<body>

    <h1>🎬 Media Library</h1>

    <div class="nav-section">
        <h3>Add New Entry</h3>
        <form action="search_result.php" method="POST" class="search-form">
            <select name="type" id="media-type-select">
                <option value="movie">Movie</option>
                <option value="tv">TV Show</option>
                <option value="anime">Anime</option>
                <option value="book">Book</option> 
                <option value="manga">Manga</option>
            </select>

            <input type="text" name="name" id="search-input" placeholder="🔍 Enter the title..." required>
            <button type="submit">Search & Confirm</button>
        </form>
    </div>

    <div class="nav-section">
        <div class="nav">
            <strong>📁 Folders:</strong>
            <a href="<?php echo build_url(['folder' => '', 'page' => 1]); ?>" class="nav-link <?php echo $folder_filter==''?'active':''; ?>">All</a>
            <?php foreach($all_folders as $fname): ?>
                <a href="<?php echo build_url(['folder' => $fname, 'page' => 1]); ?>" class="nav-link <?php echo $folder_filter==$fname?'active':''; ?>">📂 <?php echo htmlspecialchars($fname); ?></a>
            <?php endforeach; ?>
        </div>
        
        <div class="nav" style="margin-top: 10px;">
            <strong>🏷️ Categories:</strong>
            <a href="<?php echo build_url(['type' => '', 'page' => 1]); ?>" class="nav-link <?php echo $type_filter==''?'active':''; ?>">All</a>
            <?php 
            $categories = ['movie'=>'Movie', 'tv'=>'Series', 'show'=>'Variety', 'anime'=>'Anime', 'manga'=>'Manga', 'book'=>'Book'];
            foreach($categories as $key => $val): ?>
                <a href="<?php echo build_url(['type' => $key, 'page' => 1]); ?>" class="nav-link <?php echo $type_filter==$key?'active':''; ?>"><?php echo $val; ?></a>
            <?php endforeach; ?>
        </div>

        <div class="nav" style="margin-top: 10px;">
            <strong>📊 Sort By:</strong>
            <a href="<?php echo build_url(['sort' => 'last_updated', 'page' => 1]); ?>" class="nav-link <?php echo $order_by=='last_updated'?'active':''; ?>">Recent</a>
            <a href="<?php echo build_url(['sort' => 'rating', 'page' => 1]); ?>" class="nav-link <?php echo $order_by=='rating'?'active':''; ?>">Top Rated</a>
            <a href="<?php echo build_url(['sort' => 'title', 'page' => 1]); ?>" class="nav-link <?php echo $order_by=='title'?'active':''; ?>">Title (A-Z)</a>
        </div>
    </div>

    <div class="status-bar">
        <span>📊 Pending: <strong><?php echo $remaining_count; ?></strong> items</span>
        <span class="total-hint">(Current view total: <?php echo $total_count; ?>)</span>
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
                        <?php if (!empty($row['poster_url'])): ?>
                            <img src="<?= $row['poster_url'] ?>" alt="Poster">
                        <?php else: ?>
                            <div class="no-poster"No Poster"</div>
                        <?php endif; ?>
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
                            <option value="追平" <?php echo $row['status']=='追平'?'selected':''; ?>>Caught Up</option>
                            <option value="已看" <?php echo $row['status']=='已看'?'selected':''; ?>>Completed</option>
                        </select>

                        <input type="text" name="remarks" class="input-style remarks-input" placeholder="Notes/Progress..." value="<?php echo htmlspecialchars($row['remarks'] ?? ''); ?>">

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
                            <option value="Uncategorized">-- Move to Folder --</option>
                            <?php foreach($all_folders as $fname): ?>
                                <option value="<?php echo htmlspecialchars($fname); ?>" <?php echo ($row['folder_name'] == $fname) ? 'selected' : ''; ?>>📂 <?php echo htmlspecialchars($fname); ?></option>
                            <?php endforeach; ?>
                            <option value="NEW_FOLDER" style="color: #3498db;">+ New Folder...</option>
                        </select>
                    </form>

                    <div class="card-footer">
                        <button type="button" class="link-toggle-btn" title="Set Link">🔗</button>
                        <input type="url" name="new_link" class="input-style link-input hidden-input" 
                            placeholder="URL..." 
                            value="<?php echo htmlspecialchars($row['link'] ?? ''); ?>">
                        <button type="button" class="delete-icon-btn" onclick="deleteItem(<?php echo $row['id']; ?>, this)">🗑️</button>
                    </div>
                </div>
            </div> 
        <?php endforeach; ?>
    <?php else: ?>
        <p class="empty-msg">No collection entries found.</p>
    <?php endif; ?>
</div>

<?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="<?php echo build_url(['page' => $page - 1]); ?>" class="page-link">Previous</a>
        <?php endif; ?>

        <span class="page-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>

        <?php if ($page < $total_pages): ?>
            <a href="<?php echo build_url(['page' => $page + 1]); ?>" class="page-link">Next</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="fixed-actions">
    <button id="theme-btn" title="Toggle Theme">🌓</button>
    <button onclick="window.scrollTo({top: 0, behavior: 'smooth'})" title="Back to Top">▲</button>
</div>

<script src="./scripts/script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
