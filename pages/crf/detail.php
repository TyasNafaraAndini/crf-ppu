<?php

require_once __DIR__ . '/../../helpers/auth.php';
requireLogin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/crf.php';

$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    die('ID CRF tidak valid.');
}

$userId = $_SESSION['user_id'];
$role   = $_SESSION['user_role'];

/*
|--------------------------------------------------------------------------
| Ambil data CRF sesuai role
|--------------------------------------------------------------------------
| Requester hanya dapat melihat CRF miliknya sendiri.
| Admin dan approver dapat melihat semua CRF.
|--------------------------------------------------------------------------
*/

if ($role === 'requester') {
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
        AND cr.user_id = :user_id
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':id'      => $id,
        ':user_id' => $userId
    ]);
} else {
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
}

$crf = $stmt->fetch();

if (!$crf) {
    die('CRF tidak ditemukan.');
}

$pageTitle  = $crf['request_number'];
$activeMenu = 'crf';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 ms-sm-auto px-md-4 py-4">

    <p>
        <a href="index.php" class="text-decoration-none">
            &larr; Kembali ke Daftar CRF
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

    <div class="card mb-3">
        <div class="card-header bg-white">
            <strong>Informasi Pengajuan</strong>
        </div>

        <div class="card-body">
            <div class="row">

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Requester</div>
                    <div>
                        <?= htmlspecialchars($crf['requester']) ?>
                    </div>
                </div>

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Divisi</div>
                    <div>
                        <?= htmlspecialchars($crf['divisi'] ?? '-') ?>
                    </div>
                </div>

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Email</div>
                    <div>
                        <?= htmlspecialchars($crf['email']) ?>
                    </div>
                </div>

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Tipe Perubahan</div>
                    <div>
                        <?= htmlspecialchars($crf['change_type']) ?>
                    </div>
                </div>

                <div class="col-md-6 mb-2">
                    <div class="text-muted small">Kategori</div>
                    <div>
                        <?= htmlspecialchars($crf['category']) ?>
                    </div>
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
                                date(
                                    'd M Y H:i',
                                    strtotime($crf['submission_date'])
                                )
                            )
                            : '-'
                        ?>
                    </div>
                </div>

                <div class="col-md-12 mb-2">
                    <div class="text-muted small">Judul</div>
                    <div>
                        <?= htmlspecialchars($crf['title']) ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header bg-white">
            <strong>Detail Perubahan</strong>
        </div>

        <div class="card-body">

            <h6>Kondisi Saat Ini</h6>

            <p>
                <?= nl2br(
                    htmlspecialchars($crf['current_condition'])
                ) ?>
            </p>

            <h6>Perubahan yang Diminta</h6>

            <p>
                <?= nl2br(
                    htmlspecialchars($crf['requested_change'])
                ) ?>
            </p>

            <h6 class="mb-1">Alasan / Tujuan</h6>

            <p class="mb-0">
                <?= nl2br(
                    htmlspecialchars($crf['reason'])
                ) ?>
            </p>

        </div>
    </div>

    <p class="text-muted small">
        <i class="bi bi-info-circle"></i>
        Lampiran, riwayat aktivitas, dan hasil approval akan ditambahkan
        pada tahap pengembangan berikutnya.
    </p>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>