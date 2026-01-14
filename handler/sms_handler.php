<?php
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../vendor/autoload.php';
$pdo = Database::connect();
$env = parse_ini_file(__DIR__ . '/../.env');

define('MAX_RETRIES', (int)$env['MAX_RETRIES']);
$limit = (int)$env['LIMIT_DATA'];

use Twilio\Rest\Client;

$twilio = new Client(
    $env['TWILIO_SID'],
    $env['TWILIO_TOKEN']
);

$pdo->beginTransaction();

$stmt = $pdo->prepare("
SELECT * FROM message_queue
WHERE channel='SMS'
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
         $payload = json_decode($msg['payload'], true);
        $mes = "Hello {$payload['name']}, Greetings from Magick Tech!";
        $pdo->prepare("
            UPDATE message_queue SET status='PROCESSING',
            message = ?
            WHERE id=?
        ")->execute([$mes,$msg['id']]);

       
        //twilio send SMS
       $response = $twilio->messages->create(
            $msg['recipient'],
            [
                'from' => $env['TWILIO_FROM'],
                'body' => $mes
            ]
        );
        
        if ($response->status === 'failed') {
            echo $response->errorCode;  
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
                    $msg['id'], 'FAILED', $response->errorMessage
                ]);
        }else{
            //Success
        $pdo->prepare("
            UPDATE message_queue SET status='SENT'
            WHERE id=?
        ")->execute([$msg['id']]);

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
