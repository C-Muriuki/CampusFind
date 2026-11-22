<?php
declare(strict_types=1);

/**
 * CampusFind — demo data seeder.
 *
 * Populates the database with a realistic dataset so the full system flow
 * can be demonstrated and tested end to end:
 *   - 12 student accounts + 1 admin (idempotent; admin updated in place)
 *   - 22 lost/found reports across categories, with images and varied statuses
 *   - claims (pending / approved / rejected) linking claimants to found items
 *   - notifications for claimants and report owners
 *   - contact-us submissions of varied status
 *   - activity-log entries
 *
 * Safe to re-run: uses INSERT ... ON DUPLICATE KEY UPDATE and deletes only
 * rows it owns (matching @zetech.ac.ke demo emails / demo images) first.
 *
 * Usage (CLI, with mysql driver flags):
 *   php -d extension=.../mysqlnd.so -d extension=.../pdo_mysql.so \
 *       database/seed_demo.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
if (!$pdo) {
    fwrite(STDERR, "Database connection failed.\n");
    exit(1);
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

/* --------------------------------------------------------------------------
 * 0. Restore demo images from the persistent source directory.
 *    Test runs wipe uploads/reports/, so we copy the canonical demo images
 *    back into place before referencing them below.
 * ------------------------------------------------------------------------ */
$demoSrc = __DIR__ . '/demo-images';
$uploadDir = UPLOAD_PATH . '/reports';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0750, true);
}
$restored = 0;
foreach (glob($demoSrc . '/*.jpg') as $src) {
    $dest = $uploadDir . '/' . basename($src);
    if (!file_exists($dest)) {
        copy($src, $dest);
        $restored++;
    }
}
echo "Restored $restored demo images.\n";

/* --------------------------------------------------------------------------
 * 1. Students
 * ------------------------------------------------------------------------ */
$students = [
    ['Amina Wanjiru',    'amina.wanjiru@zetech.ac.ke',    'BCS/2101/2024', '0712 345 001'],
    ['Brian Otieno',     'brian.otieno@zetech.ac.ke',     'BCS/2102/2024', '0712 345 002'],
    ['Cynthia Njeri',    'cynthia.njeri@zetech.ac.ke',    'BBA/2103/2023', '0712 345 003'],
    ['David Kamau',      'david.kamau@zetech.ac.ke',      'BCS/2104/2024', '0712 345 004'],
    ['Esther Mutua',     'esther.mutua@zetech.ac.ke',     'BIS/2105/2023', '0712 345 005'],
    ['Felix Mwangi',     'felix.mwangi@zetech.ac.ke',     'BCS/2106/2024', '0712 345 006'],
    ['Grace Achieng',    'grace.achieng@zetech.ac.ke',    'BBA/2107/2023', '0712 345 007'],
    ['Henry Kiprop',     'henry.kiprop@zetech.ac.ke',     'BCS/2108/2024', '0712 345 008'],
    ['Irene Chebet',     'irene.chebet@zetech.ac.ke',     'BIS/2109/2023', '0712 345 009'],
    ['Kevin Omondi',     'kevin.omondi@zetech.ac.ke',     'BCS/2110/2024', '0712 345 010'],
    ['Lucy Wambui',      'lucy.wambui@zetech.ac.ke',      'BBA/2111/2023', '0712 345 011'],
    ['Martin Njoroge',   'martin.njoroge@zetech.ac.ke',   'BCS/2112/2024', '0712 345 012'],
];

$defaultHash = password_hash('DemoPass123!', PASSWORD_DEFAULT);

$insUser = $pdo->prepare(
    'INSERT INTO users (full_name, email, student_number, phone, password_hash, role, account_status, created_at)
     VALUES (?, ?, ?, ?, ?, "student", "active", NOW() - INTERVAL FLOOR(RAND()*180) DAY)
     ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        student_number = VALUES(student_number),
        phone = VALUES(phone),
        password_hash = VALUES(password_hash),
        account_status = "active"'
);

$userIds = [];
foreach ($students as [$name, $email, $num, $phone]) {
    $insUser->execute([$name, $email, $num, $phone, $defaultHash]);
    $userIds[$email] = (int) $pdo->lastInsertId();
    // lastInsertId is 0 on UPDATE; fetch real id.
    if ($userIds[$email] === 0) {
        $q = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $q->execute([$email]);
        $userIds[$email] = (int) $q->fetchColumn();
    }
}

// Ensure admin exists.
$insAdmin = $pdo->prepare(
    'INSERT INTO users (full_name, email, student_number, phone, password_hash, role, account_status, created_at)
     VALUES (?, ?, ?, ?, ?, "admin", "active", NOW() - INTERVAL 300 DAY)
     ON DUPLICATE KEY UPDATE
        full_name = VALUES(full_name),
        password_hash = VALUES(password_hash),
        role = "admin",
        account_status = "active"'
);
$insAdmin->execute([
    'CampusFind Admin',
    'admin@zetech.ac.ke',
    'ADMIN001',
    '0719 034 500',
    password_hash('Admin@12345', PASSWORD_DEFAULT),
]);
$adminQ = $pdo->prepare('SELECT id FROM users WHERE email = ?');
$adminQ->execute(['admin@zetech.ac.ke']);
$adminId = (int) $adminQ->fetchColumn();

echo "Seeded " . count($students) . " students + admin.\n";

/* --------------------------------------------------------------------------
 * 2. Reports (clear demo-owned rows first so re-runs are clean)
 * ------------------------------------------------------------------------ */
// Remove previous demo reports by their generated image filenames.
$demoImages = glob(UPLOAD_PATH . '/reports/*.jpg');
$pdo->exec("DELETE FROM claims WHERE report_id IN (SELECT id FROM reports WHERE image_path LIKE '%.jpg')");
$pdo->exec("DELETE FROM notifications WHERE related_report_id IN (SELECT id FROM reports WHERE image_path LIKE '%.jpg')");
$pdo->exec("DELETE FROM reports WHERE image_path LIKE '%.jpg'");

$catIds = [];
// Ensure the base categories exist. schema.sql ships DDL only, so a fresh
// deploy (schema -> seed_demo) would otherwise have no categories and every
// report would fail to map its category. INSERT IGNORE keeps re-runs clean.
$baseCategories = [
    'Electronics'          => 'Phones, laptops, chargers and electronic devices',
    'Identification'       => 'Student IDs, national IDs and access cards',
    'Bags'                 => 'Backpacks, handbags and other bags',
    'Books and Stationery' => 'Books, notebooks, pens and stationery',
    'Clothing'             => 'Jackets, sweaters, shoes and other clothing',
    'Accessories'          => 'Watches, jewellery, glasses and accessories',
    'Keys'                 => 'Keys and key holders',
    'Documents'            => 'Certificates, papers and personal documents',
    'Sports Equipment'     => 'Sports gear and fitness equipment',
    'Other'                => 'Items that do not fit other categories',
];
$insCat = $pdo->prepare('INSERT IGNORE INTO categories (name, description) VALUES (?, ?)');
foreach ($baseCategories as $name => $description) {
    $insCat->execute([$name, $description]);
}

foreach ($pdo->query('SELECT id, name FROM categories') as $c) {
    $catIds[$c['name']] = (int) $c['id'];
}

// [image, item_name, type, category, location, incident_date, description, status, owner_email]
$reports = [
    ['iphone13_pro.jpg',   'iPhone 13 Pro',            'found', 'Electronics',          'Main Library, 2nd Floor',      '2026-10-02', 'Found a black iPhone 13 Pro with a cracked screen protector on a study desk near the window. Locked with a passcode. Please describe the lock screen to claim.', 'approved',  'brian.otieno@zetech.ac.ke'],
    ['laptop_hp.jpg',      'HP Laptop Bag',            'lost',  'Bags',                 'Lecture Hall B, Ruiru Campus', '2026-10-05', 'Lost my grey HP laptop bag containing a laptop and charger after the afternoon DBMS lecture. It has a small Zetech sticker on the side.', 'approved',  'amina.wanjiru@zetech.ac.ke'],
    ['student_id.jpg',     'Student ID Card',          'found', 'Identification',       'Cafeteria entrance',           '2026-10-07', 'Picked up a student ID card near the cafeteria. Name partially visible. Handed to campus security if not claimed by Friday.', 'pending',   'cynthia.njeri@zetech.ac.ke'],
    ['calculator.jpg',     'Scientific Calculator',    'lost',  'Electronics',          'Room 204, Engineering Block',  '2026-09-28', 'Left my Casio scientific calculator in Room 204 after the exam. Name "D. Kamau" written on the back in marker.', 'approved',  'david.kamau@zetech.ac.ke'],
    ['water_bottle.jpg',   'Blue Water Bottle',        'found', 'Other',                'Sports field',                 '2026-10-10', 'Found a blue 1-litre water bottle by the sports field bench. Still sealed cap. No name on it.', 'resolved',  'esther.mutua@zetech.ac.ke'],
    ['keys_bunch.jpg',     'Bunch of Keys',            'lost',  'Keys',                 'Parking lot, near Gate 2',     '2026-10-12', 'Lost a bunch of 4 keys with a red keyring and a small bottle-opener charm somewhere between the parking lot and Gate 2.', 'approved',  'felix.mwangi@zetech.ac.ke'],
    ['wallet.jpg',         'Brown Leather Wallet',     'found', 'Accessories',          'Bus stop outside main gate',   '2026-10-08', 'Found a brown leather wallet at the bus stop. Contains an ATM card and some cash. Please identify the bank to claim.', 'pending',   'grace.achieng@zetech.ac.ke'],
    ['textbook.jpg',       'Calculus Textbook',        'lost',  'Books and Stationery', 'Library reading area',         '2026-09-25', 'Missing my Calculus (Stewart) textbook with my name and notes inside the front cover. Needed for exams.', 'approved',  'henry.kiprop@zetech.ac.ke'],
    ['headphones.jpg',     'Wireless Headphones',      'found', 'Electronics',          'Computer Lab 3',               '2026-10-14', 'Found a pair of black wireless over-ear headphones left in Computer Lab 3. Tell me the brand to claim.', 'pending',   'irene.chebet@zetech.ac.ke'],
    ['jacket.jpg',         'Navy Jacket',              'lost',  'Clothing',             'Lecture Hall A',               '2026-10-11', 'Left a navy blue hooded jacket on a seat in Lecture Hall A after the morning class. Size L.', 'rejected',  'kevin.omondi@zetech.ac.ke'],
    ['charger.jpg',        'USB-C Charger',            'found', 'Electronics',          'Study room, Admin Block',      '2026-10-15', 'Found a 65W USB-C laptop charger in a study room. White brick with a braided cable.', 'approved',  'lucy.wambui@zetech.ac.ke'],
    ['spectacles.jpg',     'Prescription Glasses',     'lost',  'Accessories',          'Path between Library and Hall', '2026-10-09', 'Lost my prescription glasses (thin black frames) somewhere on the path between the library and the main hall.', 'approved',  'martin.njoroge@zetech.ac.ke'],
    ['notebook.jpg',       'Spiral Notebook',          'found', 'Books and Stationery', 'Lecture Hall C',               '2026-10-13', 'Found a green spiral notebook with handwritten notes on the last lecture. Claim by describing the subject.', 'approved',  'amina.wanjiru@zetech.ac.ke'],
    ['sports_bag.jpg',     'Gym Bag',                  'lost',  'Bags',                 'Changing rooms, Sports Complex', '2026-10-06', 'Lost a black gym bag with a change of clothes and running shoes in the changing rooms.', 'pending',   'brian.otieno@zetech.ac.ke'],
    ['access_card.jpg',    'Library Access Card',      'found', 'Identification',       'Library turnstile',            '2026-10-16', 'Found a library access card at the turnstile. I have handed it to the library desk for safekeeping.', 'resolved',  'cynthia.njeri@zetech.ac.ke'],
    ['umbrella.jpg',       'Black Umbrella',           'lost',  'Other',                'Main entrance lobby',          '2026-10-04', 'Left a large black umbrella in the main entrance lobby during the heavy rains. Wooden handle.', 'approved',  'david.kamau@zetech.ac.ke'],
    ['certificate.jpg',    'Academic Certificate',     'found', 'Documents',            'Notice board, Admin Block',    '2026-09-30', 'Found an academic certificate in a clear plastic sleeve near the notice board. Name is a "W..." — contact to verify.', 'pending',   'esther.mutua@zetech.ac.ke'],
    ['watch.jpg',          'Digital Watch',            'lost',  'Accessories',          'Swimming pool area',           '2026-10-17', 'Lost a black digital sports watch near the swimming pool. Strap has a small scratch on the left side.', 'approved',  'felix.mwangi@zetech.ac.ke'],
    ['football.jpg',       'Football',                 'found', 'Sports Equipment',     'Football pitch',               '2026-10-18', 'Found a size 5 football left on the pitch after the evening game. Slightly deflated.', 'pending',   'grace.achieng@zetech.ac.ke'],
    ['pen_set.jpg',        'Set of Pens',              'lost',  'Books and Stationery', 'Design studio',                '2026-10-01', 'Lost a set of fineliner pens (Sakura) in the design studio after class. They were in a clear pouch.', 'archived',  'henry.kiprop@zetech.ac.ke'],
    ['powerbank.jpg',      'Power Bank',               'found', 'Electronics',          'Cafeteria, table 12',          '2026-10-19', 'Found a 20000mAh power bank left on cafeteria table 12. White with two USB ports.', 'pending',   'irene.chebet@zetech.ac.ke'],
    ['hoodie.jpg',         'Grey Hoodie',              'lost',  'Clothing',             'Gym entrance',                 '2026-10-20', 'Lost my grey hoodie (size M) at the gym entrance after the fitness class on Monday.', 'approved',  'kevin.omondi@zetech.ac.ke'],
];

$insReport = $pdo->prepare(
    'INSERT INTO reports
        (user_id, category_id, item_name, item_type, description, location, incident_date,
         image_path, contact_phone, contact_email, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND()*90) DAY)'
);

$reportIds = [];
$foundByImage = [];

foreach ($reports as [$img, $name, $type, $cat, $loc, $date, $desc, $status, $owner]) {
    $uid = $userIds[$owner];
    $contactPhone = '07' . str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    $insReport->execute([
        $uid,
        $catIds[$cat],
        $name,
        $type,
        $desc,
        $loc,
        $date,
        'uploads/reports/' . $img,
        $contactPhone,
        $owner,
        $status,
    ]);
    $rid = (int) $pdo->lastInsertId();
    $reportIds[] = $rid;
    if ($type === 'found') {
        $foundByImage[$img] = $rid;
    }
}

echo "Seeded " . count($reports) . " reports.\n";

/* --------------------------------------------------------------------------
 * 3. Claims (claimants on found items; some approved -> resolved, some pending, some rejected)
 * ------------------------------------------------------------------------ */
// Map: found image -> [claimant_email, proof, claim_status, reviewer]
$claims = [
    ['iphone13_pro.jpg', 'martin.njoroge@zetech.ac.ke', 'My lock screen shows a photo of a dog and the time is set to 07:30. I can unlock it for you at the security desk.', 'pending', null],
    ['student_id.jpg',   'lucy.wambui@zetech.ac.ke',    'The card belongs to me — my name is Lucy Wambui, student number BBA/2111/2023.', 'pending', null],
    ['wallet.jpg',       'grace.achieng@zetech.ac.ke',  'It is my wallet. The bank is Equity and the card ends in 4421.', 'pending', null],
    ['headphones.jpg',   'brian.otieno@zetech.ac.ke',   'They are mine — Sony WH-1000XM4, black. I left them in Lab 3 on Tuesday.', 'pending', null],
    ['charger.jpg',      'amina.wanjiru@zetech.ac.ke',  'That is my 65W Anker charger. The cable has a small velcro tie.', 'approved', null],
    ['water_bottle.jpg', 'david.kamau@zetech.ac.ke',    'I think this is my bottle — blue, 1 litre, with a carry loop.', 'approved', null],
    ['access_card.jpg',  'cynthia.njeri@zetech.ac.ke',  'My library access card. I can confirm my student number.', 'approved', null],
    ['watch.jpg',        'kevin.omondi@zetech.ac.ke',   'That is not my watch, mine has a metal strap not rubber.', 'rejected', null],
];

$insClaim = $pdo->prepare(
    'INSERT INTO claims (report_id, claimant_id, proof, status, reviewed_by, reviewed_at, admin_notes, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND()*40) DAY)'
);

$claimCount = 0;
foreach ($claims as [$img, $claimant, $proof, $status, $reviewer]) {
    if (!isset($foundByImage[$img])) {
        continue;
    }
    $rid = $foundByImage[$img];
    $cid = $userIds[$claimant];

    $reviewedAt = ($status !== 'pending') ? 'NOW() - INTERVAL FLOOR(RAND()*10) DAY' : null;
    $notes = $status === 'approved' ? 'Verified — item released to owner.'
           : ($status === 'rejected' ? 'Proof did not match.' : null);

    // Insert with conditional reviewed_at via a small SQL switch.
    if ($reviewedAt !== null) {
        $pdo->prepare(
            'INSERT INTO claims (report_id, claimant_id, proof, status, reviewed_by, reviewed_at, admin_notes, created_at)
             VALUES (?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND()*10) DAY, ?, NOW() - INTERVAL FLOOR(RAND()*40) DAY)'
        )->execute([$rid, $cid, $proof, $status, $adminId, $notes]);
    } else {
        $insClaim->execute([$rid, $cid, $proof, $status, null, null, null]);
    }
    $claimCount++;

    // Notify claimant of the outcome.
    if ($status === 'approved') {
        createNotification(
            $cid,
            'Claim approved',
            'Your claim for the reported item was approved. Contact campus security to collect it.',
            'claim_approved',
            $rid
        );
        // Mark the report resolved.
        $pdo->prepare("UPDATE reports SET status = 'resolved', resolved_at = NOW() WHERE id = ?")->execute([$rid]);
    } elseif ($status === 'rejected') {
        createNotification(
            $cid,
            'Claim rejected',
            'Your claim was not approved because the proof did not match the item.',
            'claim_rejected',
            $rid
        );
    } else {
        createNotification(
            $cid,
            'Claim submitted',
            'Your claim has been submitted and is awaiting admin review.',
            'claim_pending',
            $rid
        );
    }
}

echo "Seeded " . $claimCount . " claims.\n";

/* --------------------------------------------------------------------------
 * 4. Contact submissions (varied subjects + statuses)
 * ------------------------------------------------------------------------ */
$pdo->exec("DELETE FROM contact_submissions WHERE email LIKE '%@zetech.ac.ke'");

$subjects = [
    'general'   => 'General inquiry',
    'lost_item' => 'I lost an item',
    'found_item' => 'I found an item',
    'claim_help' => 'Help with a claim',
    'account'   => 'Account or login issue',
    'feedback'  => 'Feedback or suggestion',
    'other'     => 'Other',
];

$contacts = [
    ['amina.wanjiru@zetech.ac.ke',  'Amina Wanjiru',  'lost_item',  'I lost my laptop bag near Lecture Hall B yesterday. How do I track my report?', 'new', null],
    ['brian.otieno@zetech.ac.ke',   'Brian Otieno',   'claim_help', 'I submitted a claim for the iPhone but have not heard back. Can you check?', 'in_progress', 'Your claim is with the review team. We will update you shortly.'],
    ['cynthia.njeri@zetech.ac.ke',  'Cynthia Njeri',  'account',    'I cannot reset my password — the reset email never arrives.', 'resolved', 'We have resent your reset link. Please check spam too.'],
    ['david.kamau@zetech.ac.ke',    'David Kamau',    'feedback',   'The new dark mode looks great. Please add a way to sort items by date.', 'new', null],
    ['esther.mutua@zetech.ac.ke',   'Esther Mutua',   'general',    'Are there plans to extend CampusFind to the Mang\'u campus?', 'resolved', 'We are evaluating multi-campus support for next semester.'],
    ['felix.mwangi@zetech.ac.ke',   'Felix Mwangi',   'found_item', 'I found a bunch of keys near Gate 2 and reported them. What next?', 'new', null],
    ['grace.achieng@zetech.ac.ke',  'Grace Achieng',  'other',      'Can you clarify how long items are kept before disposal?', 'in_progress', 'We are confirming the retention period with campus security.'],
];

$insContact = $pdo->prepare(
    'INSERT INTO contact_submissions (user_id, name, email, subject, message, status, admin_response, responded_by, responded_at, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND()*30) DAY)'
);

foreach ($contacts as [$email, $name, $subjKey, $msg, $status, $resp]) {
    $uid = $userIds[$email] ?? null;
    $subjectLabel = $subjects[$subjKey];
    $respondedBy = ($status !== 'new') ? $adminId : null;
    $respondedAt = ($status !== 'new') ? 'NOW() - INTERVAL FLOOR(RAND()*5) DAY' : null;

    if ($respondedAt !== null) {
        $pdo->prepare(
            'INSERT INTO contact_submissions (user_id, name, email, subject, message, status, admin_response, responded_by, responded_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW() - INTERVAL FLOOR(RAND()*5) DAY, NOW() - INTERVAL FLOOR(RAND()*30) DAY)'
        )->execute([$uid, $name, $email, $subjectLabel, $msg, $status, $resp, $respondedBy]);
    } else {
        $insContact->execute([$uid, $name, $email, $subjectLabel, $msg, $status, null, null, null]);
    }
}

echo "Seeded " . count($contacts) . " contact submissions.\n";

/* --------------------------------------------------------------------------
 * 5. Activity logs
 * ------------------------------------------------------------------------ */
$pdo->exec("DELETE FROM activity_logs WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@zetech.ac.ke')");

$log = $pdo->prepare(
    'INSERT INTO activity_logs (user_id, action, description, created_at)
     VALUES (?, ?, ?, NOW() - INTERVAL FLOOR(RAND()*20) DAY)'
);
foreach ($students as [$name, $email, $num, $phone]) {
    $log->execute([$userIds[$email], 'seed_login', 'Demo account created for ' . $name]);
}
$log->execute([$adminId, 'seed_moderation', 'Approved and rejected demo claims']);

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\nDemo seed complete.\n";
echo "  Students: " . count($students) . " (password: DemoPass123!)\n";
echo "  Admin:    admin@zetech.ac.ke (password: Admin@12345)\n";
echo "  Reports:  " . count($reports) . "\n";
echo "  Claims:   " . $claimCount . "\n";
echo "  Contacts: " . count($contacts) . "\n";