<?php
session_start();
include 'db.php';

$success_message = '';  
$error_message = '';    


if (!isset($_SESSION['email'])) {
    header("Location: login.php");
    exit();
}


if (isset($_POST['save'])) {
    $name = $_POST['name'];
    $url = $_POST['url'];
    $description = $_POST['description'];
    $timeout = $_POST['timeout'];


    $sql = "SELECT * FROM servers WHERE name = ? OR url = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $name, $url);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $error_message = "Bu sunucu veya URL zaten kayıtlı!";
    } else {
 
        $sql = "INSERT INTO servers (name, url, description, timeout) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssi", $name, $url, $description, $timeout);

        if ($stmt->execute()) {
            $success_message = "Sunucu başarıyla eklendi!";
        } else {
            $error_message = "İşlem sırasında hata oluştu: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sunucu Ekle</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
        <h1>Sunucu Ekle</h1>
        <form method="POST" action="addServer.php">
            <div class="mb-3">
                <label for="name" class="form-label">Sunucu Adı:</label>
                <input type="text" class="form-control" id="name" name="name" required>
            </div>
            <div class="mb-3">
                <label for="url" class="form-label">Sunucu URL:</label>
                <input type="url" class="form-control" id="url" name="url" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Açıklama:</label>
                <textarea class="form-control" id="description" name="description" required></textarea>
            </div>
            <div class="mb-3">
                <label for="timeout" class="form-label">Timeout (saniye):</label>
                <input type="number" class="form-control" id="timeout" name="timeout" required>
            </div>
            <button type="submit" name="save" class="btn btn-primary">Ekle</button>
        </form>
        <a href="index.php" class="btn btn-secondary mt-3">Geri Dön</a>
    </div>

    
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
            }, 3000); 
        });
    </script>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
