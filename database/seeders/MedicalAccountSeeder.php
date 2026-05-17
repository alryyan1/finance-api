<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class MedicalAccountSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ========== الأصول ==========
            ['code' => '1000',  'name' => 'أصول',                                        'type' => 'asset',     'sub_type' => 'main',   'parent' => null],
            ['code' => '1100',  'name' => 'أصول متداولة',                                'type' => 'asset',     'sub_type' => 'sub',    'parent' => '1000'],
            ['code' => '1101',  'name' => 'الصندوق',                                     'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1100'],
            ['code' => '1102',  'name' => 'الحساب البنكي',                               'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1100'],
            ['code' => '1103',  'name' => 'ذمم مدينة - مرضى نقدي',                      'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1100'],

            // شركات التأمين الصحي - مجموعة
            ['code' => '1104',  'name' => 'ذمم مدينة - شركات التأمين الصحي',            'type' => 'asset',     'sub_type' => 'sub',    'parent' => '1100'],
            ['code' => '11041', 'name' => 'الصندوق القومي للتأمين الصحي',               'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1104'],
            ['code' => '11042', 'name' => 'الشركة الوطنية للتأمين',                     'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1104'],
            ['code' => '11043', 'name' => 'شركة إسلامية للتأمين',                       'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1104'],
            ['code' => '11044', 'name' => 'شركة تأمين خاصة (1)',                        'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1104'],
            ['code' => '11045', 'name' => 'شركة تأمين خاصة (2)',                        'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1104'],
            ['code' => '11046', 'name' => 'شركة تأمين دولية',                           'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1104'],

            ['code' => '1105',  'name' => 'مخزون الأدوية',                              'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1100'],
            ['code' => '1106',  'name' => 'مخزون المستلزمات الطبية',                    'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1100'],
            ['code' => '1107',  'name' => 'مخزون كواشف ومستلزمات المختبر',              'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1100'],
            ['code' => '1108',  'name' => 'مصاريف مدفوعة مقدماً',                       'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1100'],

            ['code' => '1200',  'name' => 'أصول ثابتة',                                 'type' => 'asset',     'sub_type' => 'sub',    'parent' => '1000'],
            ['code' => '1201',  'name' => 'المباني والمنشآت الصحية',                    'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1200'],
            ['code' => '1202',  'name' => 'الأجهزة والمعدات الطبية',                    'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1200'],
            ['code' => '1203',  'name' => 'أجهزة ومعدات المختبر',                       'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1200'],
            ['code' => '1204',  'name' => 'أجهزة الأشعة والتصوير الطبي',                'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1200'],
            ['code' => '1205',  'name' => 'أجهزة الكمبيوتر والمعدات المكتبية',          'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1200'],
            ['code' => '1206',  'name' => 'وسائل النقل',                                'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1200'],
            ['code' => '1207',  'name' => 'مجمع استهلاك المباني',                       'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1200'],
            ['code' => '1208',  'name' => 'مجمع استهلاك الأجهزة الطبية',                'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1200'],
            ['code' => '1209',  'name' => 'مجمع استهلاك أجهزة المختبر',                 'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1200'],

            // ========== الخصوم ==========
            ['code' => '2000',  'name' => 'خصوم',                                        'type' => 'liability', 'sub_type' => 'main',   'parent' => null],
            ['code' => '2100',  'name' => 'خصوم متداولة',                               'type' => 'liability', 'sub_type' => 'sub',    'parent' => '2000'],
            ['code' => '2101',  'name' => 'ذمم دائنة - موردو الأدوية',                  'type' => 'liability', 'sub_type' => 'detail', 'parent' => '2100'],
            ['code' => '2102',  'name' => 'ذمم دائنة - موردو الأجهزة الطبية',           'type' => 'liability', 'sub_type' => 'detail', 'parent' => '2100'],
            ['code' => '2103',  'name' => 'أمانات وضمانات المرضى',                      'type' => 'liability', 'sub_type' => 'detail', 'parent' => '2100'],
            ['code' => '2104',  'name' => 'رواتب مستحقة الدفع',                         'type' => 'liability', 'sub_type' => 'detail', 'parent' => '2100'],
            ['code' => '2105',  'name' => 'ضريبة القيمة المضافة مستحقة',                'type' => 'liability', 'sub_type' => 'detail', 'parent' => '2100'],
            ['code' => '2106',  'name' => 'مستحقات التأمينات الاجتماعية',               'type' => 'liability', 'sub_type' => 'detail', 'parent' => '2100'],
            ['code' => '2200',  'name' => 'خصوم طويلة الأجل',                           'type' => 'liability', 'sub_type' => 'sub',    'parent' => '2000'],
            ['code' => '2201',  'name' => 'قروض طويلة الأجل',                           'type' => 'liability', 'sub_type' => 'detail', 'parent' => '2200'],

            // ========== حقوق الملكية ==========
            ['code' => '3000',  'name' => 'حقوق الملكية',                               'type' => 'equity',    'sub_type' => 'main',   'parent' => null],
            ['code' => '3001',  'name' => 'رأس المال',                                  'type' => 'equity',    'sub_type' => 'detail', 'parent' => '3000'],
            ['code' => '3002',  'name' => 'الأرباح المحتجزة',                           'type' => 'equity',    'sub_type' => 'detail', 'parent' => '3000'],
            ['code' => '3003',  'name' => 'أرباح وخسائر السنة الحالية',                 'type' => 'equity',    'sub_type' => 'detail', 'parent' => '3000'],

            // ========== الإيرادات ==========
            ['code' => '4000',  'name' => 'إيرادات',                                    'type' => 'revenue',   'sub_type' => 'main',   'parent' => null],
            ['code' => '4100',  'name' => 'إيرادات الخدمات الطبية',                     'type' => 'revenue',   'sub_type' => 'sub',    'parent' => '4000'],
            ['code' => '4101',  'name' => 'إيرادات الكشوفات والاستشارات الطبية',        'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4100'],
            ['code' => '4102',  'name' => 'إيرادات العمليات الجراحية والإجراءات',       'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4100'],
            ['code' => '4103',  'name' => 'إيرادات الإقامة والرعاية التمريضية',         'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4100'],
            ['code' => '4104',  'name' => 'إيرادات خدمات الطوارئ والإسعاف',            'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4100'],
            ['code' => '4105',  'name' => 'إيرادات العيادات التخصصية',                  'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4100'],

            ['code' => '4200',  'name' => 'إيرادات المختبر والتشخيص',                   'type' => 'revenue',   'sub_type' => 'sub',    'parent' => '4000'],
            ['code' => '4201',  'name' => 'إيرادات التحاليل المخبرية',                  'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4200'],
            ['code' => '4202',  'name' => 'إيرادات الأشعة والتصوير الطبي',              'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4200'],
            ['code' => '4203',  'name' => 'إيرادات تخطيط القلب والموجات فوق الصوتية',  'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4200'],

            ['code' => '4300',  'name' => 'إيرادات الصيدلية',                           'type' => 'revenue',   'sub_type' => 'sub',    'parent' => '4000'],
            ['code' => '4301',  'name' => 'مبيعات الأدوية والمستحضرات',                 'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4300'],
            ['code' => '4302',  'name' => 'مبيعات المستلزمات الطبية',                   'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4300'],

            // إيرادات شركات التأمين - مجموعة (تعكس هيكل الأصول)
            ['code' => '4400',  'name' => 'إيرادات التأمين الصحي',                      'type' => 'revenue',   'sub_type' => 'sub',    'parent' => '4000'],
            ['code' => '44001', 'name' => 'إيرادات الصندوق القومي للتأمين الصحي',      'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4400'],
            ['code' => '44002', 'name' => 'إيرادات الشركة الوطنية للتأمين',            'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4400'],
            ['code' => '44003', 'name' => 'إيرادات شركة إسلامية للتأمين',              'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4400'],
            ['code' => '44004', 'name' => 'إيرادات شركة تأمين خاصة (1)',               'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4400'],
            ['code' => '44005', 'name' => 'إيرادات شركة تأمين خاصة (2)',               'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4400'],
            ['code' => '44006', 'name' => 'إيرادات شركة تأمين دولية',                  'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4400'],

            ['code' => '4500',  'name' => 'إيرادات أخرى',                              'type' => 'revenue',   'sub_type' => 'sub',    'parent' => '4000'],
            ['code' => '4501',  'name' => 'إيرادات تدريب وتعليم طبي',                  'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4500'],
            ['code' => '4502',  'name' => 'إيرادات متنوعة أخرى',                       'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '4500'],

            // ========== المصروفات ==========
            ['code' => '5000',  'name' => 'مصروفات',                                    'type' => 'expense',   'sub_type' => 'main',   'parent' => null],
            ['code' => '5100',  'name' => 'تكلفة الخدمات المقدمة',                      'type' => 'expense',   'sub_type' => 'sub',    'parent' => '5000'],
            ['code' => '5101',  'name' => 'تكلفة الأدوية المستخدمة',                    'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5100'],
            ['code' => '5102',  'name' => 'تكلفة المستلزمات الطبية المستهلكة',          'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5100'],
            ['code' => '5103',  'name' => 'تكلفة كواشف ومواد المختبر',                  'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5100'],

            ['code' => '5200',  'name' => 'مصروفات الرواتب والأجور',                    'type' => 'expense',   'sub_type' => 'sub',    'parent' => '5000'],
            ['code' => '5201',  'name' => 'رواتب الأطباء والأخصائيين',                  'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5200'],
            ['code' => '5202',  'name' => 'رواتب هيئة التمريض والمساعدين الطبيين',     'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5200'],
            ['code' => '5203',  'name' => 'رواتب فنيي المختبر والأشعة',                 'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5200'],
            ['code' => '5204',  'name' => 'رواتب الكوادر الإدارية',                     'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5200'],
            ['code' => '5205',  'name' => 'مكافآت وعلاوات الموظفين',                   'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5200'],
            ['code' => '5206',  'name' => 'مصروف التأمينات الاجتماعية',                 'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5200'],

            ['code' => '5300',  'name' => 'مصروفات التشغيل',                            'type' => 'expense',   'sub_type' => 'sub',    'parent' => '5000'],
            ['code' => '5301',  'name' => 'مصروف الإيجار',                              'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5300'],
            ['code' => '5302',  'name' => 'مصروف الكهرباء والمياه',                     'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5300'],
            ['code' => '5303',  'name' => 'مصروف الصيانة العامة',                       'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5300'],
            ['code' => '5304',  'name' => 'مصروف صيانة الأجهزة الطبية',                 'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5300'],
            ['code' => '5305',  'name' => 'مصروف التأمين على المنشأة والمعدات',         'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5300'],
            ['code' => '5306',  'name' => 'مصروف التعقيم والتطهير والنظافة',            'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5300'],
            ['code' => '5307',  'name' => 'مصروف التخلص من النفايات الطبية',            'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5300'],

            ['code' => '5400',  'name' => 'مصروفات التسويق والعلاقات العامة',           'type' => 'expense',   'sub_type' => 'sub',    'parent' => '5000'],
            ['code' => '5401',  'name' => 'مصروف الإعلانات والتسويق',                   'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5400'],
            ['code' => '5402',  'name' => 'مصروف ترقيات وعروض خدمية',                  'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5400'],

            ['code' => '5500',  'name' => 'مصروفات إدارية وعمومية',                    'type' => 'expense',   'sub_type' => 'sub',    'parent' => '5000'],
            ['code' => '5501',  'name' => 'مصروفات مكتبية وقرطاسية',                   'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5500'],
            ['code' => '5502',  'name' => 'مصروفات قانونية ومهنية واستشارية',           'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5500'],
            ['code' => '5503',  'name' => 'رسوم الترخيص والاعتماد الصحي',               'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5500'],
            ['code' => '5504',  'name' => 'مصروفات تدريب وتطوير الكوادر',               'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5500'],

            ['code' => '5600',  'name' => 'مصروفات الاستهلاك',                          'type' => 'expense',   'sub_type' => 'sub',    'parent' => '5000'],
            ['code' => '5601',  'name' => 'استهلاك المباني والمنشآت',                   'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5600'],
            ['code' => '5602',  'name' => 'استهلاك الأجهزة الطبية',                     'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5600'],
            ['code' => '5603',  'name' => 'استهلاك أجهزة المختبر والأشعة',              'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5600'],

            ['code' => '5700',  'name' => 'مصروفات مالية',                              'type' => 'expense',   'sub_type' => 'sub',    'parent' => '5000'],
            ['code' => '5701',  'name' => 'فوائد بنكية مدفوعة',                         'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5700'],
            ['code' => '5702',  'name' => 'عمولات وخدمات بنكية',                        'type' => 'expense',   'sub_type' => 'detail', 'parent' => '5700'],
        ];

        $ids = [];

        foreach ($rows as $row) {
            $parentId = $row['parent'] ? ($ids[$row['parent']] ?? null) : null;

            $account = Account::updateOrCreate(
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
    }
}
