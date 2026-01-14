<?php
require_once __DIR__ . '/../config/Database.php';

$pdo = Database::connect();
$env = parse_ini_file(__DIR__ . '/../.env');

define('MAX_RETRIES', (int)$env['MAX_RETRIES']);
$limit = (int)$env['LIMIT_DATA'];
$pdo->beginTransaction();
$stmt = $pdo->prepare("
SELECT * FROM message_queue
WHERE channel='EMAIL'
AND status IN ('PENDING','FAILED')
AND (next_retry_at IS NULL OR next_retry_at <= NOW())
AND retry_count < :max
LIMIT $limit
FOR UPDATE
");

$stmt->execute([':max' => MAX_RETRIES]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pdo->commit();
foreach ($messages as $msg) {

    try {
        $pdo->prepare("
            UPDATE message_queue SET status='PROCESSING'
            WHERE id=?
        ")->execute([$msg['id']]);
        $payload = [
            "personalizations" => [[
                "to" => [["email" => $msg['recipient']]],
                "dynamic_template_data" =>
                    json_decode($msg['payload'], true)
            ]],
            "from" => ["email" => $env['SENDGRID_FROM_EMAIL']],
            "template_id" => $env['SENDGRID_TEMPLATE_ID']
        ];

        $ch = curl_init($env['SENDGRID_API']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$env['SENDGRID_API_KEY']}",
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true
        ]);

    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        echo $response;
        if ($httpCode === 202) {

            //  Success
            $pdo->prepare("
                UPDATE message_queue
                SET status='SENT'
                WHERE id=?
            ")->execute([$msg['id']]);

        } else {

            //  Faillure
            $retry = $msg['retry_count'] + 1;

            $errorReason = $response ?: 'Unknown SendGrid error';

            $pdo->prepare("
                UPDATE message_queue
                SET status='FAILED',
                    retry_count=?,
                    next_retry_at=?
                WHERE id=?
            ")->execute([
                $retry,
                date('Y-m-d H:i:s', time() + pow(2, $retry) * 60),
                $msg['id']
            ]);

            // LOG 
            $pdo->prepare("
                INSERT INTO message_logs
                (queue_id, status, error_reason)
                VALUES (?, ?, ?)
            ")->execute([
                $msg['id'],
                'FAILED',
                "HTTP {$httpCode} - {$errorReason}"
            ]);
        }

    } catch (Exception $e) {

    //Failed Scenarios
        $retry = $msg['retry_count'] + 1;

        $pdo->prepare("
            UPDATE message_queue
            SET status='FAILED',
                retry_count=?,
                next_retry_at=?
            WHERE id=?
        ")->execute([
            $retry,
            date('Y-m-d H:i:s', time() + pow(2, $retry) * 60),
            $msg['id']
        ]);
          $pdo->prepare("
            INSERT INTO message_logs
            (queue_id,status,error_reason)
            VALUES (?,?,?)
        ")->execute([
            $msg['id'], 'FAILED', $e->getMessage()
        ]);
    }
}
