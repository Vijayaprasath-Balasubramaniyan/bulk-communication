<?php
require_once __DIR__ . '/../config/Database.php';

$pdo = Database::connect();
$env = parse_ini_file(__DIR__ . '/../.env');


// custom campaign id
$campaign = 'BULK_' . date('Ymd_His');


//insert into queue for Email
$sql = "
INSERT IGNORE INTO message_queue
(user_id, channel, recipient, template_id, payload, campaign)
SELECT
    u.id,
    'EMAIL',
    u.email,
    :template,
    JSON_OBJECT('name', u.name),
    :campaign
FROM users u
WHERE u.email IS NOT NULL
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':template' => $env['SENDGRID_TEMPLATE_ID'],
    ':campaign' => $campaign
]);

//insert into queue for SMS
$sql = "
INSERT IGNORE INTO message_queue
(user_id, channel, recipient, payload, campaign)
SELECT
    u.id,
    'SMS',
    u.phone,
    JSON_OBJECT('name', u.name),
    :campaign
FROM users u
WHERE u.phone IS NOT NULL
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':campaign' => $campaign
]);

echo "Users enqueued\n";
