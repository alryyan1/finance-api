<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class MedicalAccountSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // ========== 1. الأصول (Assets) ==========
            ['code' => '1',      'name' => 'الأصول',                                                      'type' => 'asset',     'sub_type' => 'main',   'parent' => null],
            ['code' => '11',     'name' => 'الأصول المتداولة',                                             'type' => 'asset',     'sub_type' => 'sub',    'parent' => '1'],

            // 111 النقدية وما يعادلها
            ['code' => '111',    'name' => 'النقدية وما يعادلها',                                          'type' => 'asset',     'sub_type' => 'sub',    'parent' => '11'],

            // 1111 الصناديق
            ['code' => '1111',   'name' => 'الصناديق (الخزائن الفرعية والرئيسية)',                        'type' => 'asset',     'sub_type' => 'sub',    'parent' => '111'],
            ['code' => '111101', 'name' => 'صندوق استقبال الوردية (الاستقبال 1)',                          'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1111'],
            ['code' => '111102', 'name' => 'صندوق استقبال الوردية (الاستقبال 2)',                          'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1111'],
            ['code' => '111103', 'name' => 'الصندوق الرئيسي للمركز (خزينة المحاسب)',                      'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1111'],

            // 1112 البنوك
            ['code' => '1112',   'name' => 'البنوك',                                                       'type' => 'asset',     'sub_type' => 'sub',    'parent' => '111'],
            ['code' => '111201', 'name' => 'حساب بنك المركز الرئيسي',                                     'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1112'],

            // 1113 حسابات تسوية وسيطة
            ['code' => '1113',   'name' => 'حسابات تسوية وسيطة',                                          'type' => 'asset',     'sub_type' => 'sub',    'parent' => '111'],
            ['code' => '111301', 'name' => 'حساب وسيط تحويلات موظفي الاستقبال',                            'type' => 'asset',     'sub_type' => 'detail', 'parent' => '1113'],

            // 112 المدينون والذمم التجارية
            ['code' => '112',    'name' => 'المدينون والذمم التجارية',                                     'type' => 'asset',     'sub_type' => 'sub',    'parent' => '11'],
            ['code' => '1121',   'name' => 'ذمم شركات التأمين',                                            'type' => 'asset',     'sub_type' => 'sub',    'parent' => '112'],

            // ========== 2. الالتزامات (Liabilities) ==========
            ['code' => '2',      'name' => 'الخصوم والالتزامات',                                           'type' => 'liability', 'sub_type' => 'main',   'parent' => null],
            ['code' => '21',     'name' => 'الالتزامات المتداولة',                                         'type' => 'liability', 'sub_type' => 'sub',    'parent' => '2'],

            // 211 أرصدة دائنة أخرى
            ['code' => '211',    'name' => 'أرصدة دائنة أخرى',                                            'type' => 'liability', 'sub_type' => 'sub',    'parent' => '21'],
            ['code' => '2111',   'name' => 'مستحقات الأطباء (محفظة الذمم الخاصة بالأطباء)',               'type' => 'liability', 'sub_type' => 'sub',    'parent' => '211'],

            // 212 الموردون
            ['code' => '212',    'name' => 'الموردون (ذمم دائنة تجارية)',                                  'type' => 'liability', 'sub_type' => 'sub',    'parent' => '21'],
            ['code' => '212101', 'name' => 'ذمم موردي المواد والمستهلكات الطبية والمخبرية',               'type' => 'liability', 'sub_type' => 'detail', 'parent' => '212'],

            // ========== 3. حقوق الملكية (Equity) ==========
            ['code' => '3',      'name' => 'حقوق الملكية',                                                 'type' => 'equity',    'sub_type' => 'main',   'parent' => null],

            // 31 رأس المال
            ['code' => '31',     'name' => 'رأس المال',                                                    'type' => 'equity',    'sub_type' => 'sub',    'parent' => '3'],
            ['code' => '311001', 'name' => 'رأس مال الشركاء (حسب السجل التجاري للمؤسسة)',                 'type' => 'equity',    'sub_type' => 'detail', 'parent' => '31'],

            // 32 الأرباح المبقاة / المدورة
            ['code' => '32',     'name' => 'الأرباح المبقاة / المدورة',                                    'type' => 'equity',    'sub_type' => 'sub',    'parent' => '3'],
            ['code' => '321001', 'name' => 'أرباح وخسائر سنوات سابقة',                                    'type' => 'equity',    'sub_type' => 'detail', 'parent' => '32'],

            // 33 صافي أرباح/خسائر العام الحالي
            ['code' => '33',     'name' => 'صافي أرباح/خسائر العام الحالي',                               'type' => 'equity',    'sub_type' => 'sub',    'parent' => '3'],
            ['code' => '331001', 'name' => 'أرباح وخسائر العام الحالي',                                    'type' => 'equity',    'sub_type' => 'detail', 'parent' => '33'],

            // ========== 4. الإيرادات (Revenues) ==========
            ['code' => '4',      'name' => 'الإيرادات',                                                    'type' => 'revenue',   'sub_type' => 'main',   'parent' => null],
            ['code' => '41',     'name' => 'الإيرادات التشغيلية الطبية',                                   'type' => 'revenue',   'sub_type' => 'sub',    'parent' => '4'],

            // 411 إيرادات الكشوفات والخدمات الطبية
            ['code' => '411',    'name' => 'إيرادات الكشوفات والخدمات الطبية',                             'type' => 'revenue',   'sub_type' => 'sub',    'parent' => '41'],
            ['code' => '411101', 'name' => 'إيرادات كشوفات عيادة (أ) - نقدي وتأمين',                     'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '411'],
            ['code' => '411102', 'name' => 'إيرادات كشوفات عيادة (ب) - نقدي وتأمين',                     'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '411'],

            // 412 إيرادات المختبر والتحاليل الطبية
            ['code' => '412',    'name' => 'إيرادات المختبر والتحاليل الطبية',                             'type' => 'revenue',   'sub_type' => 'sub',    'parent' => '41'],

            // 413 مردودات ومسموحات الإيرادات (حساب عكسي)
            ['code' => '413',    'name' => 'مردودات ومسموحات الإيرادات',                                   'type' => 'revenue',   'sub_type' => 'sub',    'parent' => '41'],
            ['code' => '413101', 'name' => 'مردودات المختبر',                                              'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '413'],
            ['code' => '413102', 'name' => 'مسموحات المختبر',                                              'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '413'],
            ['code' => '413103', 'name' => 'مردودات العيادات والخدمات الطبية',                             'type' => 'revenue',   'sub_type' => 'detail', 'parent' => '413'],

            // ========== 5. المصروفات (Expenses) ==========
            ['code' => '5',      'name' => 'المصروفات',                                                    'type' => 'expense',   'sub_type' => 'main',   'parent' => null],

            // 51 المصروفات التشغيلية (تكلفة النشاط الطبي)
            ['code' => '51',     'name' => 'المصروفات التشغيلية (تكلفة النشاط الطبي)',                     'type' => 'expense',   'sub_type' => 'sub',    'parent' => '5'],

            // 511 تكاليف الكادر الطبي
            ['code' => '511',    'name' => 'تكاليف الكادر الطبي (الأطباء)',                                'type' => 'expense',   'sub_type' => 'sub',    'parent' => '51'],
            ['code' => '511101', 'name' => 'مصروف أتعاب الأطباء ',      'type' => 'expense',   'sub_type' => 'detail', 'parent' => '511'],

            // 512 مستهلكات ومواد طبية ومخبرية
            ['code' => '512',    'name' => 'مستهلكات ومواد طبية ومخبرية',                                 'type' => 'expense',   'sub_type' => 'sub',    'parent' => '51'],
            ['code' => '512101', 'name' => 'مصروف كواشف ومحاليل مخبرية (Reagents)',                       'type' => 'expense',   'sub_type' => 'detail', 'parent' => '512'],
            ['code' => '512102', 'name' => 'مصروف أدوات ومستهلكات طبية عامة (إبر، قطن، قفازات...)',      'type' => 'expense',   'sub_type' => 'detail', 'parent' => '512'],

            // 513 مصروفات التأمين المرفوضة
            ['code' => '513',    'name' => 'مصروفات التأمين المرفوضة',                                    'type' => 'expense',   'sub_type' => 'sub',    'parent' => '51'],
            ['code' => '513101', 'name' => 'مصروف مرفوضات وخصومات شركات التأمين',                         'type' => 'expense',   'sub_type' => 'detail', 'parent' => '513'],

            // 52 المصروفات العمومية والإدارية
            ['code' => '52',     'name' => 'المصروفات العمومية والإدارية',                                  'type' => 'expense',   'sub_type' => 'sub',    'parent' => '5'],

            // 521 الرواتب والأجور وملحقاتها
            ['code' => '521',    'name' => 'الرواتب والأجور وملحقاتها',                                    'type' => 'expense',   'sub_type' => 'sub',    'parent' => '52'],
            ['code' => '521101', 'name' => 'رواتب الموظفين الأساسية',                                      'type' => 'expense',   'sub_type' => 'detail', 'parent' => '521'],
            ['code' => '521102', 'name' => 'بدل السكن والانتقال للموظفين',                                 'type' => 'expense',   'sub_type' => 'detail', 'parent' => '521'],

            // 522 مصروفات تشغيلية عامة
            ['code' => '522',    'name' => 'مصروفات تشغيلية عامة',                                        'type' => 'expense',   'sub_type' => 'sub',    'parent' => '52'],
            ['code' => '522101', 'name' => 'مصروفات الإيجار (إيجار مبنى المركز)',                          'type' => 'expense',   'sub_type' => 'detail', 'parent' => '522'],
            ['code' => '522102', 'name' => 'مصروفات الكهرباء والمياه والإنترنت',                           'type' => 'expense',   'sub_type' => 'detail', 'parent' => '522'],
            ['code' => '522103', 'name' => 'مصروف صيانة الأجهزة الطبية والمخبرية',                        'type' => 'expense',   'sub_type' => 'detail', 'parent' => '522'],
            ['code' => '522104', 'name' => 'مصروفات البرمجيات والـ IT',                                    'type' => 'expense',   'sub_type' => 'detail', 'parent' => '522'],
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
