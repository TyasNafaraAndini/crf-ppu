<?php

require_once __DIR__ . '/../../helpers/auth.php';

requireLogin();
requireRole(['requester']);

require_once __DIR__ . '/../../config/database.php';

$userId = $_SESSION['user_id'];

$id = $_POST['id'] ?? null;

if (!$id || !is_numeric($id)) {
    die('ID CRF tidak valid.');
}

try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Pastikan CRF:
    | - milik requester yang sedang login
    | - masih draft
    |--------------------------------------------------------------------------
    */
    $sql = "
        SELECT id, request_number
        FROM change_requests
        WHERE id = :id
          AND user_id = :user_id
          AND status = 'draft'
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':id'      => $id,
        ':user_id' => $userId
    ]);

    $crf = $stmt->fetch();

    if (!$crf) {
        throw new RuntimeException(
            'CRF tidak ditemukan atau tidak dapat dihapus.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Hapus CRF
    |--------------------------------------------------------------------------
    | activity_logs dan attachment yang memiliki ON DELETE CASCADE
    | akan ikut terhapus.
    |--------------------------------------------------------------------------
    */
    $sqlDelete = "
        DELETE FROM change_requests
        WHERE id = :id
          AND user_id = :user_id
          AND status = 'draft'
    ";

    $stmtDelete = $pdo->prepare($sqlDelete);

    $stmtDelete->execute([
        ':id'      => $id,
        ':user_id' => $userId
    ]);

    $pdo->commit();

    header('Location: index.php');
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die($e->getMessage());
}