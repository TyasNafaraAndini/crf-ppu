<?php

require_once __DIR__ . '/../../helpers/auth.php';
requireLogin();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/crf.php';

$userId = $_SESSION['user_id'];
$role   = $_SESSION['user_role'];

if ($role === 'requester') {

    // Requester hanya melihat CRF miliknya sendiri
    $sql = "
        SELECT
            cr.id,
            cr.request_number,
            cr.title,
            cr.priority,
            cr.status,
            cr.created_at,
            u.name AS requester
        FROM change_requests cr
        JOIN users u ON cr.user_id = u.id
        WHERE cr.user_id = :user_id
        ORDER BY cr.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':user_id' => $userId
    ]);

} elseif ($role === 'approver') {

    // Untuk sementara approver melihat CRF yang sudah diajukan
    $sql = "
        SELECT
            cr.id,
            cr.request_number,
            cr.title,
            cr.priority,
            cr.status,
            cr.created_at,
            u.name AS requester
        FROM change_requests cr
        JOIN users u ON cr.user_id = u.id
        WHERE cr.status = 'submitted'
        ORDER BY cr.created_at DESC
    ";

    $stmt = $pdo->query($sql);

} else {

    // Admin melihat semua CRF
    $sql = "
        SELECT
            cr.id,
            cr.request_number,
            cr.title,
            cr.priority,
            cr.status,
            cr.created_at,
            u.name AS requester
        FROM change_requests cr
        JOIN users u ON cr.user_id = u.id
        ORDER BY cr.created_at DESC
    ";

    $stmt = $pdo->query($sql);
}

$changeRequests = $stmt->fetchAll();

$pageTitle  = 'Change Request';
$activeMenu = 'crf';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="col-md-10 ms-sm-auto px-md-4 py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Daftar Change Request</h2>
        <?php if ($role === 'requester'): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> Buat CRF
            </a>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>No. CRF</th>
                        <th>Judul Perubahan</th>
                        <th>Requester</th>
                        <th>Prioritas</th>
                        <th>Status</th>
                        <th>Tanggal Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($changeRequests) > 0): ?>
                        <?php foreach ($changeRequests as $crf): ?>
                            <tr>
                                <td><?= htmlspecialchars($crf['request_number']) ?></td>
                                <td><?= htmlspecialchars($crf['title']) ?></td>
                                <td><?= htmlspecialchars($crf['requester']) ?></td>
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
                                    <div class="d-flex gap-1 flex-wrap">
                                        <a href="detail.php?id=<?= $crf['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            Detail
                                        </a>

                                        <?php if ($role === 'requester' && $crf['status'] === 'draft'): ?>
                                            <a href="edit.php?id=<?= $crf['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                Edit
                                            </a>

                                            <form method="POST" action="submit.php" class="d-inline"
                                                onsubmit="return confirm('Ajukan CRF ini? Setelah diajukan, CRF tidak dapat diedit lagi.');">
                                                <input type="hidden" name="id" value="<?= $crf['id'] ?>">

                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    Ajukan
                                                </button>
                                            </form>

                                            <form method="POST" action="delete.php" class="d-inline"
                                                onsubmit="return confirm('Yakin ingin menghapus CRF ini?');">
                                                <input type="hidden" name="id" value="<?= $crf['id'] ?>">

                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    Hapus
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Belum ada Change Request.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>


</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
