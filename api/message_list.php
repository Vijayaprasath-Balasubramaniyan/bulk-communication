<?php
require_once __DIR__ . '/../config/Database.php';

header('Content-Type: application/json');

$pdo = Database::connect();

$channel = $_GET['channel'] ?? '';
$status  = $_GET['status'] ?? '';
$retry = $_GET['retry'] ?? '';

$where  = [];
$params = [];

// Channel filter
if ($channel !== '') {
    $where[] = 'channel = :channel';
    $params[':channel'] = $channel;
}
//retry filter
if ($retry == 1) {
    $where[] = "status='FAILED' AND retry_count > :max";
    $params[':max'] = 0;
}

// Status filter
if ($status !== '') {
    $where[] = 'status = :status';
    $params[':status'] = $status;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        id,
        channel,
        recipient,
        template_id,
        message,
        status,
        retry_count,
        created_at
    FROM message_queue
    $whereSql
    ORDER BY created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$data = [];

while ($row = $stmt->fetch()) {

    $data[] = [
        'id'          => $row['id'],
        'channel'     => $row['channel'],
        'recipient'   => $row['recipient'],
        'template_id' => $row['template_id'],
        'message'     => $row['message'], 
        'status'      => $row['status'],
        'retry_count' => $row['retry_count'],
        'created_at'  => date('d M Y H:i', strtotime($row['created_at'])),
        'max_retries' => (int)getenv('MAX_RETRIES') ?: 3
    ];
}
//response
echo json_encode([
    'data' => $data
]);
