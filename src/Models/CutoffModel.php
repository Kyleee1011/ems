<?php
namespace App\Models;

use PDO;

class CutoffModel
{
    protected $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureTable();
    }

    /**
     * Ensure the cutoff_configurations table exists.
     */
    private function ensureTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS cutoff_configurations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cutoff_1_start INT NOT NULL DEFAULT 8,
                cutoff_1_end INT NOT NULL DEFAULT 22,
                cutoff_2_start INT NOT NULL DEFAULT 23,
                cutoff_2_end INT NOT NULL DEFAULT 7,
                effective_date DATE NOT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /**
     * Get the cutoff configuration active for a given date.
     * Falls back to payroll_general_settings if no config row exists.
     */
    public function getActiveConfig(string $date): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM cutoff_configurations 
             WHERE effective_date <= ? 
             ORDER BY effective_date DESC, id DESC 
             LIMIT 1"
        );
        $stmt->execute([$date]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) return $row;

        // Fallback: read from payroll_general_settings
        $settings = [];
        $stmtSettings = $this->pdo->query("SELECT setting_key, setting_value FROM payroll_general_settings WHERE setting_key LIKE 'cutoff%'");
        while ($s = $stmtSettings->fetch(PDO::FETCH_ASSOC)) {
            $settings[$s['setting_key']] = $s['setting_value'];
        }

        return [
            'id' => null,
            'cutoff_1_start' => (int)($settings['cutoff_1_start'] ?? 8),
            'cutoff_1_end' => (int)($settings['cutoff_1_end'] ?? 22),
            'cutoff_2_start' => (int)($settings['cutoff_2_start'] ?? 23),
            'cutoff_2_end' => (int)($settings['cutoff_2_end'] ?? 7),
            'effective_date' => null,
            'created_at' => null,
        ];
    }

    public function getConfigHistory(): array
    {
        return $this->pdo->query(
            "SELECT * FROM cutoff_configurations ORDER BY effective_date DESC, id DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveConfig(array $data): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO cutoff_configurations (cutoff_1_start, cutoff_1_end, cutoff_2_start, cutoff_2_end, effective_date, created_by) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['cutoff_1_start'],
            $data['cutoff_1_end'],
            $data['cutoff_2_start'],
            $data['cutoff_2_end'],
            $data['effective_date'],
            $data['created_by'],
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
