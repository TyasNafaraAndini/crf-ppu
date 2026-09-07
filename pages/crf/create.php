<?php

require_once __DIR__ . '/../../helpers/auth.php';
requireLogin();
requireRole(['requester']);

require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| Ambil data user yang sedang login
|--------------------------------------------------------------------------
*/
$userId = $_SESSION['user_id'];

$sql = "
    SELECT 
        u.id,
        u.name,
        u.email,
        u.divisi_id,
        d.name AS divisi_name
    FROM users u
    LEFT JOIN divisi d ON u.divisi_id = d.id
    WHERE u.id = :user_id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':user_id' => $userId
]);

$user = $stmt->fetch();

if (!$user) {
    die('Data user tidak ditemukan.');
}


/*
|--------------------------------------------------------------------------
| Data dropdown sementara
|--------------------------------------------------------------------------
| Nanti bisa kita pindahkan ke tabel master.
|--------------------------------------------------------------------------
*/
$changeTypes = [
    'Normal',
    'Emergency'
];

$categories = [
    'Application',
    'Hardware',
    'Software',
    'Network',
    'Database',
    'Security',
    'Infrastructure',
    'Other'
];


/*
|--------------------------------------------------------------------------
| Proses form
|--------------------------------------------------------------------------
*/
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $changeType       = trim($_POST['change_type'] ?? '');
    $category         = trim($_POST['category'] ?? '');
    $priority         = trim($_POST['priority'] ?? '');
    $title            = trim($_POST['title'] ?? '');
    $currentCondition = trim($_POST['current_condition'] ?? '');
    $requestedChange  = trim($_POST['requested_change'] ?? '');
    $reason           = trim($_POST['reason'] ?? '');
    $action           = $_POST['action'] ?? 'draft';


    /*
    |--------------------------------------------------------------------------
    | Validasi
    |--------------------------------------------------------------------------
    */

    if ($changeType === '') {
        $errors[] = 'Tipe perubahan wajib dipilih.';
    }

    if ($category === '') {
        $errors[] = 'Kategori wajib dipilih.';
    }

    if (!in_array($priority, ['high', 'standard', 'low'], true)) {
        $errors[] = 'Prioritas tidak valid.';
    }

    if ($title === '') {
        $errors[] = 'Judul perubahan wajib diisi.';
    }

    if ($currentCondition === '') {
        $errors[] = 'Kondisi saat ini wajib diisi.';
    }

    if ($requestedChange === '') {
        $errors[] = 'Perubahan yang diminta wajib diisi.';
    }

    if ($reason === '') {
        $errors[] = 'Alasan/tujuan wajib diisi.';
    }


    /*
    |--------------------------------------------------------------------------
    | Tentukan status
    |--------------------------------------------------------------------------
    */

    if ($action === 'submit') {
        $status = 'submitted';
    } else {
        $status = 'draft';
    }


    /*
    |--------------------------------------------------------------------------
    | Simpan ke database
    |--------------------------------------------------------------------------
    */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Buat nomor CRF
            |--------------------------------------------------------------------------
            */

            $year = date('Y');

            $sqlLast = "
                SELECT request_number
                FROM change_requests
                WHERE request_number LIKE :prefix
                ORDER BY id DESC
                LIMIT 1
            ";

            $stmtLast = $pdo->prepare($sqlLast);
            $stmtLast->execute([
                ':prefix' => "CRF-$year-%"
            ]);

            $lastCrf = $stmtLast->fetchColumn();

            if ($lastCrf) {

                $lastNumber = (int) substr($lastCrf, -4);
                $nextNumber = $lastNumber + 1;

            } else {

                $nextNumber = 1;

            }

            $requestNumber = 'CRF-' . $year . '-' . str_pad(
                $nextNumber,
                4,
                '0',
                STR_PAD_LEFT
            );


            /*
            |--------------------------------------------------------------------------
            | Tanggal pengajuan
            |--------------------------------------------------------------------------
            */

            if ($status === 'submitted') {
                $submissionDate = date('Y-m-d H:i:s');
            } else {
                $submissionDate = null;
            }


            /*
            |--------------------------------------------------------------------------
            | Insert Change Request
            |--------------------------------------------------------------------------
            */

            $sqlInsert = "
                INSERT INTO change_requests (
                    request_number,
                    user_id,
                    divisi_id,
                    submission_date,
                    change_type,
                    category,
                    priority,
                    title,
                    current_condition,
                    requested_change,
                    reason,
                    status
                )
                VALUES (
                    :request_number,
                    :user_id,
                    :divisi_id,
                    :submission_date,
                    :change_type,
                    :category,
                    :priority,
                    :title,
                    :current_condition,
                    :requested_change,
                    :reason,
                    :status
                )
            ";

            $stmtInsert = $pdo->prepare($sqlInsert);

            $stmtInsert->execute([
                ':request_number'  => $requestNumber,
                ':user_id'         => $userId,
                ':divisi_id'       => $user['divisi_id'],
                ':submission_date' => $submissionDate,
                ':change_type'     => $changeType,
                ':category'        => $category,
                ':priority'        => $priority,
                ':title'           => $title,
                ':current_condition' => $currentCondition,
                ':requested_change'  => $requestedChange,
                ':reason'            => $reason,
                ':status'            => $status
            ]);


            /*
            |--------------------------------------------------------------------------
            | Ambil ID CRF yang baru dibuat
            |--------------------------------------------------------------------------
            */

            $changeRequestId = $pdo->lastInsertId();


            /*
            |--------------------------------------------------------------------------
            | Simpan activity log
            |--------------------------------------------------------------------------
            */

            if ($status === 'submitted') {

                $actionName  = 'submitted';
                $description = 'CRF diajukan oleh requester.';

            } else {

                $actionName  = 'created';
                $description = 'CRF dibuat sebagai draft.';

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
                ':change_request_id' => $changeRequestId,
                ':user_id'           => $userId,
                ':action'            => $actionName,
                ':description'       => $description
            ]);


            $pdo->commit();


            /*
            |--------------------------------------------------------------------------
            | Setelah berhasil
            |--------------------------------------------------------------------------
            */

            header(
                'Location: detail.php?id=' . $changeRequestId
            );

            exit;

        } catch (PDOException $e) {

            $pdo->rollBack();

            $errors[] = 'Gagal menyimpan CRF: ' . $e->getMessage();
        }
    }
}

$pageTitle  = 'Buat CRF';
$activeMenu = 'crf';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<main class="col-md-10 ms-sm-auto px-md-4 py-4">

    <h2 class="mb-3">Buat Change Request</h2>

    <p>
        <a href="index.php" class="text-decoration-none">&larr; Kembali ke Daftar CRF</a>
    </p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <strong>Terdapat kesalahan:</strong>
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST">

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>1. Informasi Pengajuan</strong></div>
            <div class="card-body">
                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">No. CRF</label>
                        <input type="text" class="form-control" value="Otomatis saat disimpan" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tanggal Pengajuan</label>
                        <input type="text" class="form-control" value="Otomatis saat CRF diajukan" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Requester / Pengaju</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Divisi</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($user['divisi_name'] ?? '-') ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                    </div>

                    <div class="col-md-6">
                        <label for="priority" class="form-label">Prioritas</label>
                        <select name="priority" id="priority" class="form-select" required>
                            <option value="">-- Pilih Prioritas --</option>
                            <option value="high" <?= (($_POST['priority'] ?? '') === 'high') ? 'selected' : '' ?>>High</option>
                            <option value="standard" <?= (($_POST['priority'] ?? '') === 'standard') ? 'selected' : '' ?>>Standard</option>
                            <option value="low" <?= (($_POST['priority'] ?? '') === 'low') ? 'selected' : '' ?>>Low</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="change_type" class="form-label">Tipe Perubahan</label>
                        <select name="change_type" id="change_type" class="form-select" required>
                            <option value="">-- Pilih Tipe Perubahan --</option>
                            <?php foreach ($changeTypes as $type): ?>
                                <option value="<?= htmlspecialchars($type) ?>"
                                    <?= (($_POST['change_type'] ?? '') === $type) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="category" class="form-label">Kategori</label>
                        <select name="category" id="category" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($categories as $categoryOption): ?>
                                <option value="<?= htmlspecialchars($categoryOption) ?>"
                                    <?= (($_POST['category'] ?? '') === $categoryOption) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($categoryOption) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="title" class="form-label">Judul Perubahan</label>
                        <input type="text" name="title" id="title" class="form-control" maxlength="255"
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                    </div>

                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white"><strong>2. Detail Perubahan</strong></div>
            <div class="card-body">

                <div class="mb-3">
                    <label for="current_condition" class="form-label">Kondisi Saat Ini</label>
                    <textarea name="current_condition" id="current_condition" class="form-control" rows="4"
                        required><?= htmlspecialchars($_POST['current_condition'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="requested_change" class="form-label">Perubahan yang Diminta</label>
                    <textarea name="requested_change" id="requested_change" class="form-control" rows="4"
                        required><?= htmlspecialchars($_POST['requested_change'] ?? '') ?></textarea>
                </div>

                <div class="mb-0">
                    <label for="reason" class="form-label">Alasan / Tujuan</label>
                    <textarea name="reason" id="reason" class="form-control" rows="4"
                        required><?= htmlspecialchars($_POST['reason'] ?? '') ?></textarea>
                </div>

            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" name="action" value="draft" class="btn btn-secondary">
                <i class="bi bi-save"></i> Simpan Draft
            </button>
            <button type="submit" name="action" value="submit" class="btn btn-primary">
                <i class="bi bi-send"></i> Ajukan CRF
            </button>
        </div>

    </form>

</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
