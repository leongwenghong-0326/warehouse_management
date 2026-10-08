<?php
/**
 * Layout Header
 * Warehouse Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/init.php';

// Enforce login on all pages including header by default unless specifically handled
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle ?? 'Dashboard') ?> - <?= APP_NAME ?></title>
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body class="app-body">
    <div class="app-layout">
        <?php require_once __DIR__ . '/nav.php'; ?>
        
        <main class="main-content" id="mainContent">
            <!-- Topbar Header inside main-content -->
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="btn-sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                    </button>
                    <h1 class="page-title"><?= e($pageTitle ?? 'Dashboard') ?></h1>
                </div>

                <div class="topbar-right">
                    <div class="header-user-status">
                        <span class="status-indicator"></span>
                        <span class="text-muted">Signed in as</span>
                        <strong><?= e(current_user()['username'] ?? '') ?></strong>
                    </div>
                    <a href="<?= url('logout.php') ?>" class="btn btn-outline-danger btn-sm">Sign Out</a>
                </div>
            </header>

            <div class="content-container">
                <!-- Flash Messages -->
                <?= display_flash() ?>
