<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => '1000', 'name' => 'أصول',                  'type' => 'asset',     'parent' => null],
            ['code' => '1100', 'name' => 'أصول متداولة',          'type' => 'asset',     'parent' => '1000'],
            ['code' => '1101', 'name' => 'النقدية',               'type' => 'asset',     'parent' => '1100'],
            ['code' => '1102', 'name' => 'الحساب البنكي',         'type' => 'asset',     'parent' => '1100'],
            ['code' => '1103', 'name' => 'ذمم مدينة',             'type' => 'asset',     'parent' => '1100'],
            ['code' => '1200', 'name' => 'أصول ثابتة',            'type' => 'asset',     'parent' => '1000'],
            ['code' => '1201', 'name' => 'معدات وأجهزة',          'type' => 'asset',     'parent' => '1200'],
            ['code' => '2000', 'name' => 'خصوم',                  'type' => 'liability', 'parent' => null],
            ['code' => '2100', 'name' => 'خصوم متداولة',          'type' => 'liability', 'parent' => '2000'],
            ['code' => '2101', 'name' => 'ذمم دائنة',             'type' => 'liability', 'parent' => '2100'],
            ['code' => '2102', 'name' => 'قروض قصيرة الأجل',      'type' => 'liability', 'parent' => '2100'],
            ['code' => '3000', 'name' => 'حقوق الملكية',          'type' => 'equity',    'parent' => null],
            ['code' => '3001', 'name' => 'رأس المال',             'type' => 'equity',    'parent' => '3000'],
            ['code' => '3002', 'name' => 'الأرباح المحتجزة',      'type' => 'equity',    'parent' => '3000'],
            ['code' => '4000', 'name' => 'إيرادات',               'type' => 'revenue',   'parent' => null],
            ['code' => '4001', 'name' => 'إيرادات المبيعات',      'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4002', 'name' => 'إيرادات أخرى',          'type' => 'revenue',   'parent' => '4000'],
            ['code' => '5000', 'name' => 'مصروفات',               'type' => 'expense',   'parent' => null],
            ['code' => '5001', 'name' => 'مصروف الإيجار',         'type' => 'expense',   'parent' => '5000'],
            ['code' => '5002', 'name' => 'مصروف الرواتب',         'type' => 'expense',   'parent' => '5000'],
            ['code' => '5003', 'name' => 'مصروفات إدارية',        'type' => 'expense',   'parent' => '5000'],
        ];

        $ids = [];

        foreach ($rows as $row) {
            $parentId = $row['parent'] ? ($ids[$row['parent']] ?? null) : null;

            $account = Account::firstOrCreate(
                ['code' => $row['code']],
                ['name' => $row['name'], 'type' => $row['type'], 'parent_id' => $parentId, 'is_active' => true]
            );

            $ids[$row['code']] = $account->id;
        }
    }
}
