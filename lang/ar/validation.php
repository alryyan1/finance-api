<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'accepted' => 'يجب قبول حقل :attribute.',
    'accepted_if' => 'يجب قبول حقل :attribute عندما يكون :other هو :value.',
    'active_url' => 'حقل :attribute ليس رابطًا صحيحًا.',
    'after' => 'يجب أن يكون حقل :attribute تاريخًا بعد :date.',
    'after_or_equal' => 'يجب أن يكون حقل :attribute تاريخًا بعد أو يساوي :date.',
    'alpha' => 'يجب أن يحتوي حقل :attribute على أحرف فقط.',
    'alpha_dash' => 'يجب أن يحتوي حقل :attribute على أحرف وأرقام وشرطات وشرطات سفلية فقط.',
    'alpha_num' => 'يجب أن يحتوي حقل :attribute على أحرف وأرقام فقط.',
    'any_of' => 'حقل :attribute غير صالح.',
    'array' => 'يجب أن يكون حقل :attribute مصفوفة.',
    'ascii' => 'يجب أن يحتوي حقل :attribute على رموز وأحرف أحادية البايت فقط.',
    'before' => 'يجب أن يكون حقل :attribute تاريخًا قبل :date.',
    'before_or_equal' => 'يجب أن يكون حقل :attribute تاريخًا قبل أو يساوي :date.',
    'between' => [
        'array' => 'يجب أن يحتوي حقل :attribute على عدد عناصر بين :min و :max.',
        'file' => 'يجب أن يكون حجم حقل :attribute بين :min و :max كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute بين :min و :max.',
        'string' => 'يجب أن يكون طول حقل :attribute بين :min و :max حرفًا.',
    ],
    'boolean' => 'يجب أن تكون قيمة حقل :attribute إما صحيحة أو خاطئة.',
    'can' => 'حقل :attribute يحتوي على قيمة غير مصرح بها.',
    'confirmed' => 'تأكيد حقل :attribute غير مطابق.',
    'contains' => 'حقل :attribute يفتقد قيمة مطلوبة.',
    'current_password' => 'كلمة المرور غير صحيحة.',
    'date' => 'حقل :attribute ليس تاريخًا صحيحًا.',
    'date_equals' => 'يجب أن يكون حقل :attribute تاريخًا مساويًا لـ :date.',
    'date_format' => 'لا يطابق حقل :attribute الصيغة :format.',
    'decimal' => 'يجب أن يحتوي حقل :attribute على :decimal رقمًا عشريًا.',
    'declined' => 'يجب رفض حقل :attribute.',
    'declined_if' => 'يجب رفض حقل :attribute عندما يكون :other هو :value.',
    'different' => 'يجب أن يكون حقل :attribute و :other مختلفين.',
    'digits' => 'يجب أن يتكون حقل :attribute من :digits أرقام.',
    'digits_between' => 'يجب أن يتكون حقل :attribute من عدد أرقام بين :min و :max.',
    'dimensions' => 'أبعاد صورة حقل :attribute غير صحيحة.',
    'distinct' => 'حقل :attribute يحتوي على قيمة مكررة.',
    'doesnt_contain' => 'يجب ألا يحتوي حقل :attribute على أي من القيم التالية: :values.',
    'doesnt_end_with' => 'يجب ألا ينتهي حقل :attribute بأي من القيم التالية: :values.',
    'doesnt_start_with' => 'يجب ألا يبدأ حقل :attribute بأي من القيم التالية: :values.',
    'email' => 'يجب أن يكون حقل :attribute بريدًا إلكترونيًا صحيحًا.',
    'encoding' => 'يجب أن يكون ترميز حقل :attribute هو :encoding.',
    'ends_with' => 'يجب أن ينتهي حقل :attribute بأحد القيم التالية: :values.',
    'enum' => ':attribute المحدد غير صحيح.',
    'exists' => ':attribute المحدد غير موجود.',
    'extensions' => 'يجب أن يكون حقل :attribute من نوع الملفات: :values.',
    'file' => 'يجب أن يكون حقل :attribute ملفًا.',
    'filled' => 'يجب ألا يكون حقل :attribute فارغًا.',
    'gt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون حجم حقل :attribute أكبر من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أكبر من :value.',
        'string' => 'يجب أن يكون طول حقل :attribute أكبر من :value حرفًا.',
    ],
    'gte' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :value عنصر أو أكثر.',
        'file' => 'يجب أن يكون حجم حقل :attribute أكبر من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أكبر من أو تساوي :value.',
        'string' => 'يجب أن يكون طول حقل :attribute أكبر من أو يساوي :value حرفًا.',
    ],
    'hex_color' => 'يجب أن يكون حقل :attribute لونًا سادس عشريًا صحيحًا.',
    'image' => 'يجب أن يكون حقل :attribute صورة.',
    'in' => ':attribute المحدد غير صحيح.',
    'in_array' => 'حقل :attribute غير موجود ضمن :other.',
    'in_array_keys' => 'يجب أن يحتوي حقل :attribute على واحد على الأقل من المفاتيح التالية: :values.',
    'integer' => 'يجب أن يكون حقل :attribute عددًا صحيحًا.',
    'ip' => 'يجب أن يكون حقل :attribute عنوان IP صحيحًا.',
    'ipv4' => 'يجب أن يكون حقل :attribute عنوان IPv4 صحيحًا.',
    'ipv6' => 'يجب أن يكون حقل :attribute عنوان IPv6 صحيحًا.',
    'json' => 'يجب أن يكون حقل :attribute نص JSON صحيحًا.',
    'list' => 'يجب أن يكون حقل :attribute قائمة.',
    'lowercase' => 'يجب أن يكون حقل :attribute بحروف صغيرة.',
    'lt' => [
        'array' => 'يجب أن يحتوي حقل :attribute على أقل من :value عنصر.',
        'file' => 'يجب أن يكون حجم حقل :attribute أقل من :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أقل من :value.',
        'string' => 'يجب أن يكون طول حقل :attribute أقل من :value حرفًا.',
    ],
    'lte' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :value عنصر.',
        'file' => 'يجب أن يكون حجم حقل :attribute أقل من أو يساوي :value كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute أقل من أو تساوي :value.',
        'string' => 'يجب أن يكون طول حقل :attribute أقل من أو يساوي :value حرفًا.',
    ],
    'mac_address' => 'يجب أن يكون حقل :attribute عنوان MAC صحيحًا.',
    'max' => [
        'array' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max عنصر.',
        'file' => 'يجب ألا يتجاوز حجم حقل :attribute :max كيلوبايت.',
        'numeric' => 'يجب ألا تتجاوز قيمة حقل :attribute :max.',
        'string' => 'يجب ألا يتجاوز طول حقل :attribute :max حرفًا.',
    ],
    'max_digits' => 'يجب ألا يحتوي حقل :attribute على أكثر من :max رقم.',
    'mimes' => 'يجب أن يكون حقل :attribute ملفًا من نوع: :values.',
    'mimetypes' => 'يجب أن يكون حقل :attribute ملفًا من نوع: :values.',
    'min' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :min عنصر على الأقل.',
        'file' => 'يجب ألا يقل حجم حقل :attribute عن :min كيلوبايت.',
        'numeric' => 'يجب ألا تقل قيمة حقل :attribute عن :min.',
        'string' => 'يجب ألا يقل طول حقل :attribute عن :min حرفًا.',
    ],
    'min_digits' => 'يجب ألا يقل حقل :attribute عن :min رقم.',
    'missing' => 'يجب أن يكون حقل :attribute مفقودًا.',
    'missing_if' => 'يجب أن يكون حقل :attribute مفقودًا عندما يكون :other هو :value.',
    'missing_unless' => 'يجب أن يكون حقل :attribute مفقودًا إلا إذا كان :other هو :value.',
    'missing_with' => 'يجب أن يكون حقل :attribute مفقودًا عند وجود :values.',
    'missing_with_all' => 'يجب أن يكون حقل :attribute مفقودًا عند وجود :values.',
    'multiple_of' => 'يجب أن تكون قيمة حقل :attribute من مضاعفات :value.',
    'not_in' => ':attribute المحدد غير صحيح.',
    'not_regex' => 'صيغة حقل :attribute غير صحيحة.',
    'numeric' => 'يجب أن يكون حقل :attribute رقمًا.',
    'password' => [
        'letters' => 'يجب أن يحتوي حقل :attribute على حرف واحد على الأقل.',
        'mixed' => 'يجب أن يحتوي حقل :attribute على حرف كبير وحرف صغير على الأقل.',
        'numbers' => 'يجب أن يحتوي حقل :attribute على رقم واحد على الأقل.',
        'symbols' => 'يجب أن يحتوي حقل :attribute على رمز واحد على الأقل.',
        'uncompromised' => 'تم العثور على :attribute ضمن تسريبات بيانات سابقة. يرجى اختيار :attribute مختلف.',
    ],
    'present' => 'يجب أن يكون حقل :attribute موجودًا.',
    'present_if' => 'يجب أن يكون حقل :attribute موجودًا عندما يكون :other هو :value.',
    'present_unless' => 'يجب أن يكون حقل :attribute موجودًا إلا إذا كان :other هو :value.',
    'present_with' => 'يجب أن يكون حقل :attribute موجودًا عند وجود :values.',
    'present_with_all' => 'يجب أن يكون حقل :attribute موجودًا عند وجود :values.',
    'prohibited' => 'حقل :attribute محظور.',
    'prohibited_if' => 'حقل :attribute محظور عندما يكون :other هو :value.',
    'prohibited_if_accepted' => 'حقل :attribute محظور عندما يكون :other مقبولًا.',
    'prohibited_if_declined' => 'حقل :attribute محظور عندما يكون :other مرفوضًا.',
    'prohibited_unless' => 'حقل :attribute محظور إلا إذا كان :other ضمن :values.',
    'prohibits' => 'حقل :attribute يمنع وجود :other.',
    'regex' => 'صيغة حقل :attribute غير صحيحة.',
    'required' => 'حقل :attribute مطلوب.',
    'required_array_keys' => 'يجب أن يحتوي حقل :attribute على مدخلات لـ: :values.',
    'required_if' => 'حقل :attribute مطلوب عندما يكون :other هو :value.',
    'required_if_accepted' => 'حقل :attribute مطلوب عندما يكون :other مقبولًا.',
    'required_if_declined' => 'حقل :attribute مطلوب عندما يكون :other مرفوضًا.',
    'required_unless' => 'حقل :attribute مطلوب إلا إذا كان :other ضمن :values.',
    'required_with' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_with_all' => 'حقل :attribute مطلوب عند وجود :values.',
    'required_without' => 'حقل :attribute مطلوب عند عدم وجود :values.',
    'required_without_all' => 'حقل :attribute مطلوب عند عدم وجود أي من :values.',
    'same' => 'يجب أن يتطابق حقل :attribute مع :other.',
    'size' => [
        'array' => 'يجب أن يحتوي حقل :attribute على :size عنصر.',
        'file' => 'يجب أن يكون حجم حقل :attribute :size كيلوبايت.',
        'numeric' => 'يجب أن تكون قيمة حقل :attribute :size.',
        'string' => 'يجب أن يكون طول حقل :attribute :size حرفًا.',
    ],
    'starts_with' => 'يجب أن يبدأ حقل :attribute بأحد القيم التالية: :values.',
    'string' => 'يجب أن يكون حقل :attribute نصًا.',
    'timezone' => 'يجب أن يكون حقل :attribute منطقة زمنية صحيحة.',
    'unique' => 'قيمة حقل :attribute مستخدمة من قبل.',
    'uploaded' => 'فشل رفع حقل :attribute.',
    'uppercase' => 'يجب أن يكون حقل :attribute بحروف كبيرة.',
    'url' => 'يجب أن يكون حقل :attribute رابطًا صحيحًا.',
    'ulid' => 'يجب أن يكون حقل :attribute قيمة ULID صحيحة.',
    'uuid' => 'يجب أن يكون حقل :attribute قيمة UUID صحيحة.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'الاسم',
        'username' => 'اسم المستخدم',
        'email' => 'البريد الإلكتروني',
        'password' => 'كلمة المرور',
        'roles' => 'الصلاحيات',
    ],

];
