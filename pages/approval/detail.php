<?php

require_once __DIR__ . '/../../helpers/auth.php';
requireLogin();
requireRole(['admin', 'approver']);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/crf.php';

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    die('ID CRF tidak valid.');
}

/*
|--------------------------------------------------------------------------
| Ambil data CRF
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        cr.id,
        cr.request_number,
        cr.submission_date,
        cr.change_type,
        cr.category,
        cr.priority,
        cr.title,
        cr.current_condition,
        cr.requested_change,
        cr.reason,
        cr.status,
        cr.created_at,
        u.name AS requester,
        u.email,
        d.name AS divisi
    FROM change_requests cr
    JOIN users u ON cr.user_id = u.id
    LEFT JOIN divisi d ON cr.divisi_id = d.id
    WHERE cr.id = :id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':id' => $id
]);

$crf = $stmt->fetch();

if (!$crf) {
    die('CRF tidak ditemukan.');
}

/*
|--------------------------------------------------------------------------
| Ambil attachment
|--------------------------------------------------------------------------
*/

$sqlAttachments = "
    SELECT
        id,
        file_name,
        file_path,
        file_size,
        file_type,
        uploaded_at
    FROM attachments
    WHERE change_request_id = :change_request_id
    ORDER BY uploaded_at DESC
";

$stmtAttachments = $pdo->prepare($sqlAttachments);
$stmtAttachments->execute([
    ':change_request_id' => $crf['id']
]);

$attachments = $stmtAttachments->fetchAll();

/*
|--------------------------------------------------------------------------
| Ambil activity log
|--------------------------------------------------------------------------
*/

$sqlActivities = "
    SELECT
        al.id,
        al.action,
        al.description,
        al.created_at,
        u.name AS user_name
    FROM activity_logs al
    JOIN users u ON al.user_id = u.id
    WHERE al.change_request_id = :change_request_id
    ORDER BY al.created_at DESC, al.id DESC
";

$stmtActivities = $pdo->prepare($sqlActivities);
$stmtActivities->execute([
    ':change_request_id' => $crf['id']
]);

$activities = $stmtActivities->fetchAll();

$pageTitle = $crf['request_number'];
$activeMenu = 'approval';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 ms-sm-auto px-md-4 py-4">

    <p>
        <a href="index.php" class="text-decoration-none">
            &larr; Kembali ke Approval
        </a>
    </p>

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h2 class="mb-0">
            <?= htmlspecialchars($crf['request_number']) ?>
        </h2>

        <span class="badge fs-6 <?= statusBadgeClass($crf['status']) ?>">
            <?= statusLabel($crf['status']) ?>
        </span>

    </div>

    <!-- Informasi Pengajuan -->
    <div class="card mb-3">

        <div class="card-header bg-white">
            <strong>Informasi Pengajuan</strong>
        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Requester</div>
                    <div><?= htmlspecialchars($crf['requester']) ?></div>
                </div>

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Divisi</div>
                    <div><?= htmlspecialchars($crf['divisi'] ?? '-') ?></div>
                </div>

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Email</div>
                    <div><?= htmlspecialchars($crf['email']) ?></div>
                </div>

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Tipe Perubahan</div>
                    <div><?= htmlspecialchars($crf['change_type']) ?></div>
                </div>

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Kategori</div>
                    <div><?= htmlspecialchars($crf['category']) ?></div>
                </div>

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Prioritas</div>
                    <div>
                        <span class="badge <?= priorityBadgeClass($crf['priority']) ?>">
                            <?= htmlspecialchars(ucfirst($crf['priority'])) ?>
                        </span>
                    </div>
                </div>

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Tanggal Pengajuan</div>
                    <div>
                        <?= $crf['submission_date']
                            ? htmlspecialchars(
                                date('d M Y H:i', strtotime($crf['submission_date']))
                            )
                            : '-'
                        ?>
                    </div>
                </div>

                <div class="col-md-12 mb-2">
                    <div class="text-muted small">Judul</div>
                    <div><?= htmlspecialchars($crf['title']) ?></div>
                </div>

            </div>

        </div>

    </div>

    <!-- Detail Perubahan -->
    <div class="card mb-3">

        <div class="card-header bg-white">
            <strong>Detail Perubahan</strong>
        </div>

        <div class="card-body">

            <h6>Kondisi Saat Ini</h6>

            <p>
                <?= nl2br(htmlspecialchars($crf['current_condition'])) ?>
            </p>

            <h6>Perubahan yang Diminta</h6>

            <p>
                <?= nl2br(htmlspecialchars($crf['requested_change'])) ?>
            </p>

            <h6>Alasan / Tujuan</h6>

            <p class="mb-0">
                <?= nl2br(htmlspecialchars($crf['reason'])) ?>
            </p>

        </div>

    </div>

    <!-- Lampiran -->
    <div class="card mb-3">

        <div class="card-header bg-white">
            <strong>Lampiran</strong>
        </div>

        <div class="card-body">

            <?php if (!empty($attachments)): ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead class="table-light">
                            <tr>
                                <th>Nama File</th>
                                <th>Tipe</th>
                                <th>Ukuran</th>
                                <th>Tanggal Upload</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($attachments as $attachment): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($attachment['file_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(strtoupper($attachment['file_type'])) ?>
                                    </td>

                                    <td>
                                        <?= number_format(
                                            $attachment['file_size'] / 1024,
                                            2
                                        ) ?> KB
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            date(
                                                'd M Y H:i',
                                                strtotime($attachment['uploaded_at'])
                                            )
                                        ) ?>
                                    </td>

                                    <td>
                                        <a
                                            href="../../<?= htmlspecialchars($attachment['file_path']) ?>"
                                            target="_blank"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            <i class="bi bi-eye"></i>
                                            Lihat
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <p class="text-muted mb-0">
                    Belum ada lampiran.
                </p>

            <?php endif; ?>

        </div>

    </div>

    <!-- Riwayat Aktivitas -->
    <div class="card mb-3">

        <div class="card-header bg-white">
            <strong>Riwayat Aktivitas</strong>
        </div>

        <div class="card-body">

            <?php if (!empty($activities)): ?>

                <div class="list-group list-group-flush">

                    <?php foreach ($activities as $activity): ?>

                        <div class="list-group-item px-0">

                            <div class="d-flex justify-content-between align-items-start">

                                <div>

                                    <div class="fw-semibold">
                                        <?= htmlspecialchars($activity['description']) ?>
                                    </div>

                                    <div class="text-muted small">
                                        Oleh <?= htmlspecialchars($activity['user_name']) ?>
                                    </div>

                                </div>

                                <small class="text-muted text-nowrap ms-3">
                                    <?= htmlspecialchars(
                                        date(
                                            'd M Y H:i',
                                            strtotime($activity['created_at'])
                                        )
                                    ) ?>
                                </small>

                            </div>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p class="text-muted mb-0">
                    Belum ada aktivitas.
                </p>

            <?php endif; ?>

        </div>

    </div>

    <!-- Approval -->
    <?php if ($crf['status'] === 'submitted'): ?>

        <div class="card mb-3">

            <div class="card-header bg-white">
                <strong>Persetujuan CRF</strong>
            </div>

            <div class="card-body">

                <form method="POST" action="process.php">

                    <input
                        type="hidden"
                        name="change_request_id"
                        value="<?= $crf['id'] ?>"
                    >

                    <div class="mb-3">

                        <label for="comment" class="form-label">
                            Komentar
                        </label>

                        <textarea
                            name="comment"
                            id="comment"
                            class="form-control"
                            rows="4"
                            placeholder="Berikan komentar atau catatan approval..."
                        ></textarea>

                    </div>

                    <div class="d-flex gap-2">

                        <button
                            type="submit"
                            name="action"
                            value="approve"
                            class="btn btn-success"
                        >
                            <i class="bi bi-check-circle"></i>
                            Approve
                        </button>

                        <button
                            type="submit"
                            name="action"
                            value="reject"
                            class="btn btn-danger"
                        >
                            <i class="bi bi-x-circle"></i>
                            Reject
                        </button>

                    </div>

                </form>

            </div>

        </div>

    <?php endif; ?>

    <!-- Progress -->
    <?php if ($crf['status'] === 'approved'): ?>

        <div class="card mb-3">

            <div class="card-header bg-white">
                <strong>Progress CRF</strong>
            </div>

            <div class="card-body">

                <p class="text-muted">
                    CRF telah disetujui dan siap diproses.
                </p>

                <form method="POST" action="progress.php">

                    <input
                        type="hidden"
                        name="change_request_id"
                        value="<?= $crf['id'] ?>"
                    >

                    <button
                        type="submit"
                        name="action"
                        value="start"
                        class="btn btn-primary"
                    >
                        <i class="bi bi-play-circle"></i>
                        Mulai Proses
                    </button>

                </form>

            </div>

        </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>