<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLeaveCreditsToEmployees extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('employees');

        if (!$table->hasColumn('vl_credits')) {
            $table->addColumn('vl_credits', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'after' => 'sil_credits'])
                  ->addColumn('sl_credits', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 0.00, 'after' => 'vl_credits'])
                  ->addColumn('paternity_credits', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 7.00, 'after' => 'sl_credits'])
                  ->addColumn('maternity_credits', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 105.00, 'after' => 'paternity_credits'])
                  ->addColumn('bereavement_immediate_credits', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 5.00, 'after' => 'maternity_credits'])
                  ->addColumn('bereavement_extended_credits', 'decimal', ['precision' => 10, 'scale' => 2, 'default' => 3.00, 'after' => 'bereavement_immediate_credits'])
                  ->update();
        }
    }
}
