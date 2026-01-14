<?php
require_once __DIR__ . '/../config/Database.php';

$id = $_GET['id'] ?? 0;

$pdo = Database::connect();

$stmt = $pdo->prepare("
    SELECT 
        queue_id,
        status,
        error_reason,
        created_at
    FROM message_logs
    WHERE queue_id = ?
    ORDER BY created_at ASC
");
$stmt->execute([$id]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);


$env = parse_ini_file(__DIR__ . '/../.env');
define('APP_URL', rtrim($env['APP_URL'], '/'));
?>

<!DOCTYPE html>
<html>
<head>
    <title>Message Timeline</title>
    
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

<link rel="stylesheet" href="../assets/css/style.css">
</head>

<body class="p-4">

<div class="d-flex justify-content-between align-items-center mb-3">
    <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
        ←
    </a>
</div>

<h4>Message Timeline (Queue ID: <?= htmlspecialchars($id) ?>)</h4>

<div class="timeline mt-4">

<?php foreach ($logs as $log): 
    $statusClass = strtolower($log['status']);
?>
    <div class="timeline-item">
        <div class="timeline-dot <?= $statusClass ?>"></div>
        <div class="timeline-content">
            <strong>Status:</strong> <?= htmlspecialchars($log['status']) ?><br>

            <?php if (!empty($log['error_reason'])): ?>
                <div class="text-danger mt-1">
                    <?= nl2br(htmlspecialchars($log['error_reason'])) ?>
                </div>
            <?php endif; ?>

            <div class="timeline-time mt-1">
                <?= htmlspecialchars($log['created_at']) ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

</div>

</body>
</html>
