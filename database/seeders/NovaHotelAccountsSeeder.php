<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class NovaHotelAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            // ===== الأصول =====
            ['code' => '1000', 'name' => 'الأصول',              'type' => 'asset',     'sub_type' => 'main',   'parent_code' => null],
            ['code' => '1100', 'name' => 'النقدية والبنوك',      'type' => 'asset',     'sub_type' => 'sub',    'parent_code' => '1000'],
            ['code' => '1110', 'name' => 'الصندوق',              'type' => 'asset',     'sub_type' => 'detail', 'parent_code' => '1100'],
            ['code' => '1120', 'name' => 'البنك',                'type' => 'asset',     'sub_type' => 'detail', 'parent_code' => '1100'],
            ['code' => '1200', 'name' => 'الأثاث والأجهزة',      'type' => 'asset',     'sub_type' => 'sub',    'parent_code' => '1000'],
            ['code' => '1210', 'name' => 'الأثاث',               'type' => 'asset',     'sub_type' => 'detail', 'parent_code' => '1200'],
            ['code' => '1220', 'name' => 'المكيفات',              'type' => 'asset',     'sub_type' => 'detail', 'parent_code' => '1200'],
            ['code' => '1230', 'name' => 'الغسالات',              'type' => 'asset',     'sub_type' => 'detail', 'parent_code' => '1200'],
            ['code' => '1240', 'name' => 'المولد',                'type' => 'asset',     'sub_type' => 'detail', 'parent_code' => '1200'],

            // ===== الالتزامات =====
            ['code' => '2000', 'name' => 'الالتزامات',           'type' => 'liability', 'sub_type' => 'main',   'parent_code' => null],
            ['code' => '2100', 'name' => 'الموردون',              'type' => 'liability', 'sub_type' => 'detail', 'parent_code' => '2000'],
            ['code' => '2200', 'name' => 'رواتب مستحقة',         'type' => 'liability', 'sub_type' => 'detail', 'parent_code' => '2000'],
            ['code' => '2300', 'name' => 'إيجار المبنى',          'type' => 'liability', 'sub_type' => 'detail', 'parent_code' => '2000'],

            // ===== حقوق الملكية =====
            ['code' => '3000', 'name' => 'حقوق الملكية',         'type' => 'equity',    'sub_type' => 'main',   'parent_code' => null],
            ['code' => '3100', 'name' => 'رأس المال',             'type' => 'equity',    'sub_type' => 'detail', 'parent_code' => '3000'],

            // ===== الإيرادات =====
            ['code' => '4000', 'name' => 'الإيرادات',            'type' => 'revenue',   'sub_type' => 'main',   'parent_code' => null],
            ['code' => '4100', 'name' => 'إيرادات إيجار الشقق',  'type' => 'revenue',   'sub_type' => 'detail', 'parent_code' => '4000'],
            ['code' => '4200', 'name' => 'إيرادات التمديد',       'type' => 'revenue',   'sub_type' => 'detail', 'parent_code' => '4000'],
            ['code' => '4300', 'name' => 'إيرادات خدمات إضافية', 'type' => 'revenue',   'sub_type' => 'detail', 'parent_code' => '4000'],

            // ===== المصروفات =====
            ['code' => '5000', 'name' => 'المصروفات التشغيلية',  'type' => 'expense',   'sub_type' => 'main',   'parent_code' => null],

            // الكهرباء والمياه
            ['code' => '5100', 'name' => 'الكهرباء والمياه',      'type' => 'expense',   'sub_type' => 'sub',    'parent_code' => '5000'],
            ['code' => '5110', 'name' => 'كهرباء الشقق',          'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5100'],
            ['code' => '5120', 'name' => 'كهرباء المولد',          'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5100'],
            ['code' => '5130', 'name' => 'موية الشقق',            'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5100'],
            ['code' => '5140', 'name' => 'موية جالون للمولد',     'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5100'],

            // النظافة والصيانة
            ['code' => '5200', 'name' => 'النظافة والصيانة',      'type' => 'expense',   'sub_type' => 'sub',    'parent_code' => '5000'],
            ['code' => '5210', 'name' => 'مواد نظافة',            'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5200'],
            ['code' => '5220', 'name' => 'صيانة كهرباء',          'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5200'],
            ['code' => '5230', 'name' => 'صيانة سباكة',           'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5200'],
            ['code' => '5240', 'name' => 'صيانة التكييف',          'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5200'],

            // الضيافة والتموين
            ['code' => '5300', 'name' => 'الضيافة والتموين',      'type' => 'expense',   'sub_type' => 'sub',    'parent_code' => '5000'],
            ['code' => '5310', 'name' => 'تموينة ضيافة',          'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5300'],
            ['code' => '5320', 'name' => 'كباي وأكواب',           'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5300'],
            ['code' => '5330', 'name' => 'شاي وقهوة وسكر',        'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5300'],
            ['code' => '5340', 'name' => 'كمامات وقفازات',        'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5300'],

            // المصروفات الإدارية
            ['code' => '5400', 'name' => 'المصروفات الإدارية',    'type' => 'expense',   'sub_type' => 'sub',    'parent_code' => '5000'],
            ['code' => '5410', 'name' => 'رواتب الموظفين',        'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5400'],
            ['code' => '5420', 'name' => 'ترحيل ومواصلات',        'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5400'],
            ['code' => '5430', 'name' => 'مصروف مكتب',            'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5400'],
            ['code' => '5440', 'name' => 'إنترنت واتصالات',       'type' => 'expense',   'sub_type' => 'detail', 'parent_code' => '5400'],
        ];

        foreach ($accounts as $data) {
            $parentId = null;
            if ($data['parent_code'] !== null) {
                $parentId = Account::where('code', $data['parent_code'])->value('id');
            }

            Account::updateOrCreate(
                ['code' => $data['code']],
                [
                    'name'      => $data['name'],
                    'type'      => $data['type'],
                    'sub_type'  => $data['sub_type'],
                    'parent_id' => $parentId,
                    'is_active' => true,
                ]
            );
        }
    }
}
