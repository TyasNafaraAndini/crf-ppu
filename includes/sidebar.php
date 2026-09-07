<?php
/**
 * includes/sidebar.php
 *
 * Menu navigasi sebelah kiri.
 * Set variabel $activeMenu di halaman SEBELUM include file ini,
 * supaya menu yang sedang aktif ter-highlight.
 *
 * Contoh: $activeMenu = 'dashboard';
 */

$activeMenu = $activeMenu ?? '';
?>
<nav class="col-md-2 d-md-block bg-dark sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">

            <li class="nav-item">
                <a class="nav-link text-white <?= $activeMenu === 'dashboard' ? 'active bg-primary' : '' ?>"
                   href="/crf-ppu/pages/dashboard/index.php">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link text-white <?= $activeMenu === 'crf' ? 'active bg-primary' : '' ?>"
                   href="/crf-ppu/pages/crf/index.php">
                    <i class="bi bi-file-earmark-text"></i>
                    Change Request
                </a>
            </li>

            <!--
                Menu "Approval", "Users", dan "Divisi" akan kita tambahkan
                di sini setelah halaman-halaman tersebut dibuat pada
                tahap pengembangan berikutnya (lihat bagian "Belum Selesai").
            -->

        </ul>
    </div>
</nav>
