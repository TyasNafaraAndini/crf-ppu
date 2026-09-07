<?php

require_once __DIR__ . '/../../helpers/auth.php';
requireLogin();
requireRole(['requester']);

require_once __DIR__ . '/../../config/database.php';

$userId = $_SESSION['user_id'];
$attachmentId = $_POST['attachment_id'] ?? null;

if (!$attachmentId || !is_numeric($attachmentId)) {
    die('ID attachment tidak valid.');
}

try {
    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | Ambil attachment dan pastikan:
    | - attachment ada
    | - CRF milik requester
    | - CRF masih draft
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            a.id,
            a.file_name,
            a.file_path,
            cr.id AS change_request_id
        FROM attachments a
        JOIN change_requests cr
            ON a.change_request_id = cr.id
        WHERE a.id = :attachment_id
          AND cr.user_id = :user_id
          AND cr.status = 'draft'
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':attachment_id' => $attachmentId,
        ':user_id' => $userId
    ]);

    $attachment = $stmt->fetch();

    if (!$attachment) {
        throw new RuntimeException(
            'Attachment tidak ditemukan atau tidak dapat dihapus.'
        );
    }

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
        ':change_request_id' => $attachment['change_request_id'],
        ':user_id' => $userId,
        ':action' => 'attachment_deleted',
        ':description' => 'Lampiran "' . $attachment['file_name'] . '" dihapus.'
    ]);

    /*
    |--------------------------------------------------------------------------
    | Hapus record attachment dari database
    |--------------------------------------------------------------------------
    */

    $sqlDelete = "
        DELETE FROM attachments
        WHERE id = :attachment_id
    ";

    $stmtDelete = $pdo->prepare($sqlDelete);

    $stmtDelete->execute([
        ':attachment_id' => $attachmentId
    ]);

    /*
    |--------------------------------------------------------------------------
    | Hapus file fisik
    |--------------------------------------------------------------------------
    */

    $filePath = __DIR__ . '/../../' . $attachment['file_path'];

    if (is_file($filePath)) {
        unlink($filePath);
    }

    $pdo->commit();

    header(
        'Location: detail.php?id=' . $attachment['change_request_id']
    );

    exit;

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die($e->getMessage());
}