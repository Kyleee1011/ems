<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class EnhanceEmployeeDocumentsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('employee_documents');

        if (!$table->hasColumn('doc_type')) {
            $table->addColumn('doc_type', 'string', ['limit' => 100, 'null' => true, 'default' => 'general'])
                  ->addColumn('status', 'string', ['limit' => 50, 'default' => 'Approved'])
                  ->addColumn('reviewed_by', 'integer', ['null' => true])
                  ->addColumn('reviewed_at', 'datetime', ['null' => true])
                  ->addColumn('rejection_reason', 'string', ['limit' => 500, 'null' => true])
                  ->addColumn('expiry_date', 'date', ['null' => true])
                  ->addIndex(['emp_id', 'status'], ['name' => 'idx_emp_status'])
                  ->update();
        }
    }
}
