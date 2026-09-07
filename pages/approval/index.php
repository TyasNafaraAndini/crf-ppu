<?php

require_once __DIR__ . '/../../helpers/auth.php';
requireLogin();
requireRole(['admin', 'approver']);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/crf.php';

/*
|--------------------------------------------------------------------------
| Ambil CRF yang menunggu approval
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
        cr.status,
        u.name AS requester,
        d.name AS divisi
    FROM change_requests cr
    JOIN users u ON cr.user_id = u.id
    LEFT JOIN divisi d ON cr.divisi_id = d.id
    WHERE cr.status = 'submitted'
    ORDER BY
        CASE cr.priority
            WHEN 'high' THEN 1
            WHEN 'standard' THEN 2
            WHEN 'low' THEN 3
            ELSE 4
        END,
        cr.submission_date ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();

$crfs = $stmt->fetchAll();

$pageTitle = 'Approval';
$activeMenu = 'approval';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 ms-sm-auto px-md-4 py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1">Approval</h2>
            <p class="text-muted mb-0">
                Daftar CRF yang menunggu persetujuan.
            </p>
        </div>
    </div>

    <div class="card">

        <div class="card-header bg-white">
            <strong>CRF Menunggu Approval</strong>
        </div>

        <div class="card-body">

            <?php if (!empty($crfs)): ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">
                            <tr>
                                <th>No. CRF</th>
                                <th>Judul</th>
                                <th>Requester</th>
                                <th>Divisi</th>
                                <th>Prioritas</th>
                                <th>Tanggal Pengajuan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($crfs as $crf): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= htmlspecialchars($crf['request_number']) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($crf['title']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($crf['requester']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($crf['divisi'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <span class="badge <?= priorityBadgeClass($crf['priority']) ?>">
                                            <?= htmlspecialchars(ucfirst($crf['priority'])) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= $crf['submission_date']
                                            ? htmlspecialchars(
                                                date(
                                                    'd M Y H:i',
                                                    strtotime($crf['submission_date'])
                                                )
                                            )
                                            : '-'
                                        ?>
                                    </td>

                                    <td>
                                        <a
                                            href="detail.php?id=<?= $crf['id'] ?>"
                                            class="btn btn-sm btn-primary"
                                        >
                                            <i class="bi bi-eye"></i>
                                            Periksa
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="text-center py-5">

                    <div class="mb-3">
                        <i class="bi bi-check-circle fs-1 text-success"></i>
                    </div>

                    <h5>Tidak ada CRF menunggu approval</h5>

                    <p class="text-muted mb-0">
                        Semua CRF sudah diproses atau belum ada pengajuan baru.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </div>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>