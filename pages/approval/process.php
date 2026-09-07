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
$comment = trim($_POST['comment'] ?? '');

if (!$changeRequestId || !is_numeric($changeRequestId)) {
    die('ID CRF tidak valid.');
}

if (!in_array($action, ['approve', 'reject'], true)) {
    die('Aksi approval tidak valid.');
}

if ($action === 'reject' && $comment === '') {
    die('Komentar wajib diisi saat menolak CRF.');
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

if ($crf['status'] !== 'submitted') {
    die('CRF ini sudah diproses atau tidak dapat di-approve.');
}

/*
|--------------------------------------------------------------------------
| Tentukan status approval
|--------------------------------------------------------------------------
*/

if ($action === 'approve') {
    $approvalStatus = 'approved';
    $crfStatus = 'approved';
    $description = 'CRF "' . $crf['request_number'] . '" disetujui.';
} else {
    $approvalStatus = 'rejected';
    $crfStatus = 'rejected';
    $description = 'CRF "' . $crf['request_number'] . '" ditolak.';
}

/*
|--------------------------------------------------------------------------
| Proses approval
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    | Simpan approval
    */

    $sqlApproval = "
        INSERT INTO approvals (
            change_request_id,
            approver_id,
            status,
            comment,
            approved_at,
            created_at,
            updated_at
        ) VALUES (
            :change_request_id,
            :approver_id,
            :status,
            :comment,
            :approved_at,
            NOW(),
            NOW()
        )
    ";

    $stmtApproval = $pdo->prepare($sqlApproval);

    $stmtApproval->execute([
        ':change_request_id' => $changeRequestId,
        ':approver_id' => $userId,
        ':status' => $approvalStatus,
        ':comment' => $comment !== '' ? $comment : null,
        ':approved_at' => date('Y-m-d H:i:s')
    ]);

    /*
    | Update status CRF
    */

    $sqlUpdate = "
        UPDATE change_requests
        SET status = :status,
            updated_at = NOW()
        WHERE id = :id
    ";

    $stmtUpdate = $pdo->prepare($sqlUpdate);

    $stmtUpdate->execute([
        ':status' => $crfStatus,
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
        ':action' => $action === 'approve'
            ? 'approved'
            : 'rejected',
        ':description' => $description
    ]);

    $pdo->commit();

    header('Location: detail.php?id=' . $changeRequestId);
    exit;

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die('Terjadi kesalahan saat memproses approval: ' . $e->getMessage());
}