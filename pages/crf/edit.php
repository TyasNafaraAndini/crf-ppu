<?php

require_once __DIR__ . '/../../helpers/auth.php';
requireLogin();
requireRole(['requester']);

require_once __DIR__ . '/../../config/database.php';

$userId = $_SESSION['user_id'];
$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    die('ID CRF tidak valid.');
}

/*
|--------------------------------------------------------------------------
| Ambil CRF milik requester yang masih draft
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
        u.name AS requester,
        u.email,
        d.name AS divisi
    FROM change_requests cr
    JOIN users u ON cr.user_id = u.id
    LEFT JOIN divisi d ON cr.divisi_id = d.id
    WHERE cr.id = :id
      AND cr.user_id = :user_id
      AND cr.status = 'draft'
    LIMIT 1
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':id' => $id,
    ':user_id' => $userId
]);

$crf = $stmt->fetch();

if (!$crf) {
    http_response_code(404);
    die('CRF tidak ditemukan atau tidak dapat diedit.');
}

/*
|--------------------------------------------------------------------------
| Data dropdown sementara
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
| Konfigurasi attachment
|--------------------------------------------------------------------------
*/

$allowedExtensions = [
    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'jpg',
    'png'
];

$maxFileSize = 5 * 1024 * 1024;

$errors = [];
$success = '';

/*
|--------------------------------------------------------------------------
| Proses form
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'update';

    /*
    |--------------------------------------------------------------------------
    | Upload attachment
    |--------------------------------------------------------------------------
    */

    if ($action === 'upload_attachment') {

        if (
            !isset($_FILES['attachments']) ||
            !is_array($_FILES['attachments']['name'])
        ) {
            $errors[] = 'Tidak ada file yang dipilih.';
        } else {

            try {

                $pdo->beginTransaction();

                $uploadDirectory = __DIR__ . '/../../uploads/crf';

                if (!is_dir($uploadDirectory)) {
                    mkdir($uploadDirectory, 0755, true);
                }

                foreach ($_FILES['attachments']['name'] as $index => $fileName) {

                    $fileError = $_FILES['attachments']['error'][$index] ?? UPLOAD_ERR_NO_FILE;

                    if ($fileError === UPLOAD_ERR_NO_FILE) {
                        continue;
                    }

                    if ($fileError !== UPLOAD_ERR_OK) {
                        throw new RuntimeException(
                            "Gagal mengupload file {$fileName}."
                        );
                    }

                    $tmpName = $_FILES['attachments']['tmp_name'][$index];
                    $fileSize = $_FILES['attachments']['size'][$index];
                    $extension = strtolower(
                        pathinfo($fileName, PATHINFO_EXTENSION)
                    );

                    if (!in_array($extension, $allowedExtensions, true)) {
                        throw new RuntimeException(
                            "Format file {$fileName} tidak diperbolehkan."
                        );
                    }

                    if ($fileSize > $maxFileSize) {
                        throw new RuntimeException(
                            "Ukuran file {$fileName} melebihi 5 MB."
                        );
                    }

                    $safeFileName = uniqid('crf_', true) . '.' . $extension;
                    $targetPath = $uploadDirectory . '/' . $safeFileName;

                    if (!move_uploaded_file($tmpName, $targetPath)) {
                        throw new RuntimeException(
                            "Gagal menyimpan file {$fileName}."
                        );
                    }

                    $relativePath = 'uploads/crf/' . $safeFileName;

                    $sqlAttachment = "
                        INSERT INTO attachments (
                            change_request_id,
                            file_name,
                            file_path,
                            file_size,
                            file_type,
                            uploaded_by
                        )
                        VALUES (
                            :change_request_id,
                            :file_name,
                            :file_path,
                            :file_size,
                            :file_type,
                            :uploaded_by
                        )
                    ";

                    $stmtAttachment = $pdo->prepare($sqlAttachment);

                    $stmtAttachment->execute([
                        ':change_request_id' => $id,
                        ':file_name' => $fileName,
                        ':file_path' => $relativePath,
                        ':file_size' => $fileSize,
                        ':file_type' => $extension,
                        ':uploaded_by' => $userId
                    ]);

                    $sqlAttachmentLog = "
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

                    $stmtAttachmentLog = $pdo->prepare($sqlAttachmentLog);

                    $stmtAttachmentLog->execute([
                        ':change_request_id' => $id,
                        ':user_id' => $userId,
                        ':action' => 'attachment_added',
                        ':description' => 'Lampiran "' . $fileName . '" ditambahkan.'
                    ]);
                }

                $pdo->commit();

                header('Location: edit.php?id=' . $id);
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = $e->getMessage();
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update data CRF
    |--------------------------------------------------------------------------
    */

    if ($action === 'update') {

        $changeType = trim($_POST['change_type'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $priority = trim($_POST['priority'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $currentCondition = trim($_POST['current_condition'] ?? '');
        $requestedChange = trim($_POST['requested_change'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        /*
        |--------------------------------------------------------------------------
        | Validasi
        |--------------------------------------------------------------------------
        */

        if (!in_array($changeType, $changeTypes, true)) {
            $errors[] = 'Tipe perubahan tidak valid.';
        }

        if (!in_array($category, $categories, true)) {
            $errors[] = 'Kategori tidak valid.';
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
            $errors[] = 'Alasan / tujuan wajib diisi.';
        }

        /*
        |--------------------------------------------------------------------------
        | Simpan perubahan
        |--------------------------------------------------------------------------
        */

        if (empty($errors)) {

            try {

                $pdo->beginTransaction();

                $sqlUpdate = "
                    UPDATE change_requests
                    SET
                        change_type = :change_type,
                        category = :category,
                        priority = :priority,
                        title = :title,
                        current_condition = :current_condition,
                        requested_change = :requested_change,
                        reason = :reason
                    WHERE id = :id
                      AND user_id = :user_id
                      AND status = 'draft'
                ";

                $stmtUpdate = $pdo->prepare($sqlUpdate);

                $stmtUpdate->execute([
                    ':change_type' => $changeType,
                    ':category' => $category,
                    ':priority' => $priority,
                    ':title' => $title,
                    ':current_condition' => $currentCondition,
                    ':requested_change' => $requestedChange,
                    ':reason' => $reason,
                    ':id' => $id,
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
                    ':user_id' => $userId,
                    ':action' => 'updated',
                    ':description' => 'CRF draft diperbarui oleh requester.'
                ]);

                $pdo->commit();

                header('Location: detail.php?id=' . $id);
                exit;

            } catch (Throwable $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $errors[] = 'Gagal memperbarui CRF.';
            }
        }
    }
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
    ':change_request_id' => $id
]);

$attachments = $stmtAttachments->fetchAll();

$pageTitle = 'Edit CRF';
$activeMenu = 'crf';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>

<main class="col-md-10 ms-sm-auto px-md-4 py-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Edit Change Request</h2>

        <a
            href="detail.php?id=<?= $crf['id'] ?>"
            class="btn btn-outline-secondary"
        >
            Kembali
        </a>
    </div>

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

    <form
        method="POST"
        enctype="multipart/form-data"
    >

        <!-- Informasi Pengajuan -->
        <div class="card mb-3">

            <div class="card-header bg-white">
                <strong>1. Informasi Pengajuan</strong>
            </div>

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-md-6">
                        <label class="form-label">No. CRF</label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?= htmlspecialchars($crf['request_number']) ?>"
                            readonly
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Tanggal Pengajuan</label>

                        <input
                            type="text"
                            class="form-control"
                            value="Belum diajukan"
                            readonly
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">
                            Requester / Pengaju
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?= htmlspecialchars($crf['requester']) ?>"
                            readonly
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Divisi</label>

                        <input
                            type="text"
                            class="form-control"
                            value="<?= htmlspecialchars($crf['divisi'] ?? '-') ?>"
                            readonly
                        >
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email</label>

                        <input
                            type="email"
                            class="form-control"
                            value="<?= htmlspecialchars($crf['email']) ?>"
                            readonly
                        >
                    </div>

                    <div class="col-md-6">
                        <label for="change_type" class="form-label">
                            Tipe Perubahan
                        </label>

                        <select
                            name="change_type"
                            id="change_type"
                            class="form-select"
                            required
                        >
                            <option value="">
                                -- Pilih Tipe Perubahan --
                            </option>

                            <?php foreach ($changeTypes as $type): ?>

                                <option
                                    value="<?= htmlspecialchars($type) ?>"
                                    <?= ($_POST['change_type'] ?? $crf['change_type']) === $type ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($type) ?>
                                </option>

                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="category" class="form-label">
                            Kategori
                        </label>

                        <select
                            name="category"
                            id="category"
                            class="form-select"
                            required
                        >
                            <option value="">
                                -- Pilih Kategori --
                            </option>

                            <?php foreach ($categories as $item): ?>

                                <option
                                    value="<?= htmlspecialchars($item) ?>"
                                    <?= ($_POST['category'] ?? $crf['category']) === $item ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($item) ?>
                                </option>

                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label for="priority" class="form-label">
                            Prioritas
                        </label>

                        <select
                            name="priority"
                            id="priority"
                            class="form-select"
                            required
                        >
                            <option value="">
                                -- Pilih Prioritas --
                            </option>

                            <option
                                value="high"
                                <?= ($_POST['priority'] ?? $crf['priority']) === 'high' ? 'selected' : '' ?>
                            >
                                High
                            </option>

                            <option
                                value="standard"
                                <?= ($_POST['priority'] ?? $crf['priority']) === 'standard' ? 'selected' : '' ?>
                            >
                                Standard
                            </option>

                            <option
                                value="low"
                                <?= ($_POST['priority'] ?? $crf['priority']) === 'low' ? 'selected' : '' ?>
                            >
                                Low
                            </option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="title" class="form-label">
                            Judul Perubahan
                        </label>

                        <input
                            type="text"
                            name="title"
                            id="title"
                            class="form-control"
                            maxlength="255"
                            value="<?= htmlspecialchars($_POST['title'] ?? $crf['title']) ?>"
                            required
                        >
                    </div>

                </div>

            </div>

        </div>

        <!-- Detail Perubahan -->
        <div class="card mb-3">

            <div class="card-header bg-white">
                <strong>2. Detail Perubahan</strong>
            </div>

            <div class="card-body">

                <div class="mb-3">
                    <label for="current_condition" class="form-label">
                        Kondisi Saat Ini
                    </label>

                    <textarea
                        name="current_condition"
                        id="current_condition"
                        class="form-control"
                        rows="5"
                        required
                    ><?= htmlspecialchars($_POST['current_condition'] ?? $crf['current_condition']) ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="requested_change" class="form-label">
                        Perubahan yang Diminta
                    </label>

                    <textarea
                        name="requested_change"
                        id="requested_change"
                        class="form-control"
                        rows="5"
                        required
                    ><?= htmlspecialchars($_POST['requested_change'] ?? $crf['requested_change']) ?></textarea>
                </div>

                <div>
                    <label for="reason" class="form-label">
                        Alasan / Tujuan
                    </label>

                    <textarea
                        name="reason"
                        id="reason"
                        class="form-control"
                        rows="5"
                        required
                    ><?= htmlspecialchars($_POST['reason'] ?? $crf['reason']) ?></textarea>
                </div>

            </div>

        </div>

        <!-- Lampiran -->
        <div class="card mb-3">

            <div class="card-header bg-white">
                <strong>3. Lampiran</strong>
            </div>

            <div class="card-body">

                <?php if (!empty($attachments)): ?>

                    <div class="table-responsive mb-3">

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
                                            <?= htmlspecialchars(
                                                strtoupper($attachment['file_type'])
                                            ) ?>
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

                    <p class="text-muted">
                        Belum ada lampiran.
                    </p>

                <?php endif; ?>

                <div class="border rounded p-3">

                    <label
                        for="attachments"
                        class="form-label"
                    >
                        Tambah Lampiran
                    </label>

                    <input
                        type="file"
                        name="attachments[]"
                        id="attachments"
                        class="form-control"
                        multiple
                        accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png"
                    >

                    <div class="form-text">
                        Format:
                        PDF, DOC, DOCX, XLS, XLSX, JPG, PNG.
                        Maksimal 5 MB per file.
                    </div>

                    <button
                        type="submit"
                        name="action"
                        value="upload_attachment"
                        class="btn btn-primary mt-3"
                    >
                        <i class="bi bi-upload"></i>
                        Upload Lampiran
                    </button>

                </div>

            </div>

        </div>

        <!-- Aksi -->
        <div class="d-flex gap-2">

            <button
                type="submit"
                name="action"
                value="update"
                class="btn btn-primary"
            >
                <i class="bi bi-save"></i>
                Simpan Perubahan
            </button>

            <a
                href="detail.php?id=<?= $crf['id'] ?>"
                class="btn btn-secondary"
            >
                Batal
            </a>

        </div>

    </form>

</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>