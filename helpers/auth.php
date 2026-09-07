<?php
/**
 * helpers/auth.php
 *
 * Kumpulan fungsi untuk mengecek status login dan role user.
 * Dipanggil di hampir semua halaman internal (dashboard, crf, dll)
 * supaya kita tidak menulis ulang kode cek session di setiap file.
 */

/**
 * Pastikan session PHP sudah aktif.
 * Aman dipanggil berkali-kali (tidak akan error walau session sudah start).
 */
function startSessionIfNeeded()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Wajibkan user sudah login sebelum melihat halaman ini.
 * Jika belum login, user diarahkan ke halaman login.
 */
function requireLogin()
{
    startSessionIfNeeded();

    if (!isset($_SESSION['user_id'])) {
        header('Location: /crf-ppu/pages/auth/login.php');
        exit;
    }
}

/**
 * Wajibkan user memiliki salah satu role tertentu.
 * Panggil SETELAH requireLogin().
 *
 * Contoh pemakaian:
 *   requireRole(['admin']);
 *   requireRole(['admin', 'approver']);
 *
 * @param array $allowedRoles
 */
function requireRole(array $allowedRoles)
{
    $role = $_SESSION['user_role'] ?? null;

    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        die('Anda tidak memiliki akses ke halaman ini.');
    }
}
