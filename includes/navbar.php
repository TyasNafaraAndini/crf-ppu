<?php
/**
 * includes/navbar.php
 *
 * Navbar atas, tampil di semua halaman internal.
 * Membutuhkan $_SESSION['user_name'] dan $_SESSION['user_role'].
 */
?>
<nav class="navbar navbar-dark bg-dark px-3">
    <span class="navbar-brand mb-0 h1">
        <i class="bi bi-arrow-left-right"></i>
        CRF PPU
    </span>

    <div class="d-flex align-items-center text-light">
        <span class="me-3">
            <?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
            <span class="badge bg-secondary text-uppercase">
                <?= htmlspecialchars($_SESSION['user_role'] ?? '') ?>
            </span>
        </span>

        <a href="/crf-ppu/logout.php" class="btn btn-sm btn-outline-light">
            <i class="bi bi-box-arrow-right"></i>
            Logout
        </a>
    </div>
</nav>
