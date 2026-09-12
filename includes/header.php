<?php
/**
 * Online Rental Management System (ORMS)
 * Header Template
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'ORMS — Online Rental Management System';
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8'
                        }
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="min-h-full flex flex-col bg-slate-950 text-slate-100 antialiased selection:bg-blue-600 selection:text-white">

    <?php require_once __DIR__ . '/navbar.php'; ?>

    <!-- Flash Notification Messages Container -->
    <?php 
    $flashMessages = get_flash(); 
    if (!empty($flashMessages)): 
    ?>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full space-y-2">
        <?php foreach ($flashMessages as $flash): 
            $type = $flash['type'];
            $bgColor = 'bg-blue-900/40 border-blue-600/60 text-blue-200';
            $icon = 'ℹ️';

            if ($type === 'success') {
                $bgColor = 'bg-emerald-950/70 border-emerald-500/60 text-emerald-200';
                $icon = '✅';
            } elseif ($type === 'error') {
                $bgColor = 'bg-rose-950/70 border-rose-500/60 text-rose-200';
                $icon = '⚠️';
            } elseif ($type === 'warning') {
                $bgColor = 'bg-amber-950/70 border-amber-500/60 text-amber-200';
                $icon = '⚡';
            }
        ?>
        <div class="flex items-center justify-between p-4 rounded-xl border <?= $bgColor ?> backdrop-blur-sm shadow-lg transition duration-200">
            <div class="flex items-center space-x-3">
                <span class="text-xl"><?= $icon ?></span>
                <p class="text-sm font-medium"><?= htmlspecialchars($flash['message']) ?></p>
            </div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white text-sm px-2">&times;</button>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Main Content Body Wrapper -->
    <main class="flex-grow">
