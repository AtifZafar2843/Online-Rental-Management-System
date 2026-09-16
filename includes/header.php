<?php
/**
 * Online Rental Management System (ORMS)
 * Header Template — Brand System Integration
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'ORMS — Peer-to-Peer Rental Marketplace';
?>
<!DOCTYPE html>
<html lang="en" class="h-full scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- Favicon Suite -->
    <link rel="icon" type="image/x-icon" href="<?= base_url('favicon/favicon.ico') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= base_url('favicon/favicon-32x32.png') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= base_url('favicon/favicon-16x16.png') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= base_url('favicon/apple-touch-icon.png') ?>">
    <link rel="manifest" href="<?= base_url('favicon/site.webmanifest') ?>">

    <!-- Google Fonts: Space Grotesk (Headlines) & Inter (Body/UI) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Remix Icon CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css">

    <!-- Tailwind CSS CDN & Brand Configuration -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        coral: {
                            DEFAULT: '#FF5A5F',
                            50: '#fff1f2',
                            100: '#ffe4e6',
                            200: '#fecdd3',
                            400: '#ff7175',
                            500: '#FF5A5F',
                            600: '#e04e53',
                            700: '#c53b40',
                        },
                        midnight: {
                            DEFAULT: '#0B1020',
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            800: '#141c38',
                            900: '#0B1020',
                            950: '#060913',
                        },
                        ivory: {
                            DEFAULT: '#FFF9F2',
                            50: '#ffffff',
                            100: '#FFF9F2',
                            200: '#faedd9',
                            300: '#f4dec0',
                        },
                        lilac: {
                            DEFAULT: '#E9E7FF',
                            50: '#fbfaff',
                            100: '#f4f3ff',
                            200: '#E9E7FF',
                            300: '#d5d1ff',
                        },
                        status: {
                            success: '#22C55E',
                            warning: '#F59E0B',
                            error: '#EF4444',
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
                        display: ['"Space Grotesk"', 'sans-serif'],
                    },
                    boxShadow: {
                        'soft': '0 4px 20px -2px rgba(11, 16, 32, 0.06), 0 2px 6px -1px rgba(11, 16, 32, 0.04)',
                        'glow-coral': '0 10px 25px -3px rgba(255, 90, 95, 0.25)',
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3, .font-display { font-family: 'Space Grotesk', sans-serif; letter-spacing: -0.02em; }
        .tracking-h1 { letter-spacing: -0.02em; }
        .tracking-h2 { letter-spacing: -0.015em; }
        .tracking-h3 { letter-spacing: -0.01em; }
    </style>
</head>
<body class="min-h-full flex flex-col bg-[#FAF8F5] text-midnight antialiased selection:bg-coral selection:text-white">

    <?php require_once __DIR__ . '/navbar.php'; ?>

    <!-- Flash Notification Messages Container -->
    <?php 
    $flashMessages = get_flash(); 
    if (!empty($flashMessages)): 
    ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full space-y-2 z-40">
        <?php foreach ($flashMessages as $flash): 
            $type = $flash['type'];
            $borderCol = 'border-blue-200 bg-blue-50 text-blue-900';
            $iconClass = 'ri-information-fill text-blue-500';

            if ($type === 'success') {
                $borderCol = 'border-emerald-200 bg-emerald-50 text-emerald-900';
                $iconClass = 'ri-checkbox-circle-fill text-emerald-500';
            } elseif ($type === 'error') {
                $borderCol = 'border-rose-200 bg-rose-50 text-rose-900';
                $iconClass = 'ri-error-warning-fill text-rose-500';
            } elseif ($type === 'warning') {
                $borderCol = 'border-amber-200 bg-amber-50 text-amber-900';
                $iconClass = 'ri-alert-fill text-amber-500';
            }
        ?>
        <div class="flex items-center justify-between p-4 rounded-2xl border <?= $borderCol ?> shadow-soft transition duration-200">
            <div class="flex items-center space-x-3">
                <i class="<?= $iconClass ?> text-xl"></i>
                <p class="text-sm font-medium leading-relaxed"><?= htmlspecialchars($flash['message']) ?></p>
            </div>
            <button onclick="this.parentElement.remove()" class="text-stone-400 hover:text-stone-600 text-lg px-2 leading-none">&times;</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Content Body Wrapper -->
    <main class="flex-grow">
