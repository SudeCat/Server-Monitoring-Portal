<?php
session_start();
include 'db.php';


if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}


$sql = "SELECT id, name, logo FROM servers";
$servers = $conn->query($sql);

$selected_servers = isset($_GET['server_id']) ? (array)$_GET['server_id'] : [];
$selected_start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$selected_end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

if (empty($selected_servers)) {
    $sql = "SELECT id FROM servers";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $selected_servers[] = $row['id'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlama</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <style>
        .navbar-brand {
            display: flex;
            align-items: center;
            height: 70px;
        }

        .navbar-brand img {
            height: 130px;
            width: 150px;
            margin-top: 15px;
            margin-right: 15px;
        }

        .status-icon {
            font-size: 24px;
            margin-left: 10px;
        }

        .status-icon.active {
            color: green;
        }

        .status-icon.error {
            color: red;
        }

        .status-icon.timeout {
            color: orange;
        }

        .logo-img {
            width: 50px;
            height: 50px;
        }

        .detail-btn {
            background-color: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
        }

        .detail-btn:hover {
            background-color: #0056b3;
        }

        .detail-item {
            margin-bottom: 10px;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 5px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .detail-item strong {
            color: #007bff;
            display: block;
            margin-bottom: 5px;
        }

        .detail-item span {
            color: #555;
        }

        .modal-content {
            background-color: #f4f4f4;
            border-radius: 10px;
            padding: 20px;
        }

        .modal-header {
            border-bottom: none;
        }

        .modal-footer {
            border-top: none;
            justify-content: center;
        }

        .modal-body .server-info {
            margin-bottom: 20px;
        }

        .modal-body .server-info img {
            margin-right: 10px;
        }

        .modal-body .server-info .server-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .modal-body .server-info .server-desc {
            color: #777;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="./images/file.png" alt="Logo">
                 Sunucu İzleme
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavDropdown">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="reporting.php">Raporlama</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger text-white" href="logout.php">Çıkış Yap</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h3>Sunucu Raporlama</h3>
        <form method="GET" action="reporting.php" class="row">
            <div class="mb-3 col-md-6">
                <label for="server_id" class="form-label">Sunucu Seç:</label>
                <select class="form-control" id="server_id" name="server_id[]" multiple>
                    <?php while ($row = $servers->fetch_assoc()): ?>
                        <option value="<?= $row['id']; ?>" <?= in_array($row['id'], $selected_servers) ? 'selected' : ''; ?>><?= htmlspecialchars($row['name']); ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="mb-3 col-md-6">
                <label for="reportrange" class="form-label">Tarih Aralığı Seç:</label>
                <div id="reportrange" style="background: #fff; cursor: pointer; padding: 5px 10px; border: 1px solid #ccc; width: 100%">
                    <i class="fa fa-calendar"></i>&nbsp;
                    <span></span> <i class="fa fa-caret-down"></i>
                </div>
                <input type="hidden" id="start_date" name="start_date" value="<?= htmlspecialchars($selected_start_date); ?>">
                <input type="hidden" id="end_date" name="end_date" value="<?= htmlspecialchars($selected_end_date); ?>">
            </div>
            <div class="mb-3 col-12 text-end">
                <button type="submit" class="btn btn-primary">Raporu Göster</button>
            </div>
        </form>

        <?php
        $servers_data = [];
        $report_generated = false;

        if (!empty($selected_servers)) {
            foreach ($selected_servers as $server_id) {
                $server_id = intval($server_id);

                $start_date = $selected_start_date . ' 00:00:00';
                $end_date = $selected_end_date . ' 23:59:59';

                $server_name = '';
                $server_logo = '';

                $sql = "SELECT s.name, s.logo, st.status_code, st.status_time 
                        FROM status st 
                        JOIN servers s ON st.server_id = s.id 
                        WHERE st.server_id = ? AND st.status_time BETWEEN ? AND ? 
                        ORDER BY st.status_time ASC";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param('iss', $server_id, $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    $status_changes = [];
                    while ($row = $result->fetch_assoc()) {
                        if (empty($server_name)) {
                            $server_name = $row['name'];
                            $server_logo = $row['logo'];
                        }
                        $status_changes[] = $row;
                    }

                    $total_closed_time = 0;
                    $last_status = null;
                    $last_time = null;

                    $details = [];

                    foreach ($status_changes as $change) {
                        $current_time = strtotime($change['status_time']);
                        if ($last_status !== null && $last_status != 200) {
                            $time_diff = $current_time - $last_time;
                            $total_closed_time += $time_diff;

                            $details[] = "<div class='detail-item'><strong>Kapanma:</strong> " . date('d/m/Y H:i:s', $last_time) . " (Durum Kodu: $last_status)<br><strong>Açılma:</strong> " . date('d/m/Y H:i:s', $current_time) . "<br><strong>Kapalı kalma süresi:</strong> " . gmdate('H:i:s', $time_diff) . "</div>";
                        }
                        $last_status = $change['status_code'];
                        $last_time = $current_time;
                    }

                    $now = time();
                    if ($last_status != 200) {
                        $total_closed_time += $now - $last_time;
                        $details[] = "<div class='detail-item'><strong>Kapanma:</strong> " . date('d/m/Y H:i:s', $last_time) . " (Durum Kodu: $last_status)<br><span>Sunucu hala kapalı.</span><br><strong>Şu ana kadar kapalı kalma süresi:</strong> " . gmdate('H:i:s', $now - $last_time) . "</div>";
                    }

                    if ($total_closed_time > 0) {
                        $servers_data[] = [
                            'name' => $server_name,
                            'logo' => 'uploads/' . $server_logo,
                            'closed_time' => gmdate('H:i:s', $total_closed_time),
                            'details' => $details
                        ];
                        $report_generated = true;
                    }
                }
            }
        }

        if ($report_generated) {
            usort($servers_data, function($a, $b) {
                return strcmp($b['closed_time'], $a['closed_time']);
            });

            echo "<table class='table table-bordered mt-4'>";
            echo "<thead><tr><th>Sunucu</th><th>Logo</th><th>Kapalı Kalma Süresi</th><th>Detay</th></tr></thead>";
            echo "<tbody>";

            foreach ($servers_data as $server) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($server['name']) . "</td>";
                echo "<td><img src='" . htmlspecialchars($server['logo']) . "' alt='Logo' class='logo-img'></td>";
                echo "<td>" . $server['closed_time'] . "</td>";
                echo "<td><span class='detail-btn' onclick='showDetails(" . json_encode($server, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ")'>Detay</span></td>";
                echo "</tr>";
            }

            echo "</tbody>";
            echo "</table>";
        } else {
            echo "<div class='text-center mt-4'><strong>Rapor edilecek bir şey yok.</strong></div>";
        }
        ?>

    </div>


    <div class="modal fade" id="detailModal" tabindex="-1" aria-labelledby="detailModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="detailModalLabel">Detaylı Rapor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="detailContent">

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment/min/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script type="text/javascript">
        $(document).ready(function() {
            $('#server_id').select2({
                placeholder: 'Sunucu Seçiniz',
                allowClear: true
            }).val(<?= json_encode($selected_servers); ?>).trigger('change');

            var start = moment("<?= !empty($selected_start_date) ? $selected_start_date : date("Y-m-d", strtotime("-29 days")); ?>", "YYYY-MM-DD");
            var end = moment("<?= !empty($selected_end_date) ? $selected_end_date : date("Y-m-d"); ?>", "YYYY-MM-DD");

            function cb(start, end) {
                $('#reportrange span').html(start.format('DD/MM/YYYY') + ' - ' + end.format('DD/MM/YYYY'));
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            }

            $('#reportrange').daterangepicker({
                startDate: start,
                endDate: end,
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            }, cb);

            cb(start, end);
        });

        function showDetails(server) {
            let detailContent = '<div class="server-info">';
            detailContent += `<img src="${server.logo}" alt="Logo" class="logo-img">`;
            detailContent += `<div class="server-name">${server.name}</div>`;
            detailContent += `<div class="server-desc">Sunucu durumu ile ilgili detaylar aşağıdadır:</div>`;
            detailContent += '</div>';
            detailContent += server.details.join('');
            $('#detailContent').html(detailContent);
            $('#detailModal').modal('show');
        }
    </script>
</body>

</html>
