<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddAppealFieldsToLeaveApplications extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('leave_applications');

        if (!$table->hasColumn('is_appeal')) {
            $table->addColumn('is_appeal', 'boolean', ['default' => 0, 'after' => 'status'])
                  ->addColumn('appeal_reason', 'text', ['null' => true, 'after' => 'is_appeal'])
                  ->addColumn('attachment_path', 'string', ['limit' => 255, 'null' => true, 'after' => 'appeal_reason'])
                  ->addColumn('violation_note', 'string', ['limit' => 255, 'null' => true, 'after' => 'attachment_path'])
                  ->addIndex(['is_appeal'], ['name' => 'idx_leave_is_appeal'])
                  ->update();
        }
    }
}
