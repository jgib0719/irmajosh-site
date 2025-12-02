<?php
require 'src/bootstrap.php';

// Override db() by wrapping it
$original_db = db();
class DebugPDO extends PDO {
    private $original;
    public function __construct($original) {
        $this->original = $original;
    }
    public function prepare($statement, $options = []) {
        file_put_contents('/tmp/sql_debug.log', date('Y-m-d H:i:s') . " SQL: " . $statement . "\n", FILE_APPEND);
        return $this->original->prepare($statement, $options);
    }
    public function __call($method, $args) {
        return call_user_func_array([$this->original, $method], $args);
    }
}
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/dashboard';
$_SESSION['user_id'] = 1;

try {
    require 'src/router.php';
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
