<?php
require_once __DIR__ . '/../src/bootstrap.php';

use App\Models\DateCategory;
use App\Models\DateIdea;

$file = '/var/www/irmajosh.com/.brainstorming/bookmarks_11_29_25.html';

if (!file_exists($file)) {
    die("File not found: $file\n");
}

$content = file_get_contents($file);
$lines = explode("\n", $content);

$categoryStack = [];
$currentCategoryName = 'Uncategorized';

// Map bookmark folders to our DB categories or create new ones
$categoryMap = [
    'art' => 'Art & Culture',
    'pets' => 'Pets',
    'markets' => 'Markets',
    'music' => 'Music',
    'game/interactive' => 'Games',
    'dates' => null, // Skip or use as root
    'Bookmarks bar' => null
];

echo "Starting import...\n";

// Truncate tables to start fresh (optional, but good for fixing categories)
db()->exec('SET FOREIGN_KEY_CHECKS = 0');
db()->exec('TRUNCATE TABLE date_ideas');
db()->exec('TRUNCATE TABLE date_categories');
db()->exec('SET FOREIGN_KEY_CHECKS = 1');
echo "Tables truncated.\n";

foreach ($lines as $line) {
    $line = trim($line);
    
    // Check for Folder (H3)
    if (preg_match('/<H3[^>]*>([^<]+)<\/H3>/i', $line, $matches)) {
        $folderName = html_entity_decode($matches[1]);
        $currentCategoryName = $folderName;
        // We push to stack when we see DL, but we need to know the name beforehand.
        // Actually, the H3 is immediately followed by DL usually.
        // Let's just store the last seen H3 as "pending category".
    }
    
    // Check for DL start (entering folder)
    if (stripos($line, '<DL') !== false) {
        $categoryStack[] = $currentCategoryName;
    }
    
    // Check for DL end (leaving folder)
    if (stripos($line, '</DL') !== false) {
        array_pop($categoryStack);
        // Reset current category to parent
        $currentCategoryName = end($categoryStack) ?: 'Uncategorized';
    }
    
    // Check for Link (A)
    if (preg_match('/<A HREF="([^"]+)"[^>]*>([^<]+)<\/A>/i', $line, $matches)) {
        $url = $matches[1];
        $title = html_entity_decode($matches[2]);
        
        // Determine category
        $folder = end($categoryStack);
        if ($folder === false) {
            $folder = 'Uncategorized';
        }
        
        if ($folder === 'dates' || $folder === 'Bookmarks bar') {
            // These are top level, maybe treat as "General" or skip if we want specific categories
            $dbCategoryName = 'Activity'; // Default
        } else {
            // Use the folder name
            $dbCategoryName = $categoryMap[strtolower($folder)] ?? ucfirst($folder);
        }
        
        // Find or Create Category
        $categoryId = getCategoryId($dbCategoryName);
        
        // Create Date Idea
        try {
            // Check if exists
            $stmt = db()->prepare('SELECT id FROM date_ideas WHERE url = ? LIMIT 1');
            $stmt->execute([$url]);
            if ($stmt->fetch()) {
                echo "Skipping existing: $title\n";
                continue;
            }
            
            DateIdea::create([
                'title' => $title,
                'category_id' => $categoryId,
                'url' => $url,
                'description' => "Imported from bookmarks ($folder)",
                'cost_level' => 1, // Default
                'season' => 'Any',
                'points_value' => 100
            ]);
            echo "Imported: $title ($dbCategoryName)\n";
        } catch (Exception $e) {
            echo "Error importing $title: " . $e->getMessage() . "\n";
        }
    }
}

function getCategoryId($name) {
    static $cache = [];
    if (isset($cache[$name])) return $cache[$name];
    
    $pdo = db();

    // Check DB
    $stmt = $pdo->prepare('SELECT id FROM date_categories WHERE name = ? LIMIT 1');
    $stmt->execute([$name]);
    $row = $stmt->fetch();
    
    if ($row) {
        $cache[$name] = $row['id'];
        return $row['id'];
    }
    
    // Create
    $stmt = $pdo->prepare('INSERT INTO date_categories (name, icon, color) VALUES (?, ?, ?)');
    // Assign random color/icon or default
    $stmt->execute([$name, 'star', '#6b7280']);
    $id = $pdo->lastInsertId();
    $cache[$name] = $id;
    return $id;
}

echo "Import complete!\n";
