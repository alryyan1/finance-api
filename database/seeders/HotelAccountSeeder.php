<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class HotelAccountSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ========== الأصول ==========
            ['code' => '1000', 'name' => 'أصول',                              'type' => 'asset',     'parent' => null],
            ['code' => '1100', 'name' => 'أصول متداولة',                      'type' => 'asset',     'parent' => '1000'],
            ['code' => '1101', 'name' => 'الصندوق',                           'type' => 'asset',     'parent' => '1100'],
            ['code' => '1102', 'name' => 'الحساب البنكي',                     'type' => 'asset',     'parent' => '1100'],
            ['code' => '1103', 'name' => 'ذمم مدينة - عملاء',                 'type' => 'asset',     'parent' => '1100'],
            ['code' => '1104', 'name' => 'مخزون مستلزمات الفندق',            'type' => 'asset',     'parent' => '1100'],
            ['code' => '1105', 'name' => 'مخزون الأغذية والمشروبات',          'type' => 'asset',     'parent' => '1100'],
            ['code' => '1106', 'name' => 'مصاريف مدفوعة مقدماً',             'type' => 'asset',     'parent' => '1100'],
            ['code' => '1200', 'name' => 'أصول ثابتة',                       'type' => 'asset',     'parent' => '1000'],
            ['code' => '1201', 'name' => 'الأراضي والمباني',                  'type' => 'asset',     'parent' => '1200'],
            ['code' => '1202', 'name' => 'الأثاث والتجهيزات',                 'type' => 'asset',     'parent' => '1200'],
            ['code' => '1203', 'name' => 'أجهزة الكمبيوتر والمعدات',         'type' => 'asset',     'parent' => '1200'],
            ['code' => '1204', 'name' => 'وسائل النقل',                      'type' => 'asset',     'parent' => '1200'],
            ['code' => '1205', 'name' => 'مجمع استهلاك المباني',             'type' => 'asset',     'parent' => '1200'],
            ['code' => '1206', 'name' => 'مجمع استهلاك الأثاث والتجهيزات',  'type' => 'asset',     'parent' => '1200'],

            // ========== الخصوم ==========
            ['code' => '2000', 'name' => 'خصوم',                             'type' => 'liability', 'parent' => null],
            ['code' => '2100', 'name' => 'خصوم متداولة',                     'type' => 'liability', 'parent' => '2000'],
            ['code' => '2101', 'name' => 'ذمم دائنة - موردون',               'type' => 'liability', 'parent' => '2100'],
            ['code' => '2102', 'name' => 'إيرادات مؤجلة - حجوزات مسبقة',    'type' => 'liability', 'parent' => '2100'],
            ['code' => '2103', 'name' => 'رواتب مستحقة الدفع',               'type' => 'liability', 'parent' => '2100'],
            ['code' => '2104', 'name' => 'ضريبة القيمة المضافة مستحقة',      'type' => 'liability', 'parent' => '2100'],
            ['code' => '2105', 'name' => 'قروض قصيرة الأجل',                'type' => 'liability', 'parent' => '2100'],
            ['code' => '2200', 'name' => 'خصوم طويلة الأجل',                'type' => 'liability', 'parent' => '2000'],
            ['code' => '2201', 'name' => 'قروض طويلة الأجل',                'type' => 'liability', 'parent' => '2200'],
            ['code' => '2202', 'name' => 'رهن عقاري',                        'type' => 'liability', 'parent' => '2200'],

            // ========== حقوق الملكية ==========
            ['code' => '3000', 'name' => 'حقوق الملكية',                     'type' => 'equity',    'parent' => null],
            ['code' => '3001', 'name' => 'رأس المال',                        'type' => 'equity',    'parent' => '3000'],
            ['code' => '3002', 'name' => 'الأرباح المحتجزة',                 'type' => 'equity',    'parent' => '3000'],
            ['code' => '3003', 'name' => 'أرباح وخسائر السنة الحالية',       'type' => 'equity',    'parent' => '3000'],

            // ========== الإيرادات ==========
            ['code' => '4000', 'name' => 'إيرادات',                          'type' => 'revenue',   'parent' => null],
            ['code' => '4100', 'name' => 'إيرادات الغرف',                    'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4101', 'name' => 'إيجار الغرف العادية',              'type' => 'revenue',   'parent' => '4100'],
            ['code' => '4102', 'name' => 'إيجار الأجنحة',                    'type' => 'revenue',   'parent' => '4100'],
            ['code' => '4103', 'name' => 'إيجار غرف VIP',                   'type' => 'revenue',   'parent' => '4100'],
            ['code' => '4200', 'name' => 'إيرادات الأغذية والمشروبات',       'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4201', 'name' => 'إيرادات المطعم',                   'type' => 'revenue',   'parent' => '4200'],
            ['code' => '4202', 'name' => 'إيرادات الكافيتيريا والكافيه',     'type' => 'revenue',   'parent' => '4200'],
            ['code' => '4203', 'name' => 'إيرادات ميني بار',                 'type' => 'revenue',   'parent' => '4200'],
            ['code' => '4300', 'name' => 'إيرادات الخدمات الإضافية',         'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4301', 'name' => 'إيرادات قاعات الاجتماعات والمؤتمرات', 'type' => 'revenue', 'parent' => '4300'],
            ['code' => '4302', 'name' => 'إيرادات السبا واللياقة البدنية',   'type' => 'revenue',   'parent' => '4300'],
            ['code' => '4303', 'name' => 'إيرادات خدمات الغسيل والكي',       'type' => 'revenue',   'parent' => '4300'],
            ['code' => '4304', 'name' => 'إيرادات البوفيهات والحفلات',       'type' => 'revenue',   'parent' => '4300'],
            ['code' => '4400', 'name' => 'إيرادات أخرى',                    'type' => 'revenue',   'parent' => '4000'],
            ['code' => '4401', 'name' => 'إيرادات خدمات الاتصالات',         'type' => 'revenue',   'parent' => '4400'],
            ['code' => '4402', 'name' => 'إيرادات متنوعة أخرى',             'type' => 'revenue',   'parent' => '4400'],

            // ========== المصروفات ==========
            ['code' => '5000', 'name' => 'مصروفات',                          'type' => 'expense',   'parent' => null],
            ['code' => '5100', 'name' => 'تكلفة المبيعات',                   'type' => 'expense',   'parent' => '5000'],
            ['code' => '5101', 'name' => 'تكلفة الأغذية والمشروبات',         'type' => 'expense',   'parent' => '5100'],
            ['code' => '5102', 'name' => 'تكلفة مستلزمات الغرف',            'type' => 'expense',   'parent' => '5100'],
            ['code' => '5200', 'name' => 'مصروفات الرواتب والأجور',          'type' => 'expense',   'parent' => '5000'],
            ['code' => '5201', 'name' => 'رواتب وأجور الموظفين',             'type' => 'expense',   'parent' => '5200'],
            ['code' => '5202', 'name' => 'مكافآت وعلاوات',                  'type' => 'expense',   'parent' => '5200'],
            ['code' => '5203', 'name' => 'مصروف التأمينات الاجتماعية',       'type' => 'expense',   'parent' => '5200'],
            ['code' => '5300', 'name' => 'مصروفات التشغيل',                  'type' => 'expense',   'parent' => '5000'],
            ['code' => '5301', 'name' => 'مصروف الإيجار',                    'type' => 'expense',   'parent' => '5300'],
            ['code' => '5302', 'name' => 'مصروف الكهرباء والمياه',           'type' => 'expense',   'parent' => '5300'],
            ['code' => '5303', 'name' => 'مصروف الصيانة والإصلاحات',         'type' => 'expense',   'parent' => '5300'],
            ['code' => '5304', 'name' => 'مصروف التأمين',                    'type' => 'expense',   'parent' => '5300'],
            ['code' => '5305', 'name' => 'مصروف الاتصالات والإنترنت',        'type' => 'expense',   'parent' => '5300'],
            ['code' => '5400', 'name' => 'مصروفات التسويق والمبيعات',        'type' => 'expense',   'parent' => '5000'],
            ['code' => '5401', 'name' => 'مصروف الإعلانات والتسويق',         'type' => 'expense',   'parent' => '5400'],
            ['code' => '5402', 'name' => 'عمولات الحجز والوكالات السياحية',  'type' => 'expense',   'parent' => '5400'],
            ['code' => '5500', 'name' => 'مصروفات إدارية وعمومية',           'type' => 'expense',   'parent' => '5000'],
            ['code' => '5501', 'name' => 'مصروفات مكتبية وقرطاسية',         'type' => 'expense',   'parent' => '5500'],
            ['code' => '5502', 'name' => 'مصروفات قانونية ومهنية',           'type' => 'expense',   'parent' => '5500'],
            ['code' => '5503', 'name' => 'مصروفات ترفيه واستقبال',           'type' => 'expense',   'parent' => '5500'],
            ['code' => '5600', 'name' => 'مصروفات الاستهلاك',                'type' => 'expense',   'parent' => '5000'],
            ['code' => '5601', 'name' => 'استهلاك المباني',                  'type' => 'expense',   'parent' => '5600'],
            ['code' => '5602', 'name' => 'استهلاك الأثاث والتجهيزات',        'type' => 'expense',   'parent' => '5600'],
            ['code' => '5700', 'name' => 'مصروفات مالية',                    'type' => 'expense',   'parent' => '5000'],
            ['code' => '5701', 'name' => 'فوائد بنكية مدفوعة',              'type' => 'expense',   'parent' => '5700'],
            ['code' => '5702', 'name' => 'عمولات وخدمات بنكية',             'type' => 'expense',   'parent' => '5700'],
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
