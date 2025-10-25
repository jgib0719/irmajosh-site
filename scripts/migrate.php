<?php
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Connect to database
$pdo = new PDO(
    "mysql:host={$_ENV['DB_HOST']};dbname={$_ENV['DB_NAME']};charset=utf8mb4",
    $_ENV['DB_USER'],
    $_ENV['DB_PASS'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]
);

// Set timezone to UTC
$pdo->exec("SET time_zone = '+00:00'");

echo "🔍 Checking migration status...\n\n";

// Check if migrations table exists
$tableExists = $pdo->query("SHOW TABLES LIKE '_migrations'")->rowCount() > 0;

if (!$tableExists) {
    echo "📝 Running migration 000 (create migrations table)...\n";
    $sql = file_get_contents(__DIR__ . '/../migrations/000_create_migrations_table.sql');
    $pdo->exec($sql);
    echo "✅ Migration system initialized\n\n";
}

// Get applied migrations
$stmt = $pdo->query("SELECT migration FROM _migrations ORDER BY id");
$applied = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "✅ Applied migrations: " . count($applied) . "\n";
foreach ($applied as $migration) {
    echo "   - {$migration}\n";
}
echo "\n";

// Scan migration files
$files = glob(__DIR__ . '/../migrations/*.sql');
sort($files); // Ensure order

$pending = [];
foreach ($files as $file) {
    $migration = basename($file, '.sql');
    
    // Skip if already applied
    if (in_array($migration, $applied)) {
        continue;
    }
    
    $pending[] = $migration;
}

if (empty($pending)) {
    echo "✅ All migrations up to date!\n";
    exit(0);
}

echo "📋 Pending migrations: " . count($pending) . "\n";
foreach ($pending as $migration) {
    echo "   - {$migration}\n";
}
echo "\n";

// Apply pending migrations
foreach ($pending as $migration) {
    echo "⏳ Applying {$migration}...\n";
    
    $file = __DIR__ . '/../migrations/' . $migration . '.sql';
    $sql = file_get_contents($file);
    
    try {
        $pdo->exec($sql);
        echo "✅ {$migration} applied successfully\n\n";
    } catch (Exception $e) {
        echo "❌ Failed to apply {$migration}: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "\n✅ All migrations applied successfully!\n";