<?php
const ACCOUNT_TYPES = [
    'bank' => 'ব্যাংক অ্যাকাউন্ট',
    'cash' => 'নগদ',
    'mobile_banking' => 'মোবাইল ব্যাংকিং',
    'credit_card' => 'ক্রেডিট কার্ড',
    'other' => 'অন্যান্য',
];

const ACCOUNT_KINDS = [
    'current' => 'চলতি (তাৎক্ষণিক খরচ)',
    'savings' => 'সঞ্চয় (সাধারণ সঞ্চয়)',
    'long_term_savings' => 'দীর্ঘমেয়াদী সঞ্চয়',
];

function validate_account_input(array $post): array
{
    $data = [
        'name' => trim($post['name'] ?? ''),
        'type' => trim($post['type'] ?? ''),
        'account_type' => trim($post['account_type'] ?? ''),
        'initial_balance' => trim($post['initial_balance'] ?? ''),
    ];
    $errors = [];
    if ($data['name'] === '' || mb_strlen($data['name']) > 100) {
        $errors['name'] = 'অনুগ্রহ করে অ্যাকাউন্টের নাম লিখুন (সর্বোচ্চ ১০০ অক্ষর)।';
    }
    if (!array_key_exists($data['type'], ACCOUNT_TYPES)) {
        $errors['type'] = 'অনুগ্রহ করে অ্যাকাউন্টের প্রকার নির্বাচন করুন।';
    }
    if (!array_key_exists($data['account_type'], ACCOUNT_KINDS)) {
        $errors['account_type'] = 'অনুগ্রহ করে অ্যাকাউন্টের ধরন নির্বাচন করুন।';
    }
    if ($data['initial_balance'] === '' || !is_numeric($data['initial_balance'])) {
        $errors['initial_balance'] = 'অনুগ্রহ করে একটি বৈধ প্রাথমিক ব্যালেন্স লিখুন (০ হতে পারে)।';
    }
    return [$data, $errors];
}

function account_form_fields(array $data, array $errors): void
{
    ?>
    <div>
        <label>অ্যাকাউন্টের নাম:</label>
        <input type="text" name="name" maxlength="100" value="<?php echo e($data['name']); ?>">
        <span><?php echo e($errors['name'] ?? ''); ?></span>
    </div>
    <div>
        <label>প্রকার:</label>
        <select name="type">
            <option value="">প্রকার নির্বাচন করুন</option>
            <?php foreach (ACCOUNT_TYPES as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $data['type'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <span><?php echo e($errors['type'] ?? ''); ?></span>
    </div>
    <div>
        <label>অ্যাকাউন্টের ধরন:</label>
        <select name="account_type">
            <option value="">ধরন নির্বাচন করুন</option>
            <?php foreach (ACCOUNT_KINDS as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $data['account_type'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <span><?php echo e($errors['account_type'] ?? ''); ?></span>
    </div>
    <div>
        <label>প্রাথমিক ব্যালেন্স (BDT):</label>
        <input type="number" name="initial_balance" step="0.01" value="<?php echo e($data['initial_balance']); ?>">
        <span><?php echo e($errors['initial_balance'] ?? ''); ?></span>
    </div>
    <?php
}
