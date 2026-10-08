<?php
/**
 * Navigation Sidebar & Topbar
 * Warehouse Management System
 */

declare(strict_types=1);

$user = current_user();
$isAdmin = has_role('admin');
$currentUri = $_SERVER['REQUEST_URI'] ?? '';

// Helper to determine if link is active
function is_nav_active(string $path, string $currentUri): string
{
    return str_contains($currentUri, $path) ? 'active' : '';
}
?>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                <line x1="12" y1="22.08" x2="12" y2="12"></line>
            </svg>
        </div>
        <div class="brand-text">
            <span class="brand-title">WMS Portal</span>
            <span class="brand-subtitle"><?= $isAdmin ? 'Admin Console' : 'Staff Portal' ?></span>
        </div>
    </div>

    <nav class="sidebar-menu">
        <div class="menu-label">Main Navigation</div>

        <?php if ($isAdmin): ?>
            <a href="<?= url('admin/dashboard.php') ?>" class="nav-item <?= is_nav_active('admin/dashboard.php', $currentUri) ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </span>
                <span class="nav-text">Dashboard</span>
            </a>

            <a href="<?= url('admin/items.php') ?>" class="nav-item <?= is_nav_active('admin/items.php', $currentUri) ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </span>
                <span class="nav-text">Items Management</span>
            </a>

            <a href="<?= url('admin/movements.php') ?>" class="nav-item <?= is_nav_active('admin/movements.php', $currentUri) ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path></svg>
                </span>
                <span class="nav-text">Stock Movements</span>
            </a>

            <a href="<?= url('admin/reports.php') ?>" class="nav-item <?= is_nav_active('admin/reports.php', $currentUri) ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                </span>
                <span class="nav-text">Stock Reports</span>
            </a>

            <div class="menu-label">System Control</div>

            <a href="<?= url('admin/users.php') ?>" class="nav-item <?= is_nav_active('admin/users.php', $currentUri) ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                </span>
                <span class="nav-text">User Management</span>
            </a>

        <?php else: ?>
            <a href="<?= url('user/dashboard.php') ?>" class="nav-item <?= is_nav_active('user/dashboard.php', $currentUri) ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                </span>
                <span class="nav-text">Dashboard</span>
            </a>

            <a href="<?= url('user/items.php') ?>" class="nav-item <?= is_nav_active('user/items.php', $currentUri) || is_nav_active('user/item-details.php', $currentUri) ?>">
                <span class="nav-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                </span>
                <span class="nav-text">Browse Items</span>
            </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="user-profile">
            <div class="user-avatar"><?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?></div>
            <div class="user-info">
                <span class="user-name"><?= e($user['full_name'] ?? 'User') ?></span>
                <span class="user-role badge-role-<?= strtolower($user['role'] ?? 'user') ?>"><?= strtoupper(e($user['role'] ?? 'user')) ?></span>
            </div>
        </div>
        <a href="<?= url('logout.php') ?>" class="btn-logout" title="Sign Out">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
        </a>
    </div>
</aside>
