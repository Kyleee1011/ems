<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAuditLogsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('audit_logs', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('timestamp', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('actor_user_id', 'integer', ['null' => true])
            ->addColumn('actor_role', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('action', 'string', ['limit' => 100])
            ->addColumn('entity_type', 'string', ['limit' => 50])
            ->addColumn('entity_id', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('before_data', 'json', ['null' => true])
            ->addColumn('after_data', 'json', ['null' => true])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
            ->addColumn('user_agent', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('request_id', 'string', ['limit' => 36, 'null' => true])
            ->addIndex(['timestamp'], ['name' => 'idx_timestamp'])
            ->addIndex(['actor_user_id'], ['name' => 'idx_actor'])
            ->addIndex(['entity_type', 'entity_id'], ['name' => 'idx_entity'])
            ->addIndex(['action'], ['name' => 'idx_action'])
            ->create();
    }
}
