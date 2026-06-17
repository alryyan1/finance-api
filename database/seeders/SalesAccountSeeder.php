<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

/**
 * دليل الحسابات لنشاط تجاري (مبيعات / تجارة)
 *
 * التسلسل الهرمي:
 *   1000  أصول
 *     1100  أصول متداولة      (current)
 *     1200  أصول غير متداولة  (non_current)
 *   2000  خصوم
 *     2100  خصوم متداولة      (current)
 *     2200  خصوم طويلة الأجل  (long_term)
 *   3000  حقوق الملكية
 *   4000  إيرادات
 *     4100  إيرادات المبيعات
 *     4200  إيرادات أخرى
 *   5000  مصروفات
 *     5100  تكلفة المبيعات
 *     5200  رواتب وأجور
 *     5300  إيجار ومرافق
 *     5400  تسويق ومبيعات
 *     5500  مصروفات إدارية عامة
 *     5600  مصروفات مالية
 *     5700  إهلاك
 *     5800  ضرائب وزكاة
 */
class SalesAccountSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [

            // ══════════════════════════════════════════════════════════════════
            // 1 — ASSETS
            // ══════════════════════════════════════════════════════════════════
            ['code' => '1000', 'name' => 'الأصول',                              'type' => 'asset',     'sub_type' => null,          'parent' => null],

            // ── Current assets ─────────────────────────────────────────────
            ['code' => '1100', 'name' => 'الأصول المتداولة',                    'type' => 'asset',     'sub_type' => 'current',     'parent' => '1000'],
            ['code' => '1101', 'name' => 'الصندوق (نقدية)',                     'type' => 'asset',     'sub_type' => 'current',     'parent' => '1100'],
            ['code' => '1102', 'name' => 'صندوق النثريات',                      'type' => 'asset',     'sub_type' => 'current',     'parent' => '1100'],
            ['code' => '1103', 'name' => 'البنك — الحساب الجاري',               'type' => 'asset',     'sub_type' => 'current',     'parent' => '1100'],
            ['code' => '1104', 'name' => 'ذمم مدينة — عملاء',                   'type' => 'asset',     'sub_type' => 'current',     'parent' => '1100'],
            ['code' => '1105', 'name' => 'أوراق قبض',                           'type' => 'asset',     'sub_type' => 'current',     'parent' => '1100'],
            ['code' => '1106', 'name' => 'مخزون البضاعة',                       'type' => 'asset',     'sub_type' => 'current',     'parent' => '1100'],
            ['code' => '1107', 'name' => 'مصروفات مدفوعة مقدماً',               'type' => 'asset',     'sub_type' => 'current',     'parent' => '1100'],
            ['code' => '1108', 'name' => 'إيجار مدفوع مقدماً',                  'type' => 'asset',     'sub_type' => 'current',     'parent' => '1100'],
            ['code' => '1109', 'name' => 'ضريبة القيمة المضافة — مدخلة',        'type' => 'asset',     'sub_type' => 'current',     'parent' => '1100'],
            ['code' => '1110', 'name' => 'دفعات مقدمة للموردين',                'type' => 'asset',     'sub_type' => 'current',     'parent' => '1100'],

            // ── Non-current assets ─────────────────────────────────────────
            ['code' => '1200', 'name' => 'الأصول غير المتداولة',                'type' => 'asset',     'sub_type' => 'non_current', 'parent' => '1000'],
            ['code' => '1201', 'name' => 'أراضي',                               'type' => 'asset',     'sub_type' => 'non_current', 'parent' => '1200'],
            ['code' => '1202', 'name' => 'مباني',                               'type' => 'asset',     'sub_type' => 'non_current', 'parent' => '1200'],
            ['code' => '1203', 'name' => 'مركبات',                              'type' => 'asset',     'sub_type' => 'non_current', 'parent' => '1200'],
            ['code' => '1204', 'name' => 'أثاث ومعدات مكتبية',                  'type' => 'asset',     'sub_type' => 'non_current', 'parent' => '1200'],
            ['code' => '1205', 'name' => 'أجهزة حاسب آلي ومعدات تقنية',        'type' => 'asset',     'sub_type' => 'non_current', 'parent' => '1200'],
            ['code' => '1206', 'name' => 'معدات وآلات',                         'type' => 'asset',     'sub_type' => 'non_current', 'parent' => '1200'],
            ['code' => '1290', 'name' => 'مجمع إهلاك الأصول الثابتة',           'type' => 'asset',     'sub_type' => 'non_current', 'parent' => '1200'],

            // ══════════════════════════════════════════════════════════════════
            // 2 — LIABILITIES
            // ══════════════════════════════════════════════════════════════════
            ['code' => '2000', 'name' => 'الخصوم',                              'type' => 'liability', 'sub_type' => null,          'parent' => null],

            // ── Current liabilities ────────────────────────────────────────
            ['code' => '2100', 'name' => 'الخصوم المتداولة',                    'type' => 'liability', 'sub_type' => 'current',     'parent' => '2000'],
            ['code' => '2101', 'name' => 'ذمم دائنة — موردون',                  'type' => 'liability', 'sub_type' => 'current',     'parent' => '2100'],
            ['code' => '2102', 'name' => 'أوراق دفع',                           'type' => 'liability', 'sub_type' => 'current',     'parent' => '2100'],
            ['code' => '2103', 'name' => 'رواتب مستحقة الصرف',                  'type' => 'liability', 'sub_type' => 'current',     'parent' => '2100'],
            ['code' => '2104', 'name' => 'ضريبة القيمة المضافة — مستحقة',       'type' => 'liability', 'sub_type' => 'current',     'parent' => '2100'],
            ['code' => '2105', 'name' => 'مقدمات العملاء',                      'type' => 'liability', 'sub_type' => 'current',     'parent' => '2100'],
            ['code' => '2106', 'name' => 'قروض قصيرة الأجل',                    'type' => 'liability', 'sub_type' => 'current',     'parent' => '2100'],
            ['code' => '2107', 'name' => 'مصروفات مستحقة',                      'type' => 'liability', 'sub_type' => 'current',     'parent' => '2100'],
            ['code' => '2108', 'name' => 'ضرائب مستحقة',                        'type' => 'liability', 'sub_type' => 'current',     'parent' => '2100'],

            // ── Long-term liabilities ──────────────────────────────────────
            ['code' => '2200', 'name' => 'الخصوم طويلة الأجل',                  'type' => 'liability', 'sub_type' => 'long_term',   'parent' => '2000'],
            ['code' => '2201', 'name' => 'قروض بنكية طويلة الأجل',              'type' => 'liability', 'sub_type' => 'long_term',   'parent' => '2200'],
            ['code' => '2202', 'name' => 'مخصص مكافأة نهاية الخدمة',            'type' => 'liability', 'sub_type' => 'long_term',   'parent' => '2200'],

            // ══════════════════════════════════════════════════════════════════
            // 3 — EQUITY
            // ══════════════════════════════════════════════════════════════════
            ['code' => '3000', 'name' => 'حقوق الملكية',                        'type' => 'equity',    'sub_type' => null,          'parent' => null],
            ['code' => '3001', 'name' => 'رأس المال',                           'type' => 'equity',    'sub_type' => null,          'parent' => '3000'],
            ['code' => '3002', 'name' => 'الأرباح المحتجزة',                    'type' => 'equity',    'sub_type' => null,          'parent' => '3000'],
            ['code' => '3003', 'name' => 'مسحوبات الشريك / المالك',              'type' => 'equity',    'sub_type' => null,          'parent' => '3000'],
            ['code' => '3004', 'name' => 'احتياطي قانوني',                      'type' => 'equity',    'sub_type' => null,          'parent' => '3000'],

            // ══════════════════════════════════════════════════════════════════
            // 4 — REVENUE
            // ══════════════════════════════════════════════════════════════════
            ['code' => '4000', 'name' => 'الإيرادات',                           'type' => 'revenue',   'sub_type' => null,          'parent' => null],

            // ── Sales revenue ──────────────────────────────────────────────
            ['code' => '4100', 'name' => 'إيرادات المبيعات',                    'type' => 'revenue',   'sub_type' => null,          'parent' => '4000'],
            ['code' => '4101', 'name' => 'مبيعات نقدية',                        'type' => 'revenue',   'sub_type' => null,          'parent' => '4100'],
            ['code' => '4102', 'name' => 'مبيعات آجلة',                         'type' => 'revenue',   'sub_type' => null,          'parent' => '4100'],
            ['code' => '4103', 'name' => 'مردودات المبيعات',                    'type' => 'revenue',   'sub_type' => null,          'parent' => '4100'],
            ['code' => '4104', 'name' => 'خصم مبيعات ممنوح',                    'type' => 'revenue',   'sub_type' => null,          'parent' => '4100'],

            // ── Other revenue ──────────────────────────────────────────────
            ['code' => '4200', 'name' => 'إيرادات أخرى',                        'type' => 'revenue',   'sub_type' => null,          'parent' => '4000'],
            ['code' => '4201', 'name' => 'إيرادات فوائد بنكية',                 'type' => 'revenue',   'sub_type' => null,          'parent' => '4200'],
            ['code' => '4202', 'name' => 'خصم مشتريات مكتسب',                   'type' => 'revenue',   'sub_type' => null,          'parent' => '4200'],
            ['code' => '4203', 'name' => 'أرباح بيع أصول ثابتة',                'type' => 'revenue',   'sub_type' => null,          'parent' => '4200'],
            ['code' => '4204', 'name' => 'إيرادات متنوعة',                      'type' => 'revenue',   'sub_type' => null,          'parent' => '4200'],

            // ══════════════════════════════════════════════════════════════════
            // 5 — EXPENSES
            // ══════════════════════════════════════════════════════════════════
            ['code' => '5000', 'name' => 'المصروفات',                           'type' => 'expense',   'sub_type' => null,          'parent' => null],

            // ── Cost of sales ──────────────────────────────────────────────
            ['code' => '5100', 'name' => 'تكلفة المبيعات',                      'type' => 'expense',   'sub_type' => null,          'parent' => '5000'],
            ['code' => '5101', 'name' => 'مشتريات البضاعة',                     'type' => 'expense',   'sub_type' => null,          'parent' => '5100'],
            ['code' => '5102', 'name' => 'مردودات المشتريات',                   'type' => 'expense',   'sub_type' => null,          'parent' => '5100'],
            ['code' => '5103', 'name' => 'مصاريف الشحن والنقل الواردة',          'type' => 'expense',   'sub_type' => null,          'parent' => '5100'],
            ['code' => '5104', 'name' => 'رسوم جمارك واستيراد',                  'type' => 'expense',   'sub_type' => null,          'parent' => '5100'],
            ['code' => '5105', 'name' => 'هبوط قيمة المخزون',                   'type' => 'expense',   'sub_type' => null,          'parent' => '5100'],

            // ── Salaries & personnel ───────────────────────────────────────
            ['code' => '5200', 'name' => 'رواتب وأجور',                         'type' => 'expense',   'sub_type' => null,          'parent' => '5000'],
            ['code' => '5201', 'name' => 'رواتب الموظفين',                      'type' => 'expense',   'sub_type' => null,          'parent' => '5200'],
            ['code' => '5202', 'name' => 'أجور العمال',                         'type' => 'expense',   'sub_type' => null,          'parent' => '5200'],
            ['code' => '5203', 'name' => 'مكافآت وحوافز',                       'type' => 'expense',   'sub_type' => null,          'parent' => '5200'],
            ['code' => '5204', 'name' => 'تأمينات اجتماعية',                    'type' => 'expense',   'sub_type' => null,          'parent' => '5200'],
            ['code' => '5205', 'name' => 'مكافأة نهاية الخدمة',                  'type' => 'expense',   'sub_type' => null,          'parent' => '5200'],

            // ── Rent & utilities ───────────────────────────────────────────
            ['code' => '5300', 'name' => 'إيجار ومرافق',                        'type' => 'expense',   'sub_type' => null,          'parent' => '5000'],
            ['code' => '5301', 'name' => 'مصروف الإيجار',                       'type' => 'expense',   'sub_type' => null,          'parent' => '5300'],
            ['code' => '5302', 'name' => 'كهرباء وماء',                         'type' => 'expense',   'sub_type' => null,          'parent' => '5300'],
            ['code' => '5303', 'name' => 'هاتف وإنترنت',                        'type' => 'expense',   'sub_type' => null,          'parent' => '5300'],

            // ── Marketing & sales ──────────────────────────────────────────
            ['code' => '5400', 'name' => 'مصروفات التسويق والمبيعات',           'type' => 'expense',   'sub_type' => null,          'parent' => '5000'],
            ['code' => '5401', 'name' => 'إعلان وتسويق',                        'type' => 'expense',   'sub_type' => null,          'parent' => '5400'],
            ['code' => '5402', 'name' => 'عمولات مندوبي المبيعات',              'type' => 'expense',   'sub_type' => null,          'parent' => '5400'],
            ['code' => '5403', 'name' => 'مصروف التوصيل والشحن الصادرة',        'type' => 'expense',   'sub_type' => null,          'parent' => '5400'],
            ['code' => '5404', 'name' => 'عبوات وتغليف',                        'type' => 'expense',   'sub_type' => null,          'parent' => '5400'],

            // ── General & administrative ───────────────────────────────────
            ['code' => '5500', 'name' => 'المصروفات الإدارية العامة',           'type' => 'expense',   'sub_type' => null,          'parent' => '5000'],
            ['code' => '5501', 'name' => 'قرطاسية ومستلزمات مكتبية',            'type' => 'expense',   'sub_type' => null,          'parent' => '5500'],
            ['code' => '5502', 'name' => 'صيانة وإصلاح',                        'type' => 'expense',   'sub_type' => null,          'parent' => '5500'],
            ['code' => '5503', 'name' => 'وقود ومواصلات',                       'type' => 'expense',   'sub_type' => null,          'parent' => '5500'],
            ['code' => '5504', 'name' => 'تأمين',                               'type' => 'expense',   'sub_type' => null,          'parent' => '5500'],
            ['code' => '5505', 'name' => 'أتعاب قانونية ومحاسبية',              'type' => 'expense',   'sub_type' => null,          'parent' => '5500'],
            ['code' => '5506', 'name' => 'ضيافة واستقبال',                      'type' => 'expense',   'sub_type' => null,          'parent' => '5500'],
            ['code' => '5507', 'name' => 'اشتراكات ومصروفات متنوعة',             'type' => 'expense',   'sub_type' => null,          'parent' => '5500'],
            ['code' => '5508', 'name' => 'ديون معدومة',                         'type' => 'expense',   'sub_type' => null,          'parent' => '5500'],

            // ── Financial expenses ─────────────────────────────────────────
            ['code' => '5600', 'name' => 'المصروفات المالية',                   'type' => 'expense',   'sub_type' => null,          'parent' => '5000'],
            ['code' => '5601', 'name' => 'رسوم ومصاريف بنكية',                  'type' => 'expense',   'sub_type' => null,          'parent' => '5600'],
            ['code' => '5602', 'name' => 'فوائد قروض',                          'type' => 'expense',   'sub_type' => null,          'parent' => '5600'],
            ['code' => '5603', 'name' => 'خسائر فروق العملة',                   'type' => 'expense',   'sub_type' => null,          'parent' => '5600'],

            // ── Depreciation ───────────────────────────────────────────────
            ['code' => '5700', 'name' => 'الإهلاك',                             'type' => 'expense',   'sub_type' => null,          'parent' => '5000'],
            ['code' => '5701', 'name' => 'مصروف إهلاك المباني',                 'type' => 'expense',   'sub_type' => null,          'parent' => '5700'],
            ['code' => '5702', 'name' => 'مصروف إهلاك المركبات',                'type' => 'expense',   'sub_type' => null,          'parent' => '5700'],
            ['code' => '5703', 'name' => 'مصروف إهلاك الأثاث والمعدات',         'type' => 'expense',   'sub_type' => null,          'parent' => '5700'],
            ['code' => '5704', 'name' => 'مصروف إهلاك الأجهزة والتقنية',        'type' => 'expense',   'sub_type' => null,          'parent' => '5700'],

            // ── Tax & zakat ────────────────────────────────────────────────
            ['code' => '5800', 'name' => 'ضرائب وزكاة',                         'type' => 'expense',   'sub_type' => null,          'parent' => '5000'],
            ['code' => '5801', 'name' => 'ضريبة الدخل',                         'type' => 'expense',   'sub_type' => null,          'parent' => '5800'],
            ['code' => '5802', 'name' => 'الزكاة',                              'type' => 'expense',   'sub_type' => null,          'parent' => '5800'],
        ];

        $ids = [];

        foreach ($rows as $row) {
            $parentId = isset($row['parent']) ? ($ids[$row['parent']] ?? null) : null;

            $account = Account::firstOrCreate(
                ['code' => $row['code']],
                [
                    'name'      => $row['name'],
                    'type'      => $row['type'],
                    'sub_type'  => $row['sub_type'],
                    'parent_id' => $parentId,
                    'is_active' => true,
                ]
            );

            $ids[$row['code']] = $account->id;
        }

        $this->command->info('✓ تم إنشاء ' . count($rows) . ' حساب لنشاط المبيعات التجاري.');
    }
}
