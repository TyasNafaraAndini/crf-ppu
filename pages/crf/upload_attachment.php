<?php

require_once __DIR__ . '/../../helpers/auth.php';
requireLogin();
requireRole(['requester']);

require_once __DIR__ . '/../../config/database.php';

$userId = $_SESSION['user_id'];
$changeRequestId = $_POST['change_request_id'] ?? null;

$allowedExtensions = [
    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'jpg',
    'png'
];

$maxFileSize = 5 * 1024 * 1024; // 5 MB

if (!$changeRequestId || !is_numeric($changeRequestId)) {
    die('ID CRF tidak valid.');
}

if (!isset($_FILES['attachments'])) {
    die('Tidak ada file yang dipilih.');
}

try {

    /*
    |--------------------------------------------------------------------------
    | Pastikan CRF milik requester dan masih draft
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT id
        FROM change_requests
        WHERE id = :id
          AND user_id = :user_id
          AND status = 'draft'
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':id' => $changeRequestId,
        ':user_id' => $userId
    ]);

    $crf = $stmt->fetch();

    if (!$crf) {
        die('CRF tidak ditemukan atau tidak dapat diubah.');
    }

    /*
    |--------------------------------------------------------------------------
    | Folder upload
    |--------------------------------------------------------------------------
    */

    $uploadDirectory = __DIR__ . '/../../uploads/crf';

    if (!is_dir($uploadDirectory)) {
        mkdir($uploadDirectory, 0755, true);
    }

    /*
    |--------------------------------------------------------------------------
    | Proses setiap file
    |--------------------------------------------------------------------------
    */

    foreach ($_FILES['attachments']['name'] as $index => $fileName) {

        $fileError = $_FILES['attachments']['error'][$index] ?? UPLOAD_ERR_NO_FILE;

        if ($fileError === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($fileError !== UPLOAD_ERR_OK) {
            die("Gagal mengupload file: {$fileName}.");
        }

        $tmpName = $_FILES['attachments']['tmp_name'][$index];
        $fileSize = $_FILES['attachments']['size'][$index];
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        /*
        |--------------------------------------------------------------------------
        | Validasi extension
        |--------------------------------------------------------------------------
        */

        if (!in_array($extension, $allowedExtensions, true)) {
            die("Format file {$fileName} tidak diperbolehkan.");
        }

        /*
        |--------------------------------------------------------------------------
        | Validasi ukuran
        |--------------------------------------------------------------------------
        */

        if ($fileSize > $maxFileSize) {
            die("Ukuran file {$fileName} melebihi 5 MB.");
        }

        /*
        |--------------------------------------------------------------------------
        | Buat nama file unik
        |--------------------------------------------------------------------------
        */

        $safeFileName = uniqid('crf_', true) . '.' . $extension;
        $targetPath = $uploadDirectory . '/' . $safeFileName;

        if (!move_uploaded_file($tmpName, $targetPath)) {
            die("Gagal menyimpan file {$fileName}.");
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan metadata ke database
        |--------------------------------------------------------------------------
        */

        $relativePath = 'uploads/crf/' . $safeFileName;

        $sqlAttachment = "
            INSERT INTO attachments (
                change_request_id,
                file_name,
                file_path,
                file_size,
                file_type,
                uploaded_by
            )
            VALUES (
                :change_request_id,
                :file_name,
                :file_path,
                :file_size,
                :file_type,
                :uploaded_by
            )
        ";

        $stmtAttachment = $pdo->prepare($sqlAttachment);

        $stmtAttachment->execute([
            ':change_request_id' => $changeRequestId,
            ':file_name' => $fileName,
            ':file_path' => $relativePath,
            ':file_size' => $fileSize,
            ':file_type' => $extension,
            ':uploaded_by' => $userId
        ]);
    }

    header('Location: detail.php?id=' . $changeRequestId);
    exit;

} catch (PDOException $e) {

    die('Gagal menyimpan attachment: ' . $e->getMessage());
}