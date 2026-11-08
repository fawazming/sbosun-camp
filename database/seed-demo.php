<?php
declare(strict_types=1);

/**
 * Optional demo data for local development.
 *
 * Usage: php database/seed-demo.php
 * Safe to re-run: records are keyed by registration ID.
 */

require __DIR__ . '/../app/bootstrap.php';

if (!db_ready()) {
    fwrite(STDERR, "Install the portal first (php install.php).\n");
    exit(1);
}

$demo = [
    ['Alao', 'Ibrahim Adebayo', 'MALE', '21 - 30', 'Undergraduate', 'Osogbo', '08123456789', 'ibrahim@example.com', 'None', 'Mrs Alao', '09053444178', 'FULL'],
    ['Okafor', 'Ada', 'FEMALE', '21 - 30', 'Graduate', 'Ile-Ife', '08098765432', 'ada@example.com', 'Asthma', 'Mr Okafor', '08012345678', 'INSTALLMENT'],
    ['Bello', 'Fatimah', 'FEMALE', '10 - 20', 'JSS 1-3', 'Ilesha', '07011112222', 'fatimah@example.com', 'None', 'Mrs Bello', '07033334444', 'FULL'],
    ['Yusuf', 'Abdulrahman', 'MALE', '31 - 40', 'Others', 'Ede', '08055556666', 'yusuf@example.com', 'Ulcer', 'Mrs Yusuf', '08077778888', 'FULL'],
];

$created = 0;
foreach ($demo as $index => [$surname, $other, $gender, $age, $category, $zone, $phone, $email, $allergies, $nokName, $nokPhone, $plan]) {
    $rid = 'SB-26-DEMO' . ($index + 1);
    if (db_one('SELECT id FROM participants WHERE registration_id = ?', [$rid])) {
        continue;
    }
    $class = class_for_category($category);
    $pid = db_insert('participants', [
        'registration_id'   => $rid,
        'token'             => random_token(24),
        'surname'           => $surname,
        'other_name'        => $other,
        'gender'            => $gender,
        'age_group'         => $age,
        'category'          => $category,
        'class_id'          => $class ? (int) $class['id'] : null,
        'zone'              => $zone,
        'phone'             => $phone,
        'email'             => $email,
        'allergies'         => $allergies,
        'nok_name'          => $nokName,
        'nok_phone'         => $nokPhone,
        'profile_completed' => 1,
        'payment_status'    => 'NOT_PAID',
        'attendance_status' => 'NOT_ARRIVED',
        'amount_owed'       => current_fee(),
        'amount_paid'       => 0,
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);
    ensure_attendance($pid);

    $fee = fee_for('FULL');
    $paymentId = db_insert('payments', [
        'participant_id' => $pid,
        'reference'      => 'SBHIC-DEMO' . strtoupper(bin2hex(random_bytes(4))),
        'amount'         => $plan === 'FULL' ? $fee['amount'] : fee_for('INSTALLMENT', 1)['amount'],
        'payment_type'   => $plan,
        'installment_number' => $plan === 'FULL' ? null : 1,
        'plan_rate'      => $fee['plan_rate'],
        'provider'       => 'manual_transfer',
        'status'         => $plan === 'FULL' ? 'VERIFIED' : 'PENDING',
        'paid_at'        => $plan === 'FULL' ? now() : null,
        'verified_at'    => $plan === 'FULL' ? now() : null,
        'created_at'     => now(),
    ]);
    if ($plan === 'FULL') {
        db_insert('payment_events', [
            'provider_reference' => 'DEMO-' . $paymentId,
            'payment_id'         => $paymentId,
            'amount'             => $fee['amount'],
            'outcome'            => 'MATCHED',
            'created_at'         => now(),
        ]);
    }
    refresh_participant_payment($pid);
    $created++;
}

echo "Demo data ready. {$created} new participant(s) created.\n";
echo "Open the portal in your browser (for XAMPP: http://localhost/sbosun-camp/).\n";
