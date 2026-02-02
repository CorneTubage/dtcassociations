<?php

declare(strict_types=1);

namespace OCA\DTCAssociations\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version010000Date20250101 extends SimpleMigrationStep
{

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('dtc_associations')) {
            $table = $schema->createTable('dtc_associations');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('name', 'string', [
                'notnull' => true,
                'length' => 200,
            ]);
            $table->addColumn('code', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['code'], 'dtc_associations_code_idx');
        }

        if (!$schema->hasTable('dtc_asso_members')) {
            $table = $schema->createTable('dtc_asso_members');
            $table->addColumn('id', 'integer', [
                'autoincrement' => true,
                'notnull' => true,
            ]);
            $table->addColumn('user_id', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('group_id', 'string', [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('role', 'string', [
                'notnull' => true,
                'length' => 64,
                'default' => 'member'
            ]);
            $table->setPrimaryKey(['id']);
            $table->addIndex(['user_id'], 'dtc_asso_members_user_idx');
            $table->addIndex(['group_id'], 'dtc_asso_members_group_idx');
        }

        return $schema;
    }
}
