<?php

require_once __DIR__ . '/../../helpers/auth.php';
requireLogin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/crf.php';

$userId = $_SESSION['user_id'];
$role   = $_SESSION['user_role'];

/*
|--------------------------------------------------------------------------
| Statistik per role
|--------------------------------------------------------------------------
| draft/submitted/dst di bawah ini di-set default 0 dulu, supaya status
| yang belum punya data tetap tampil angka 0 (bukan hilang/error).
*/
$stats = [
    'draft'       => 0,
    'submitted'   => 0,
    'approved'    => 0,
    'rejected'    => 0,
    'in_progress' => 0,
    'completed'   => 0,
];
$totalCrf  = 0;
$recentCrf = [];

if ($role === 'requester') {

    // Hitung CRF milik requester ini, per status
    $sql = "SELECT status, COUNT(*) AS jumlah
            FROM change_requests
            WHERE user_id = :user_id
            GROUP BY status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);

    foreach ($stmt->fetchAll() as $row) {
        $stats[$row['status']] = (int) $row['jumlah'];
        $totalCrf += (int) $row['jumlah'];
    }

    // CRF terbaru milik requester ini
    $sql = "SELECT id, request_number, title, priority, status, created_at
            FROM change_requests
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 5";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $userId]);
    $recentCrf = $stmt->fetchAll();

} elseif ($role === 'approver') {

    /*
     * CATATAN PENTING (sesuai spesifikasi bagian 46):
     * Aturan resmi "approver mana yang menangani CRF mana" belum
     * ditentukan oleh PPU. Karena itu, untuk SEMENTARA, approver
     * melihat SEMUA CRF berstatus submitted, bukan hanya CRF yang
     * "ditugaskan" ke dirinya. Ini akan disesuaikan setelah tabel
     * `approvals` mulai dipakai pada tahap Approval Workflow (Phase 7).
     */

    $sql = "SELECT status, COUNT(*) AS jumlah
            FROM change_requests
            GROUP BY status";
    $stmt = $pdo->query($sql);

    foreach ($stmt->fetchAll() as $row) {
        $stats[$row['status']] = (int) $row['jumlah'];
        $totalCrf += (int) $row['jumlah'];
    }

    $sql = "SELECT cr.id, cr.request_number, cr.title, cr.priority, cr.status, cr.created_at,
                   u.name AS requester
            FROM change_requests cr
            JOIN users u ON cr.user_id = u.id
            WHERE cr.status = 'submitted'
            ORDER BY cr.created_at DESC
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $recentCrf = $stmt->fetchAll();

} else {
    // admin

    $sql = "SELECT status, COUNT(*) AS jumlah FROM change_requests GROUP BY status";
    $stmt = $pdo->query($sql);

    foreach ($stmt->fetchAll() as $row) {
        $stats[$row['status']] = (int) $row['jumlah'];
        $totalCrf += (int) $row['jumlah'];
    }

    $totalUser      = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalRequester = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'requester'")->fetchColumn();
    $totalApprover  = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'approver'")->fetchColumn();

    $sql = "SELECT cr.id, cr.request_number, cr.title, cr.priority, cr.status, cr.created_at,
                   u.name AS requester
            FROM change_requests cr
            JOIN users u ON cr.user_id = u.id
            ORDER BY cr.created_at DESC
            LIMIT 5";
    $stmt = $pdo->query($sql);
    $recentCrf = $stmt->fetchAll();
}

$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="col-md-10 ms-sm-auto px-md-4 py-4">

    <h2 class="mb-1">Dashboard</h2>
    <p class="text-muted">
        Selamat datang, <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
        (<?= htmlspecialchars(ucfirst($role)) ?>)
    </p>

    <!-- ===================== KARTU STATISTIK ===================== -->
    <div class="row g-3 mb-4">

        <?php if ($role === 'admin'): ?>

            <div class="col-sm-6 col-lg-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="fs-2 fw-bold"><?= $totalCrf ?></div>
                        <div>Total CRF</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="fs-2 fw-bold"><?= $totalUser ?></div>
                        <div>Total User</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="fs-2 fw-bold"><?= $totalRequester ?></div>
                        <div>Total Requester</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="fs-2 fw-bold"><?= $totalApprover ?></div>
                        <div>Total Approver</div>
                    </div>
                </div>
            </div>

        <?php elseif ($role === 'approver'): ?>

            <div class="col-sm-6 col-lg-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="fs-2 fw-bold"><?= $stats['submitted'] ?></div>
                        <div>Menunggu Approval</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="fs-2 fw-bold"><?= $stats['approved'] ?></div>
                        <div>Approved</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <div class="fs-2 fw-bold"><?= $stats['rejected'] ?></div>
                        <div>Rejected</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="fs-2 fw-bold"><?= $stats['completed'] ?></div>
                        <div>Completed</div>
                    </div>
                </div>
            </div>

        <?php else: // requester ?>

            <div class="col-sm-6 col-lg-2">
                <div class="card">
                    <div class="card-body">
                        <div class="fs-4 fw-bold"><?= $totalCrf ?></div>
                        <div>Total CRF</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-2">
                <div class="card">
                    <div class="card-body">
                        <div class="fs-4 fw-bold"><?= $stats['draft'] ?></div>
                        <div>Draft</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-2">
                <div class="card">
                    <div class="card-body">
                        <div class="fs-4 fw-bold"><?= $stats['submitted'] ?></div>
                        <div>Submitted</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-2">
                <div class="card">
                    <div class="card-body">
                        <div class="fs-4 fw-bold"><?= $stats['approved'] ?></div>
                        <div>Approved</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-2">
                <div class="card">
                    <div class="card-body">
                        <div class="fs-4 fw-bold"><?= $stats['rejected'] ?></div>
                        <div>Rejected</div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-2">
                <div class="card">
                    <div class="card-body">
                        <div class="fs-4 fw-bold"><?= $stats['completed'] ?></div>
                        <div>Completed</div>
                    </div>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <!-- ===================== TABEL CRF TERBARU ===================== -->
    <div class="card">
        <div class="card-header bg-white">
            <strong>
                <?php if ($role === 'requester'): ?>
                    CRF Terbaru Saya
                <?php elseif ($role === 'approver'): ?>
                    CRF Menunggu Review
                <?php else: ?>
                    CRF Terbaru
                <?php endif; ?>
            </strong>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No. CRF</th>
                        <th>Judul</th>
                        <?php if ($role !== 'requester'): ?>
                            <th>Requester</th>
                        <?php endif; ?>
                        <th>Prioritas</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentCrf)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">
                                Belum ada data.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentCrf as $crf): ?>
                            <tr>
                                <td><?= htmlspecialchars($crf['request_number']) ?></td>
                                <td><?= htmlspecialchars($crf['title']) ?></td>
                                <?php if ($role !== 'requester'): ?>
                                    <td><?= htmlspecialchars($crf['requester']) ?></td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge <?= priorityBadgeClass($crf['priority']) ?>">
                                        <?= htmlspecialchars(ucfirst($crf['priority'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= statusBadgeClass($crf['status']) ?>">
                                        <?= statusLabel($crf['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars(date('d M Y H:i', strtotime($crf['created_at']))) ?></td>
                                <td>
                                    <a href="/crf-ppu/pages/crf/detail.php?id=<?= $crf['id'] ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
