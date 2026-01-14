<?php
$env = parse_ini_file(__DIR__ . '/../.env');

define('APP_URL', rtrim($env['APP_URL'], '/'));
?>
<!DOCTYPE html>
<html>
<head>
  <title>Bulk Communication Dashboard</title>

  <!-- Bootstrap CDN -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .dashboard-card:hover {
      transform: scale(1.02);
      transition: transform 0.2s;
    }
    </style>
</head>
<body class="bg-light">

<div class="container mt-4">

<h3 class="mb-4">Bulk Communication Dashboard</h3>

<div class="row text-center">
  <div class="col-md-3">
    <a href="details.php?type=all" class="text-decoration-none text-dark">
      <div class="card p-3 shadow-sm dashboard-card">
        <h6>Total Messages</h6>
        <h3 id="total">0</h3>
      </div>
    </a>
  </div>

  <div class="col-md-3">
    <a href="details.php?status=SENT" class="text-decoration-none text-success">
      <div class="card p-3 shadow-sm dashboard-card">
        <h6>Success</h6>
        <h3 id="success">0</h3>
      </div>
    </a>
  </div>

  <div class="col-md-3">
    <a href="details.php?status=FAILED" class="text-decoration-none text-danger">
      <div class="card p-3 shadow-sm dashboard-card">
        <h6>Failed</h6>
        <h3 id="failed">0</h3>
      </div>
    </a>
  </div>

  <div class="col-md-3">
    <a href="details.php?retry=1" class="text-decoration-none text-warning">
      <div class="card p-3 shadow-sm dashboard-card">
        <h6>Retry Attempts</h6>
        <h3 id="retries">0</h3>
      </div>
    </a>
  </div>


</div>

<!-- Charts -->
<div class="row mt-4">
  <div class="col-md-6">
    <div class="card p-3 shadow-sm">
      <h6 class="text-center">SMS vs Email</h6>
      <canvas id="channelChart"></canvas>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card p-3 shadow-sm">
      <h6 class="text-center">Success vs Failure Rate</h6>
      <canvas id="statusChart"></canvas>
    </div>
  </div>
</div>

<!-- Day-wise chart -->
<div class="row mt-4">
  <div class="col-md-12">
    <div class="card p-3 shadow-sm">
      <h6 class="text-center">Daily Success vs Failure</h6>
      <canvas id="dailyChart"></canvas>
    </div>
  </div>
</div>

</div>

<script>
let channelChart, statusChart, dailyChart;
 const APP_URL = "<?= APP_URL ?>";
function loadMetrics() {
  fetch(`${APP_URL}/api/dashboard_metrics.php`)
    .then(res => res.json())
    .then(data => {

      /* Counters */
      total.innerText = data.total ?? 0;
      success.innerText = data.success ?? 0;
      failed.innerText = data.failed ?? 0;
      retries.innerText = data.retries ?? 0;

      /* Destroy old charts (important for refresh) */
      channelChart?.destroy();
      statusChart?.destroy();
      dailyChart?.destroy();

      /* SMS vs Email */
      channelChart = new Chart(channelChartEl(), {
        type: 'pie',
        data: {
          labels: ['SMS', 'Email'],
          datasets: [{
            data: [data.sms ?? 0, data.email ?? 0],
            backgroundColor: ['#7ab8ff', '#8fd19e'] 
          }]
        }
      });

      /* Success vs Failure - Overall Rate*/
      statusChart = new Chart(statusChartEl(), {
        type: 'doughnut',
        data: {
          labels: ['Success', 'Failed'],
          datasets: [{
            data: [data.success ?? 0, data.failed ?? 0],
             backgroundColor: ['#8fd19e', '#f28b8b'] 
          }]
        }
      });

      /* Day-wise success/failure */
      if (data.daily?.length) {
        dailyChart = new Chart(dailyChartEl(), {
          type: 'bar',
          data: {
            labels: data.daily.map(d => d.day),
            datasets: [
              {
                label: 'Success',
                data: data.daily.map(d => d.success),
                backgroundColor: '#8fd19e'
              },
              {
                label: 'Failed',
                data: data.daily.map(d => d.failed),
                backgroundColor: '#f28b8b'
              }
            ]
          }
        });
      }
    });
}


function channelChartEl() { 
    return document.getElementById('channelChart');
 }
function statusChartEl() { 
    return document.getElementById('statusChart'); 
}
function dailyChartEl() { 
    return document.getElementById('dailyChart'); 
}

loadMetrics();

/* Optional real-time */
// setInterval(loadMetrics, 10000);
</script>

</body>
</html>
