<?php
$status = $_GET['status'] ?? '';
$retry = $_GET['retry'] ?? null;
$env = parse_ini_file(__DIR__ . '/../.env');
define('APP_URL', rtrim($env['APP_URL'], '/'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Message Queue</title>


<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/style.css">
</head>

<body>

<div class="container-fluid mt-4">


<div class="d-flex justify-content-between align-items-center mb-3">
  <a href="dashboard.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left"></i>
  </a>

</div>

<!-- filters -->
<div class="filter-box mb-3 shadow-sm">
  <div class="row g-2 align-items-center">
    <div class="col-md-2">
      <select id="channel" class="form-select">
        <option value="">All Channels</option>
        <option value="SMS">SMS</option>
        <option value="EMAIL">Email</option>
      </select>
    </div>

    <div class="col-md-2">
      <select id="status" class="form-select">
        <option value="">All Status</option>
        <option value="SENT">Sent</option>
        <option value="FAILED">Failed</option>
        <option value="PENDING">Pending</option>
      </select>
    </div>

    <div class="col-md-2">
      <button class="btn btn-primary filt w-100" onclick="reloadTable()">
        <i class="bi bi-funnel"></i> Filter
      </button>
    </div>
  </div>
</div>


<div class="card shadow-sm">
<div class="card-body">
<table id="queueTable" class="table table-borderless table-hover w-100">
  <thead class="border-bottom">
    <tr class="text-muted">
      <th>Channel</th>
      <th>Recipient</th>
      <th>Template / Message</th>
      <th>Status</th>
      <th>Retries</th>
      <th>Date</th>
      <th>Action</th> 
    </tr>
  </thead>
</table>
</div>
</div>

</div>

<!-- script -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
let table;
 const APP_URL = "<?= APP_URL ?>";
$(document).ready(function () {

  $('#status').val("<?= $status ?>");

  table = $('#queueTable').DataTable({
    pageLength: 10,
    lengthChange: false,
    ordering: false,
    ajax: {
      url: `${APP_URL}/api/message_list.php`,
      data: function (d) {
        d.channel = $('#channel').val();
        d.status  = $('#status').val();
        d.retry   = "<?= $retry ?>";
      }
    },
    columns: [

  { data: 'channel', render: d => `
    <div class="d-flex align-items-center gap-2">
      <div class="channel-icon ${d === 'SMS' ? 'sms' : 'email'}">
        <i class="bi ${d === 'SMS' ? 'bi-chat-dots' : 'bi-envelope'}"></i>
      </div>
      <strong>${d}</strong>
    </div>
  `},

  { data: 'recipient' },

 {
  data: null,
  render: function (row) {

    // Template-based message
    if (row.template_id) {
      return `<span class="badge bg-info">${row.template_id}</span>`;
    }

    // SMS content (no template)
    return `
      <span class="text-muted" title="${row.message}">
        ${row.message ? row.message.substring(0, 40) + '…' : '-N/A-'}
      </span>
    `;
  }
},


  { data: 'status', render: d => `
    <span class="status-pill status-${d.toLowerCase()}">${d}</span>
  `},

  { data: 'retry_count' },

  { data: 'created_at' },

  // action culumn buttons
  {
    data: null,
     render: function (row) {

    //Manual retry(Max retry reached )
    if (row.status === 'FAILED' && row.retry_count >= row.max_retries) {
      return `<div class="action-buttons">
        <button class="btn btn-sm btn-warning retry-btn"
                data-id="${row.id}">
          <i class="bi bi-arrow-clockwise"></i> Retry
        </button>
        <a href="logs.php?id=${row.id}"
           class="btn btn-sm btn-outline-danger ms-1">
           Log
        </a>
      </div>`;
    }

    // Normal failed
    if (row.status === 'FAILED') {
      return `
        <a href="logs.php?id=${row.id}"
           class="btn btn-sm btn-outline-danger">
           Log
        </a>
      `;
    }

    return `<span class="text-muted">-</span>`;
  }
  }

]
  });
});

function reloadTable() {
  table.ajax.reload();
}

$(document).on('click', '.retry-btn', function () {

  const id = $(this).data('id');

  if (!confirm('Retry this message manually?')) return;

  $.post(`${APP_URL}/api/manual_retry.php`, { id }, function (res) {

    if (res.success) {
      showToast(res.msg, 'success');
      reloadTable();
    } else {
      showToast(res.msg, 'danger');
    }

  }, 'json');
});

// Toast notification
function showToast(msg, type) {
  const toast = `
  <div class="toast align-items-center text-bg-${type} border-0 position-fixed top-0 end-0 m-3"
       role="alert" data-bs-delay="3000">
    <div class="d-flex">
      <div class="toast-body">${msg}</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto"
              data-bs-dismiss="toast"></button>
    </div>
  </div>`;

  $('body').append(toast);
  const t = new bootstrap.Toast($('.toast').last()[0]);
  t.show();
}
</script>

</body>
</html>
