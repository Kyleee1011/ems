<?php
namespace Tests\Unit;

use Exception;

class SimpleTest
{
    private $pdo;

    public function __construct($pdo, $schedulerPdo) {
        $this->pdo = $pdo;
    }

    public function testTrueIsTrue()
    {
        if (true !== true) throw new Exception("True is not true");
    }

    public function testDatabaseConnection()
    {
        if (!$this->pdo) throw new Exception("PDO is null");
        // Simple query
        // $stmt = $this->pdo->query("SELECT 1");
        // if (!$stmt) throw new Exception("Query failed");
    }
}
