<?php
/**
 * helpers/crf.php
 *
 * Fungsi bantu terkait tampilan data Change Request (CRF).
 * Pemetaan warna badge mengikuti spesifikasi bagian 29 - STATUS BADGE.
 */

/**
 * Kembalikan class badge Bootstrap sesuai status CRF.
 */
function statusBadgeClass(string $status): string
{
    switch ($status) {
        case 'draft':
            return 'bg-secondary';
        case 'submitted':
            return 'bg-warning text-dark';
        case 'approved':
            return 'bg-success';
        case 'rejected':
            return 'bg-danger';
        case 'in_progress':
            return 'bg-primary';
        case 'completed':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
}

/**
 * Label status yang enak dibaca user (bukan "in_progress" mentah).
 */
function statusLabel(string $status): string
{
    $labels = [
        'draft'       => 'Draft',
        'submitted'   => 'Submitted',
        'approved'    => 'Approved',
        'rejected'    => 'Rejected',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
    ];

    return $labels[$status] ?? ucfirst($status);
}

/**
 * Kembalikan class badge Bootstrap sesuai prioritas CRF.
 */
function priorityBadgeClass(string $priority): string
{
    switch ($priority) {
        case 'high':
            return 'bg-danger';
        case 'standard':
            return 'bg-warning text-dark';
        case 'low':
            return 'bg-success';
        default:
            return 'bg-secondary';
    }
}
