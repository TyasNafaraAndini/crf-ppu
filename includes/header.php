<?php
/**
 * includes/header.php
 *
 * Bagian atas HTML untuk semua halaman INTERNAL (yang sudah login).
 * Panggil requireLogin() dulu di halaman sebelum include file ini.
 *
 * Variabel opsional yang bisa di-set sebelum include:
 *   $pageTitle  -> judul tab browser (default "CRF PPU")
 */

$pageTitle = $pageTitle ?? 'CRF PPU';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - CRF PPU</title>

    <!-- Bootstrap 5 CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet"
    >

    <!-- CSS custom punya kita sendiri -->
    <link href="/crf-ppu/assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php require_once __DIR__ . '/navbar.php'; ?>

<div class="container-fluid">
    <div class="row">
