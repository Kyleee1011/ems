<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateNotificationsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('notifications', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('recipient_user_id', 'integer')
            ->addColumn('type', 'string', ['limit' => 100])
            ->addColumn('title', 'string', ['limit' => 255])
            ->addColumn('body', 'text', ['null' => true])
            ->addColumn('link', 'string', ['limit' => 500, 'null' => true])
            ->addColumn('is_read', 'boolean', ['default' => 0])
            ->addColumn('read_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['recipient_user_id'], ['name' => 'idx_recipient'])
            ->addIndex(['recipient_user_id', 'is_read'], ['name' => 'idx_recipient_read'])
            ->addIndex(['created_at'], ['name' => 'idx_created'])
            ->create();
    }
}
