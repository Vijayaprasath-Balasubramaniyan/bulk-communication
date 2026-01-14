<?php
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: application/json');

$pdo = Database::connect();
$env = parse_ini_file(__DIR__ . '/../.env');

$id = (int)($_POST['id'] ?? 0);

// validation
if (!$id) {
    echo json_encode(['success' => false, 'msg' => 'Invalid message ID']);
    exit;
}

// Fetch message
$stmt = $pdo->prepare("SELECT * FROM message_queue WHERE id=?");
$stmt->execute([$id]);
$msg = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$msg) {
    echo json_encode(['success' => false, 'msg' => 'Message not found']);
    exit;
}

try {

  // email handler logic
    if ($msg['channel'] === 'EMAIL') {

        $payload = [
            "personalizations" => [[
                "to" => [["email" => $msg['recipient']]],
                "dynamic_template_data" => json_decode($msg['payload'], true)
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
        curl_close($ch);

        if ($httpCode !== 202) {
            throw new Exception("SendGrid Error: {$response}");
        }
    }

    /* SMS handler logic */
    if ($msg['channel'] === 'SMS') {

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$env['TWILIO_SID']}/Messages.json";
          $payload = json_decode($msg['payload'], true);
            $mes = "Hello {$payload['name']}, Greetings from Magick Tech!";

        $data = http_build_query([
            'From' => $env['TWILIO_FROM'],
            'To'   => $msg['recipient'],
            'Body' => $mes
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => "{$env['TWILIO_SID']}:{$env['TWILIO_TOKEN']}"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new Exception("Twilio Error: {$response}");
        }
    }

    /* success  */

    $pdo->prepare("
        UPDATE message_queue
        SET status='SENT',
            retry_count=0,
            next_retry_at=NULL,
            sent_at=NOW()
        WHERE id=?
    ")->execute([$id]);

    // $pdo->prepare("
    //     INSERT INTO message_logs
    //     (queue_id, status, error_reason, created_at)
    //     VALUES (?, 'SENT', 'Manual retry success', NOW())
    // ")->execute([$id]);
//response
    echo json_encode([
        'success' => true,
        'msg' => 'Message sent successfully'
    ]);

} catch (Exception $e) {

    // Failed manual retry
    $pdo->prepare("
        UPDATE message_queue
        SET status='FAILED'
        WHERE id=?
    ")->execute([$id]);

    $pdo->prepare("
        INSERT INTO message_logs
        (queue_id, status, error_reason, created_at)
        VALUES (?, 'FAILED', ?, NOW())
    ")->execute([$id, $e->getMessage()]);

    //response
    echo json_encode([
        'success' => false,
        'msg' => 'Retry failed. Check logs.'
    ]);
}
