<?php
require_once __DIR__ . '/../config/Database.php';

$db = Database::connect();

/* get counts  */
$stmt = $db->prepare("
  SELECT
    COUNT(*) AS total,
    SUM(status='SENT') AS success,
    SUM(status='FAILED') AS failed,
    IFNULL(SUM(retry_count),0) AS retries,
    SUM(channel='SMS') AS sms,
    SUM(channel='EMAIL') AS email
  FROM message_queue
");
$stmt->execute();

$data = $stmt->fetch(PDO::FETCH_ASSOC);

/* last 7 days states */
$stmt = $db->prepare("
  SELECT 
    DATE(created_at) AS day,
    SUM(status='SENT') AS success,
    SUM(status='FAILED') AS failed
  FROM message_queue
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
  GROUP BY day
  ORDER BY day
");
$stmt->execute();

$data['daily'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($data);
