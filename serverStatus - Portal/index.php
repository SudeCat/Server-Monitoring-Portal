<?php
session_start();
include 'db.php';

$success_message = '';
$error_message = '';

$inactivity_limit = 600;

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $inactivity_limit) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$_SESSION['last_activity'] = time();


if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['delete_server_id'])) {
    $server_id = intval($_POST['delete_server_id']);
    $response = ['success' => false, 'message' => ''];

    try {
        $conn->begin_transaction();
        $sql = "SELECT logo FROM servers WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $server_id);
        $stmt->execute();
        $stmt->bind_result($logo);
        $stmt->fetch();
        $stmt->close();

        if ($logo && $logo !== 'default_logo.png') {
            $logo_path = 'uploads/' . $logo;
            if (file_exists($logo_path)) {
                unlink($logo_path);
            }
        }
        $sql = "DELETE FROM status WHERE server_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $server_id);
        $stmt->execute();

        $sql = "DELETE FROM servers WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $server_id);
        $stmt->execute();

        $conn->commit();

        $response['success'] = true;
        $response['message'] = "Sunucu başarıyla silindi!";
    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = "Silme işlemi sırasında bir hata oluştu: " . $e->getMessage();
    }

    echo json_encode($response);
    exit();
}


if (isset($_POST['bulk_delete'])) {
    if (!empty($_POST['server_ids'])) {
        $ids_to_delete = implode(',', array_map('intval', $_POST['server_ids']));
        $conn->begin_transaction();
        try {
            $sql = "SELECT logo FROM servers WHERE id IN ($ids_to_delete)";
            $result = $conn->query($sql);

            while ($row = $result->fetch_assoc()) {
                $logo = $row['logo'];
                if ($logo && $logo !== 'default_logo.png') {
                    $logo_path = 'uploads/' . $logo;
                    if (file_exists($logo_path)) {
                        unlink($logo_path);
                    }
                }
            }

            $sql = "DELETE FROM status WHERE server_id IN ($ids_to_delete)";
            $conn->query($sql);

            $sql = "DELETE FROM servers WHERE id IN ($ids_to_delete)";
            $conn->query($sql);

            $conn->commit();
            $_SESSION['success_message'] = "Seçilen sunucular ve ilgili logolar başarıyla silindi!";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Silme işlemi sırasında bir hata oluştu: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Hiçbir sunucu seçilmedi.";
    }
    header("Location: index.php");
    exit();
}

if (isset($_POST['add'])) {
    $name = $_POST['name'];
    $url = $_POST['url'];
    $description = $_POST['description'];
    $timeout = $_POST['timeout'];


    $logo = 'default_logo.png'; 
    $logo_path = 'uploads/' . $logo; 

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo = time() . '_' . $_FILES['logo']['name'];
        $logo_path = 'uploads/' . $logo;
        move_uploaded_file($_FILES['logo']['tmp_name'], $logo_path);
    }


    $sql = "SELECT * FROM servers WHERE name = ? OR url = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $name, $url);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error_message'] = "Bu sunucu veya URL zaten kayıtlı!";
    } else {
        $sql = "INSERT INTO servers (name, url, description, timeout, logo) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssis', $name, $url, $description, $timeout, $logo);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Sunucu başarıyla eklendi!";
        } else {
            $_SESSION['error_message'] = "İşlem sırasında hata oluştu: " . $conn->error;
        }
    }
    header("Location: index.php");
    exit();
}


if (isset($_POST['save'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $url = $_POST['url'];
    $description = $_POST['description'];
    $timeout = $_POST['timeout'];

    $logo = $_POST['existing_logo'];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $logo = time() . '_' . $_FILES['logo']['name'];
        move_uploaded_file($_FILES['logo']['tmp_name'], 'uploads/' . $logo);
    }

    $sql = "UPDATE servers SET name=?, url=?, description=?, timeout=?, logo=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssisi", $name, $url, $description, $timeout, $logo, $id);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Sunucu başarıyla güncellendi!";
    } else {
        $_SESSION['error_message'] = "İşlem sırasında hata oluştu: " . $conn->error;
    }
    header("Location: index.php");
    exit();
}


$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$filter_sql = '';
if ($filter == 'active') {
    $filter_sql = "WHERE st.status_code = 200";
} elseif ($filter == 'inactive') {
    $filter_sql = "WHERE st.status_code != 200";
} elseif ($filter == 'recent') {
    $filter_sql = "ORDER BY s.check_time DESC";
}

$servers = [];
$sql = "SELECT s.id, s.name, s.url, s.description, s.timeout, s.logo, s.check_time, st.status_code 
        FROM servers s
        LEFT JOIN (
            SELECT st1.server_id, st1.status_code
            FROM status st1
            INNER JOIN (
                SELECT server_id, MAX(status_time) as max_check_time
                FROM status
                GROUP BY server_id
            ) st2 ON st1.server_id = st2.server_id AND st1.status_time = st2.max_check_time
        ) st ON s.id = st.server_id
        $filter_sql";

$result = $conn->query($sql);
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $servers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sunucu Yönetimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
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
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <img src="./images/file.png" alt=" Logo">
                 Sunucu İzleme
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavDropdown">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <?php
                        $email = $_SESSION['email'];
                        $sql = "SELECT first_name, last_name FROM users WHERE email = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("s", $email);
                        $stmt->execute();
                        $stmt->bind_result($first_name, $last_name);
                        $stmt->fetch();
                        $stmt->close();
                        ?>
                        <span class="nav-link">Hoşgeldin, <?= htmlspecialchars($first_name); ?> <?= htmlspecialchars($last_name); ?></span>
                    </li>
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
        
        <div id="popupMessage" class="modal fade" tabindex="-1" aria-labelledby="popupMessageLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="popupMessageLabel">Başarı</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p id="popupMessageText"></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <button class="btn btn-primary" onclick="openAddModal()">Sunucu Ekle</button>
            <div class="btn-group">
                <button class="btn btn-secondary" onclick="applyFilter('active')">Aktif Sunucular</button>
                <button class="btn btn-secondary" onclick="applyFilter('inactive')">Pasif Sunucular</button>
                <button class="btn btn-secondary" onclick="applyFilter('recent')">En Son Kontrol Edilenler</button>
                <button class="btn btn-secondary" onclick="applyFilter('')">Filtreyi Temizle</button>
            </div>
            <button id="bulkDeleteButton" class="btn btn-danger d-none" onclick="confirmBulkDelete()">Seçilenleri Sil</button>
        </div>

        <form id="bulkDeleteForm" method="POST" action="index.php">
            <input type="hidden" name="bulk_delete" value="1">
            <table id="serversTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>
                        <th>Logo</th>
                        <th>Sunucu Adı</th>
                        <th>Açıklama</th>
                        <th>Bağlantı</th>
                        <th>Timeout</th>
                        <th>Status Code</th>
                        <th>Durum</th>
                        <th>Check Time</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servers as $server): ?>
                        <tr data-id="<?= $server['id']; ?>">
                            <td><input type="checkbox" name="server_ids[]" value="<?= $server['id']; ?>" class="selectRow"></td>
                            <td><img src="uploads/<?= htmlspecialchars($server['logo']) ?>" alt="Logo" class="logo-img"></td>
                            <td>
                                <a href="reporting.php?server_id=<?= $server['id']; ?>&start_date=<?= date('Y-m-d'); ?>&end_date=<?= date('Y-m-d'); ?>">
                                    <?= htmlspecialchars($server['name']); ?>
                                </a>
                            </td>


                            <td><?= htmlspecialchars($server['description']); ?></td>
                            <td><a href="<?= htmlspecialchars($server['url']); ?>" target="_blank"><?= htmlspecialchars($server['url']); ?></a></td>
                            <td><?= htmlspecialchars($server['timeout']); ?> saniye</td>
                            <td><?= htmlspecialchars($server['status_code']); ?></td>
                            <td>
                                <?php if ($server['status_code'] == 200): ?>
                                    <i class="fas fa-check-circle status-icon active"></i>
                                <?php elseif ($server['status_code'] >= 400 && $server['status_code'] < 500): ?>
                                    <i class="fas fa-times-circle status-icon error"></i>
                                <?php else: ?>
                                    <i class="fas fa-exclamation-circle status-icon timeout"></i>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($server['check_time']); ?></td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownMenuButton<?= $server['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                        İşlemler
                                    </button>
                                    <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton<?= $server['id']; ?>">
                                        <li><a class="dropdown-item" href="#" onclick="openEditModal(<?= $server['id']; ?>, '<?= htmlspecialchars($server['name']); ?>', '<?= htmlspecialchars($server['url']); ?>', '<?= htmlspecialchars($server['description']); ?>', <?= $server['timeout']; ?>, '<?= htmlspecialchars($server['logo']); ?>')">Düzenle</a></li>
                                        <li><a class="dropdown-item" href="javascript:void(0);" onclick="deleteServer(<?= $server['id']; ?>)">Sil</a></li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </form>
    </div>


    <div class="modal fade" id="addModal" tabindex="-1" aria-labelledby="addModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addModalLabel">Sunucu Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="addName" class="form-label">Sunucu Adı:</label>
                            <input type="text" class="form-control" id="addName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="addUrl" class="form-label">Sunucu URL:</label>
                            <input type="url" class="form-control" id="addUrl" name="url" required>
                        </div>
                        <div class="mb-3">
                            <label for="addDescription" class="form-label">Açıklama:</label>
                            <textarea class="form-control" id="addDescription" name="description" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="addTimeout" class="form-label">Timeout (saniye):</label>
                            <input type="number" class="form-control" id="addTimeout" name="timeout" value="5" required>
                        </div>

                        <div class="mb-3">
                            <label for="addLogo" class="form-label">Logo:</label>
                            <input type="file" class="form-control" id="addLogo" name="logo">
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="add" class="btn btn-primary">Ekle</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editModalLabel">Sunucu Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php" enctype="multipart/form-data">
                        <input type="hidden" name="id" id="editId">
                        <input type="hidden" name="existing_logo" id="editExistingLogo">
                        <div class="mb-3">
                            <label for="editName" class="form-label">Sunucu Adı:</label>
                            <input type="text" class="form-control" id="editName" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="editUrl" class="form-label">Sunucu URL:</label>
                            <input type="url" class="form-control" id="editUrl" name="url" required>
                        </div>
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Açıklama:</label>
                            <textarea class="form-control" id="editDescription" name="description" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="editTimeout" class="form-label">Timeout (saniye):</label>
                            <input type="number" class="form-control" id="editTimeout" name="timeout" required>
                        </div>
                        <div class="mb-3">
                            <label for="editLogo" class="form-label">Logo:</label>
                            <input type="file" class="form-control" id="editLogo" name="logo">
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="save" class="btn btn-primary">Güncelle</button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#serversTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/Turkish.json" // Türkçe dil desteği
                }
            });

            $('#selectAll').on('click', function() {
                var rows = $('#serversTable').find('tbody tr');
                var isChecked = $(this).prop('checked');
                rows.each(function() {
                    var checkbox = $(this).find('input[type="checkbox"]');
                    checkbox.prop('checked', isChecked);
                });
                toggleBulkDeleteButton();
            });

            $('.selectRow').on('change', function() {
                toggleBulkDeleteButton();
            });

            
            <?php if (isset($_SESSION['success_message'])): ?>
                showPopupMessage("<?= $_SESSION['success_message']; ?>", "success");
                <?php unset($_SESSION['success_message']); ?>
            <?php elseif (isset($_SESSION['error_message'])): ?>
                showPopupMessage("<?= $_SESSION['error_message']; ?>", "error");
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>
        });

        function confirmDelete(serverName) {
            return confirm(serverName + " sunucusunu ve ilişkili tüm verileri silmek istediğinize emin misiniz?");
        }

        function confirmBulkDelete() {
            if (confirm("Seçilen sunucuları ve ilişkili tüm verileri silmek istediğinize emin misiniz?")) {
                $('#bulkDeleteForm').submit();
            }
        }

        function toggleBulkDeleteButton() {
            var selectedRows = $('.selectRow:checked').length;
            if (selectedRows > 0) {
                $('#bulkDeleteButton').removeClass('d-none');
            } else {
                $('#bulkDeleteButton').addClass('d-none');
            }
        }

        function openEditModal(id, name, url, description, timeout, logo) {
            $('#editId').val(id);
            $('#editName').val(name);
            $('#editUrl').val(url);
            $('#editDescription').val(description);
            $('#editTimeout').val(timeout);
            $('#editExistingLogo').val(logo);
            $('#editModal').modal('show');
        }

        function openAddModal() {
            $('#addModal').modal('show');
        }

        function applyFilter(filter) {
            window.location.href = 'index.php?filter=' + filter;
        }

        function deleteServer(serverId) {
            if (confirm("Bu sunucuyu silmek istediğinizden emin misiniz?")) {
                $.post('index.php', {
                    delete_server_id: serverId
                }, function(response) {
                    if (response.success) {
                        showPopupMessage(response.message, "success");
                        $('tr[data-id="' + serverId + '"]').remove(); 
                    } else {
                        showPopupMessage(response.message, "error");
                    }
                }, 'json');
            }
        }

        function showPopupMessage(message, type) {
            $('#popupMessageText').text(message);
            var popupMessage = new bootstrap.Modal(document.getElementById('popupMessage'));
            var modalHeader = $('.modal-header');
            if (type === "success") {
                modalHeader.removeClass('bg-danger').addClass('bg-success');
            } else {
                modalHeader.removeClass('bg-success').addClass('bg-danger');
            }
            popupMessage.show();
            setTimeout(function() {
                popupMessage.hide();
            }, 3000);
        }
    </script>
</body>

</html>
