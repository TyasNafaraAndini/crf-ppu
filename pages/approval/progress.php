<?php

require_once __DIR__ . '/../../helpers/auth.php';
requireLogin();
requireRole(['admin', 'approver']);

require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$changeRequestId = $_POST['change_request_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$changeRequestId || !is_numeric($changeRequestId)) {
    die('ID CRF tidak valid.');
}

if ($action !== 'start') {
    die('Aksi tidak valid.');
}

$userId = $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Ambil data CRF
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        request_number,
        status
    FROM change_requests
    WHERE id = :id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':id' => $changeRequestId
]);

$crf = $stmt->fetch();

if (!$crf) {
    die('CRF tidak ditemukan.');
}

if ($crf['status'] !== 'approved') {
    die('CRF belum berstatus approved.');
}

try {

    $pdo->beginTransaction();

    /*
    | Update status CRF
    */

    $stmtUpdate = $pdo->prepare("
        UPDATE change_requests
        SET status = 'in_progress',
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmtUpdate->execute([
        ':id' => $changeRequestId
    ]);

    /*
    | Simpan activity log
    */

    $stmtLog = $pdo->prepare("
        INSERT INTO activity_logs (
            change_request_id,
            user_id,
            action,
            description,
            created_at
        ) VALUES (
            :change_request_id,
            :user_id,
            :action,
            :description,
            NOW()
        )
    ");

    $stmtLog->execute([
        ':change_request_id' => $changeRequestId,
        ':user_id' => $userId,
        ':action' => 'in_progress',
        ':description' => 'CRF "' . $crf['request_number'] . '" mulai diproses.'
    ]);

    $pdo->commit();

    header('Location: detail.php?id=' . $changeRequestId);
    exit;

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die('Terjadi kesalahan saat memulai proses: ' . $e->getMessage());
}