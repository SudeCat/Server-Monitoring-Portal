<?php
session_start();
include 'db.php';

$success_message = '';
$error_message = '';


if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}

$editing = false;
$current_server = null;
if (isset($_GET['edit'])) {
    $editing = true;
    $id = $_GET['edit'];
    $sql = "SELECT * FROM servers WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current_server = $result->fetch_assoc();
}

if (isset($_POST['save'])) {
    $id = $_POST['id'];
    $name = $_POST['name'];
    $url = $_POST['url'];
    $description = $_POST['description'];
    $timeout = $_POST['timeout'];

    $sql = "UPDATE servers SET name=?, url=?, description=?, timeout=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssii", $name, $url, $description, $timeout, $id);

    if ($stmt->execute()) {
        $success_message = "Sunucu başarıyla güncellendi!";
        $editing = false;
        $current_server = null;
    } else {
        $error_message = "İşlem sırasında hata oluştu: " . $conn->error;
    }
}


$servers = [];
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sql = "SELECT * FROM servers WHERE name LIKE ?";
$stmt = $conn->prepare($sql);
$search_param = "%{$search}%";
$stmt->bind_param("s", $search_param);
$stmt->execute();
$result = $stmt->get_result();
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
    <title>Sunucu Düzenle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="addServer.php">Sunucu Ekle</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="editServer.php">Sunucu Düzenle</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="deleteServer.php">Sunucu Sil</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link btn btn-danger text-white" href="logout.php">Çıkış Yap</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-5">
        <h1>Sunucu Düzenle</h1>

        <?php if (!empty($success_message) || !empty($error_message)): ?>
            <div class="modal fade show" id="messageModal" tabindex="-1" aria-labelledby="messageModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header <?php echo !empty($success_message) ? 'bg-success text-white' : 'bg-danger text-white'; ?>">
                            <h5 class="modal-title" id="messageModalLabel"><?php echo !empty($success_message) ? 'Başarı' : 'Hata'; ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p><?php echo !empty($success_message) ? $success_message : $error_message; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    var messageModal = new bootstrap.Modal(document.getElementById('messageModal'));
                    messageModal.show();

                    setTimeout(function() {
                        messageModal.hide();
                    }, 3000); // 3 saniye sonra modalı kapat
                });
            </script>
        <?php endif; ?>

        <?php if ($editing && $current_server): ?>
            <form method="POST" action="editServer.php?edit=<?= $id ?>">
                <input type="hidden" name="id" value="<?= $current_server['id']; ?>">
                <div class="mb-3">
                    <label for="name" class="form-label">Sunucu Adı:</label>
                    <input type="text" class="form-control" id="name" name="name" required value="<?= $current_server['name']; ?>">
                </div>
                <div class="mb-3">
                    <label for="url" class="form-label">Sunucu URL:</label>
                    <input type="url" class="form-control" id="url" name="url" required value="<?= $current_server['url']; ?>">
                </div>
                <div class="mb-3">
                    <label for="description" class="form-label">Açıklama:</label>
                    <textarea class="form-control" id="description" name="description" required><?= $current_server['description']; ?></textarea>
                </div>
                <div class="mb-3">
                    <label for="timeout" class="form-label">Timeout (saniye):</label>
                    <input type="number" class="form-control" id="timeout" name="timeout" required value="<?= $current_server['timeout']; ?>">
                </div>
                <button type="submit" name="save" class="btn btn-primary">Güncelle</button>
                <a href="editServer.php" class="btn btn-secondary">İptal</a>
            </form>
        <?php else: ?>
            <form method="GET" action="editServer.php" class="mb-3 d-flex">
                <input type="text" class="form-control me-2" placeholder="Sunucu ara..." name="search" id="search" value="<?= htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Ara</button>
            </form>
            <h2>Kayıtlı Sunucular</h2>
            <table id="serversTable" class="table table-striped table-bordered" style="width:100%">
                <thead>
                    <tr>
                        <th>Sunucu Adı</th>
                        <th>Sunucu URL</th>
                        <th>Açıklama</th>
                        <th>Timeout</th>
                        <th>Düzenle</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($servers)): ?>
                        <?php foreach ($servers as $server): ?>
                            <tr>
                                <td><?= htmlspecialchars($server['name']); ?></td>
                                <td><a href="<?= htmlspecialchars($server['url']); ?>" target="_blank"><?= htmlspecialchars($server['url']); ?></a></td>
                                <td><?= htmlspecialchars($server['description']); ?></td>
                                <td><?= htmlspecialchars($server['timeout']); ?> saniye</td>
                                <td><a href="editServer.php?edit=<?= $server['id']; ?>" class="btn btn-warning btn-sm">Düzenle</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">Aradığınız kriterlerde sunucu bulunamadı.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#serversTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/Turkish.json" 
                }
            });
        });
    </script>
</body>

</html>