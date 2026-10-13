<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddColorToDepartments extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('departments');

        if (!$table->hasColumn('color')) {
            $table->addColumn('color', 'string', [
                'limit' => 20,
                'default' => '#0F766E',
                'null' => true,
                'after' => 'dept_name'
            ])->update();
        }

        // Backfill colors for existing departments
        $paletteMap = [
            'Human Resources'         => '#C2410C', // Orange
            'Accounting and finance'  => '#6D28D9', // Violet
            'MIS'                     => '#1D4ED8', // Blue
            'Maintenance'             => '#0F766E', // Teal
            'Kitchen'                 => '#B45309', // Amber / Brown
            'Housekeeping'            => '#047857', // Emerald
            'Food and Beverage'       => '#BE123C', // Rose / Red
            'Front Office'            => '#4338CA', // Indigo
            'Sales and Marketing'     => '#0369A1', // Sky blue
            'DJ'                      => '#A21CAF', // Fuchsia / Purple
            'Owner'                   => '#1E293B'  // Neutral Dark Ink
        ];

        foreach ($paletteMap as $name => $color) {
            $this->execute("UPDATE departments SET color = '{$color}' WHERE dept_name = '{$name}' AND (color IS NULL OR color = '#0F766E' OR color = '')");
        }
    }
}
