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
    | Pastikan CRF milik requester dan masih draft
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
            'CRF tidak ditemukan atau tidak dapat diajukan.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Submit CRF
    |--------------------------------------------------------------------------
    */
    $sqlUpdate = "
        UPDATE change_requests
        SET
            status = 'submitted',
            submission_date = CURRENT_TIMESTAMP
        WHERE id = :id
          AND user_id = :user_id
          AND status = 'draft'
    ";

    $stmtUpdate = $pdo->prepare($sqlUpdate);

    $stmtUpdate->execute([
        ':id'      => $id,
        ':user_id' => $userId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Activity log
    |--------------------------------------------------------------------------
    */
    $sqlLog = "
        INSERT INTO activity_logs (
            change_request_id,
            user_id,
            action,
            description
        )
        VALUES (
            :change_request_id,
            :user_id,
            :action,
            :description
        )
    ";

    $stmtLog = $pdo->prepare($sqlLog);

    $stmtLog->execute([
        ':change_request_id' => $id,
        ':user_id'           => $userId,
        ':action'            => 'submitted',
        ':description'       => 'CRF diajukan oleh requester.'
    ]);

    $pdo->commit();

    header('Location: detail.php?id=' . $id);
    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die($e->getMessage());
}