<?php
/**
 * CodePulse.io - Admin Dashboard
 * Premium Dark-Theme Control Panel (Sidebar Edition)
 */

session_start();

$config_file = __DIR__ . '/app_config.json';
$app_config = [];

// Load config
if (file_exists($config_file)) {
    $app_config = json_decode(file_get_contents($config_file), true);
}

// Fallback defaults if config is corrupted or empty
if (empty($app_config)) {
    $app_config = [
        'site_title' => 'CodePulse - Premium Static Code Analyzer',
        'site_description' => 'Aplikasi analisis kompleksitas kode menggunakan metode Halstead Metrics & McCabe Complexity dengan dukungan upload file dan folder.',
        'qris_type' => 'text',
        'qris_text' => '',
        'admin_username' => 'admin',
        'admin_password_hash' => password_hash('admin123', PASSWORD_DEFAULT),
        'maintenance_mode' => false
    ];
    file_put_contents($config_file, json_encode($app_config, JSON_PRETTY_PRINT));
}

// Self-healing: if plain text password is set in config (doesn't start with $2y$), dynamically hash and save it
if (!empty($app_config['admin_password_hash']) && strpos($app_config['admin_password_hash'], '$2y$') !== 0) {
    $app_config['admin_password_hash'] = password_hash($app_config['admin_password_hash'], PASSWORD_DEFAULT);
    file_put_contents($config_file, json_encode($app_config, JSON_PRETTY_PRINT));
}

$error_msg = '';
$success_msg = '';

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle Login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === $app_config['admin_username'] && password_verify($password, $app_config['admin_password_hash'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        header('Location: admin.php');
        exit;
    } else {
        $error_msg = 'Username atau password salah!';
    }
}

$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Handle Admin Dashboard Action POST
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_general') {
        $app_config['site_title'] = trim($_POST['site_title'] ?? '');
        $app_config['site_description'] = trim($_POST['site_description'] ?? '');
        $app_config['maintenance_mode'] = isset($_POST['maintenance_mode']) && $_POST['maintenance_mode'] == '1';

        if (file_put_contents($config_file, json_encode($app_config, JSON_PRETTY_PRINT))) {
            $success_msg = 'Pengaturan umum berhasil diperbarui!';
        } else {
            $error_msg = 'Gagal menyimpan pengaturan umum.';
        }
    }

    if ($action === 'update_qris') {
        $new_type = $_POST['qris_type'] ?? 'text';
        $new_text = trim($_POST['qris_text'] ?? '');

        if ($new_type === 'text' && empty($new_text)) {
            $error_msg = 'Payload teks QRIS tidak boleh kosong jika memilih tipe Teks.';
        } else {
            $app_config['qris_type'] = $new_type;
            $app_config['qris_text'] = $new_text;

            // Handle QRIS Image upload
            $upload_ok = true;
            if ($new_type === 'image' && isset($_FILES['qris_file']) && $_FILES['qris_file']['error'] === 0) {
                $check = getimagesize($_FILES['qris_file']['tmp_name']);
                if ($check !== false) {
                    $target_file = __DIR__ . '/qris.png';
                    if (!move_uploaded_file($_FILES['qris_file']['tmp_name'], $target_file)) {
                        $error_msg = 'Gagal menyimpan gambar QRIS.';
                        $upload_ok = false;
                    }
                } else {
                    $error_msg = 'File yang diunggah bukan gambar yang valid.';
                    $upload_ok = false;
                }
            }

            if ($upload_ok) {
                if (file_put_contents($config_file, json_encode($app_config, JSON_PRETTY_PRINT))) {
                    $success_msg = 'Pengaturan QRIS berhasil diperbarui!';
                } else {
                    $error_msg = 'Gagal menyimpan pengaturan QRIS.';
                }
            }
        }
    }

    if ($action === 'update_security') {
        $new_username = trim($_POST['username'] ?? '');
        $current_pwd = $_POST['current_password'] ?? '';
        $new_pwd = $_POST['new_password'] ?? '';

        if (!password_verify($current_pwd, $app_config['admin_password_hash'])) {
            $error_msg = 'Password saat ini salah!';
        } else {
            if (!empty($new_username)) {
                $app_config['admin_username'] = $new_username;
                $_SESSION['admin_username'] = $new_username;
            }

            if (!empty($new_pwd)) {
                $app_config['admin_password_hash'] = password_hash($new_pwd, PASSWORD_DEFAULT);
            }

            if (file_put_contents($config_file, json_encode($app_config, JSON_PRETTY_PRINT))) {
                $success_msg = 'Kredensial keamanan berhasil diperbarui!';
            } else {
                $error_msg = 'Gagal menyimpan pengaturan keamanan.';
            }
        }
    }
}

// Stats gathering for overview
$stat_file_count = 0;
$stat_php_files = 0;
$stat_js_files = 0;
$stat_html_files = 0;

function scanDirRecursive($dir, &$stat_file_count, &$stat_php_files, &$stat_js_files, &$stat_html_files) {
    if (!is_dir($dir)) return;
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === '.git') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            scanDirRecursive($path, $stat_file_count, $stat_php_files, $stat_js_files, $stat_html_files);
        } else {
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['php', 'js', 'html'])) {
                $stat_file_count++;
                if ($ext === 'php') $stat_php_files++;
                if ($ext === 'js') $stat_js_files++;
                if ($ext === 'html') $stat_html_files++;
            }
        }
    }
}
scanDirRecursive(__DIR__, $stat_file_count, $stat_php_files, $stat_js_files, $stat_html_files);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CodePulse Admin Dashboard - Kontrol Panel Premium</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    }
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #080b11;
            background-image: 
                radial-gradient(at 0% 0%, rgba(220, 38, 38, 0.04) 0px, transparent 40%),
                radial-gradient(at 100% 100%, rgba(15, 23, 42, 0.4) 0px, transparent 50%);
            color: #f1f5f9;
        }
        .glass-card {
            background: rgba(13, 17, 28, 0.7);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .glow-sphere {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 1;
            pointer-events: none;
            opacity: 0.1;
        }
    </style>
</head>
<body class="min-h-screen relative selection:bg-red-600/40 selection:text-white">

    <!-- Decorative Glow Spheres -->
    <div class="glow-sphere w-[350px] h-[350px] bg-red-600 top-10 left-10"></div>
    <div class="glow-sphere w-[350px] h-[350px] bg-slate-900 bottom-10 right-10"></div>

    <?php if (!$is_logged_in): ?>
        <!-- ==================== VIEW: LOGIN ==================== -->
        <div class="min-h-screen flex items-center justify-center px-4 relative z-10">
            <div class="glass-card max-w-md w-full p-8 rounded-3xl border border-neutral-800/80 shadow-2xl space-y-6">
                
                <div class="text-center">
                    <div class="w-12 h-12 bg-gradient-to-br from-red-600 to-red-400 rounded-2xl flex items-center justify-center text-white font-mono font-bold text-lg shadow-md mx-auto mb-3">
                        [C•P]
                    </div>
                    <h1 class="text-xl font-extrabold tracking-tight text-white">Admin Login</h1>
                    <p class="text-xs text-neutral-500 mt-1 leading-relaxed">
                        Masukkan kredensial admin CodePulse Anda untuk mengelola konfigurasi aplikasi.
                    </p>
                </div>

                <div class="h-[1px] bg-neutral-800/60 my-2"></div>

                <?php if (!empty($error_msg)): ?>
                    <div class="p-3.5 bg-rose-500/10 border border-rose-500/20 rounded-xl text-xs text-rose-400 flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span><?php echo $error_msg; ?></span>
                    </div>
                <?php endif; ?>

                <form action="" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="space-y-1">
                        <label class="block text-[10px] font-bold text-neutral-450 uppercase tracking-wider">Kredensial Admin</label>
                        <div class="border border-neutral-850 rounded-2xl overflow-hidden bg-neutral-950/80 divide-y divide-neutral-850/80 focus-within:border-red-500/80 focus-within:ring-1 focus-within:ring-red-500/20 transition-all">
                            <!-- Username Field -->
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-neutral-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </span>
                                <input type="text" name="username" required placeholder="Username" autocomplete="username" class="w-full bg-transparent border-0 pl-10 pr-4 py-3 text-sm text-white placeholder-neutral-600 focus:outline-none focus:ring-0">
                            </div>
                            
                            <!-- Password Field -->
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-neutral-500">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path>
                                    </svg>
                                </span>
                                <input type="password" name="password" id="login_password" required placeholder="Password" autocomplete="current-password" class="w-full bg-transparent border-0 pl-10 pr-10 py-3 text-sm text-white placeholder-neutral-600 focus:outline-none focus:ring-0">
                                <button type="button" onclick="togglePasswordVisibility('login_password', 'eye_icon')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-neutral-550 hover:text-neutral-300 transition select-none">
                                    <span id="eye_icon" class="flex items-center">
                                        <!-- Eye icon SVG -->
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 bg-neutral-900/40 border border-neutral-800 rounded-xl text-[10px] text-neutral-400 space-y-1">
                        <span class="font-bold text-neutral-300 block">Kredensial Default:</span>
                        <div class="flex justify-between">
                            <span>Username: <code class="font-mono text-red-400">admin</code></span>
                            <span>Password: <code class="font-mono text-red-400">admin123</code></span>
                        </div>
                    </div>

                    <button type="submit" class="w-full py-2.5 bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 text-white font-bold rounded-xl text-sm transition mt-2 shadow-md active:scale-98">Masuk ke Dashboard</button>
                </form>

                <div class="text-center pt-2">
                    <a href="index.php" class="text-xs text-neutral-500 hover:text-white transition">← Kembali ke Halaman Utama</a>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- ==================== VIEW: ADMIN DASHBOARD (SIDEBAR) ==================== -->
        
        <!-- Mobile Navigation Header -->
        <div class="lg:hidden flex items-center justify-between px-6 h-16 bg-[#0a0d14] border-b border-neutral-800 z-40 relative">
            <div class="flex items-center space-x-2.5">
                <div class="w-7 h-7 bg-gradient-to-br from-red-600 to-red-400 rounded-lg flex items-center justify-center text-white font-mono font-bold text-xs">
                    [C•P]
                </div>
                <span class="text-sm font-bold text-white tracking-tight">CodePulse.io Admin</span>
            </div>
            <button onclick="toggleMobileMenu()" class="text-neutral-400 hover:text-white focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
                </svg>
            </button>
        </div>

        <!-- Sticky Sidebar Navigation Container -->
        <aside id="sidebar-container" class="fixed top-0 left-0 h-screen w-64 bg-[#0a0d14] border-r border-neutral-800 flex flex-col z-30 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
            <!-- Sidebar Header -->
            <div class="p-6 border-b border-neutral-800/80 flex items-center space-x-2.5">
                <div class="w-8 h-8 bg-gradient-to-br from-red-600 to-red-400 rounded-lg flex items-center justify-center text-white font-mono font-bold text-xs shadow-md shadow-red-600/10">
                    [C•P]
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-bold text-white tracking-tight leading-none">CodePulse<span class="text-red-500 font-light">.io</span></span>
                    <span class="text-[9px] uppercase tracking-widest text-red-550 mt-1 font-bold">Admin Console</span>
                </div>
            </div>

            <!-- Sidebar Menu Items -->
            <nav class="flex-1 px-4 py-6 space-y-1.5 overflow-y-auto">
                <button onclick="switchTab('overview')" id="tab-overview" class="w-full px-4 py-3 rounded-xl text-xs font-semibold hover:bg-neutral-900/60 hover:text-white transition flex items-center gap-3 text-white bg-red-650/10 border-l-2 border-red-500">
                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    <span>Overview</span>
                </button>
                <button onclick="switchTab('qris')" id="tab-qris" class="w-full px-4 py-3 rounded-xl text-xs font-semibold hover:bg-neutral-900/60 hover:text-white transition flex items-center gap-3 text-neutral-400">
                    <svg class="w-4 h-4 text-neutral-450" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span>Pengaturan QRIS</span>
                </button>
                <button onclick="switchTab('general')" id="tab-general" class="w-full px-4 py-3 rounded-xl text-xs font-semibold hover:bg-neutral-900/60 hover:text-white transition flex items-center gap-3 text-neutral-400">
                    <svg class="w-4 h-4 text-neutral-455" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    <span>Pengaturan Umum</span>
                </button>
                <button onclick="switchTab('security')" id="tab-security" class="w-full px-4 py-3 rounded-xl text-xs font-semibold hover:bg-neutral-900/60 hover:text-white transition flex items-center gap-3 text-neutral-400">
                    <svg class="w-4 h-4 text-neutral-450" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                    </svg>
                    <span>Keamanan Kredensial</span>
                </button>
            </nav>

            <!-- Sidebar Footer User Profile Info -->
            <div class="p-4 border-t border-neutral-800 bg-[#07090f]/60 text-xs">
                <div class="flex items-center space-x-3">
                    <div class="w-7 h-7 rounded-full bg-red-600/10 border border-red-500/25 flex items-center justify-center text-red-500 font-bold text-xs">
                        A
                    </div>
                    <div class="flex-1 truncate">
                        <p class="font-bold text-white text-[11px] leading-tight truncate"><?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
                        <span class="text-[9px] text-neutral-500">Administrator</span>
                    </div>
                    <a href="admin.php?action=logout" class="text-neutral-500 hover:text-rose-455 transition" title="Log Out">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Workspace (Desktop shifts right by sidebar width) -->
        <div class="lg:pl-64 flex flex-col min-h-screen">
            
            <!-- Top Dashboard Header Bar -->
            <header class="h-16 px-6 lg:px-10 border-b border-neutral-800/80 bg-[#0a0d14]/40 backdrop-blur flex items-center justify-between sticky top-0 z-20">
                <h2 class="text-xs font-semibold text-neutral-400 uppercase tracking-widest" id="dashboard-current-title">Dashboard Overview</h2>
                <div class="flex items-center space-x-4">
                    <div class="hidden sm:flex items-center text-[10px] text-neutral-500 bg-neutral-900 border border-neutral-800 px-3 py-1 rounded-full gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block animate-pulse"></span>
                        <span>Server Online</span>
                    </div>
                    <a href="index.php" target="_blank" class="px-3 py-1.5 bg-neutral-900 border border-neutral-800 hover:bg-slate-800 text-neutral-300 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        <span>Lihat Web</span>
                    </a>
                </div>
            </header>

            <main class="flex-1 p-6 lg:p-10 max-w-[1200px] w-full mx-auto space-y-6">
                
                <?php if (!empty($error_msg)): ?>
                    <div class="p-4 bg-rose-500/10 border border-rose-500/20 rounded-2xl text-xs text-rose-400 flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span><?php echo $error_msg; ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success_msg)): ?>
                    <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 rounded-2xl text-xs text-emerald-450 flex items-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><?php echo $success_msg; ?></span>
                    </div>
                <?php endif; ?>

                <!-- ==================== TAB: OVERVIEW ==================== -->
                <div id="content-overview" class="tab-content space-y-6">
                    <div class="glass-card p-6 rounded-3xl border border-neutral-800/80 space-y-4">
                        <h2 class="text-lg font-extrabold text-white">Selamat Datang, Admin!</h2>
                        <p class="text-xs text-neutral-450 leading-relaxed">
                            Melalui panel kontrol ini, Anda dapat mengelola aspek utama dari aplikasi static code analyzer CodePulse.io seperti metadata halaman, konfigurasi donasi QRIS, dan status pemeliharaan sistem.
                        </p>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="glass-card p-5 rounded-2xl border border-neutral-800/80 flex flex-col justify-between min-h-[105px]">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider">Jumlah File</span>
                                <span class="p-1.5 bg-neutral-900 border border-neutral-800 rounded-lg text-neutral-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                                </span>
                            </div>
                            <span class="text-3xl font-extrabold text-white mt-1"><?php echo $stat_file_count; ?></span>
                            <span class="text-[9px] text-neutral-500 mt-1"><?php echo "$stat_php_files PHP | $stat_js_files JS | $stat_html_files HTML"; ?></span>
                        </div>
                        
                        <div class="glass-card p-5 rounded-2xl border border-neutral-800/80 flex flex-col justify-between min-h-[105px]">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider">Tipe QRIS Aktif</span>
                                <span class="p-1.5 bg-neutral-900 border border-neutral-800 rounded-lg text-neutral-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                </span>
                            </div>
                            <span class="text-xl font-extrabold text-white mt-1.5 capitalize"><?php echo htmlspecialchars($app_config['qris_type'] === 'image' ? 'File Gambar' : 'Payload Teks'); ?></span>
                            <span class="text-[9px] text-neutral-500 mt-1 truncate max-w-[200px]">
                                <?php echo $app_config['qris_type'] === 'text' ? substr(htmlspecialchars($app_config['qris_text']), 0, 24) . '...' : 'qris.png'; ?>
                            </span>
                        </div>

                        <div class="glass-card p-5 rounded-2xl border border-neutral-800/80 flex flex-col justify-between min-h-[105px]">
                            <div class="flex justify-between items-center">
                                <span class="text-[10px] font-bold text-neutral-500 uppercase tracking-wider">Status Mode Web</span>
                                <span class="p-1.5 bg-neutral-900 border border-neutral-800 rounded-lg text-neutral-400">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                </span>
                            </div>
                            <span class="text-xl font-extrabold text-white mt-1.5"><?php echo $app_config['maintenance_mode'] ? 'Pemeliharaan' : 'Siap Pakai'; ?></span>
                            <span class="text-[9px] text-neutral-500 mt-1">Dapat diubah di tab Pengaturan Umum</span>
                        </div>
                    </div>
                </div>

                <!-- ==================== TAB: QRIS CONFIGURATION ==================== -->
                <div id="content-qris" class="tab-content hidden space-y-6">
                    <div class="glass-card p-6 rounded-3xl border border-neutral-800/80 space-y-6">
                        <div>
                            <h3 class="text-base font-bold text-white">Pengaturan QRIS Donasi</h3>
                            <p class="text-xs text-neutral-500 mt-1">Pilih metode penyediaan QRIS Anda untuk donasi publik.</p>
                        </div>

                        <div class="h-[1px] bg-neutral-800/60 my-4"></div>

                        <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                            <input type="hidden" name="action" value="update_qris">

                            <div class="space-y-2">
                                <label class="block text-xs font-semibold text-neutral-350">Tipe Kode QRIS</label>
                                <select name="qris_type" id="qris_type_select_admin" onchange="toggleQrisFieldsAdmin()" class="w-full md:w-80 bg-neutral-900 border border-neutral-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-red-500">
                                    <option value="image" <?php echo $app_config['qris_type'] === 'image' ? 'selected' : ''; ?>>File Gambar (Upload qris.png)</option>
                                    <option value="text" <?php echo $app_config['qris_type'] === 'text' ? 'selected' : ''; ?>>Teks Payload QRIS (Dibuat Dinamis)</option>
                                </select>
                            </div>

                            <div id="qris_file_field_admin" class="space-y-2 <?php echo $app_config['qris_type'] === 'text' ? 'hidden' : ''; ?>">
                                <label class="block text-xs font-semibold text-neutral-350">File Gambar QRIS</label>
                                <input type="file" name="qris_file" accept="image/*" class="w-full text-xs text-neutral-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-neutral-800 file:text-white hover:file:bg-neutral-700">
                                <p class="text-[10px] text-neutral-550 leading-relaxed">Upload QRIS gambar Anda. File akan otomatis tersimpan sebagai `qris.png` di direktori utama.</p>
                                <?php if (file_exists(__DIR__ . '/qris.png')): ?>
                                    <div class="mt-4 p-2 bg-neutral-900 border border-neutral-850 rounded-xl w-32 h-32 flex items-center justify-center overflow-hidden">
                                        <img src="qris.png?t=<?php echo time(); ?>" alt="QRIS Aktif" class="w-full h-full object-contain">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div id="qris_text_field_admin" class="space-y-2 <?php echo $app_config['qris_type'] === 'image' ? 'hidden' : ''; ?>">
                                <label class="block text-xs font-semibold text-neutral-350">Payload Teks QRIS (EMVCo)</label>
                                <textarea name="qris_text" placeholder="00020101021138570016ID.CO.QRIS.WWW0215..." rows="5" class="w-full bg-neutral-900 border border-neutral-800 rounded-xl px-4 py-2.5 text-xs font-mono text-white focus:outline-none focus:border-red-500 leading-relaxed"><?php echo htmlspecialchars($app_config['qris_text']); ?></textarea>
                                <p class="text-[10px] text-neutral-550 leading-relaxed">Salin payload mentah dari barcode QRIS Anda. Kode QR akan otomatis digenerate dinamis menggunakan JS di halaman depan.</p>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="px-5 py-2 bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 text-white font-bold rounded-xl text-xs transition shadow-md active:scale-98">Simpan Konfigurasi QRIS</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ==================== TAB: GENERAL CONFIGURATION ==================== -->
                <div id="content-general" class="tab-content hidden space-y-6">
                    <div class="glass-card p-6 rounded-3xl border border-neutral-800/80 space-y-6">
                        <div>
                            <h3 class="text-base font-bold text-white">Pengaturan Umum Aplikasi</h3>
                            <p class="text-xs text-neutral-500 mt-1">Kelola data metadata publik serta status pemeliharaan sistem.</p>
                        </div>

                        <div class="h-[1px] bg-neutral-800/60 my-4"></div>

                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_general">

                            <div class="space-y-2">
                                <label class="block text-xs font-semibold text-neutral-350 font-sans">Judul Website (Title)</label>
                                <input type="text" name="site_title" required value="<?php echo htmlspecialchars($app_config['site_title']); ?>" class="w-full bg-neutral-900 border border-neutral-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-red-500">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-xs font-semibold text-neutral-350 font-sans">Deskripsi Metadata (Meta Description)</label>
                                <textarea name="site_description" required rows="4" class="w-full bg-neutral-900 border border-neutral-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-red-500 leading-relaxed"><?php echo htmlspecialchars($app_config['site_description']); ?></textarea>
                            </div>

                            <div class="space-y-2 pt-2">
                                <label class="block text-xs font-semibold text-neutral-350 font-sans mb-1">Status Mode Pemeliharaan (Maintenance Mode)</label>
                                <div class="flex items-center space-x-3">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="maintenance_mode" value="0" <?php echo !$app_config['maintenance_mode'] ? 'checked' : ''; ?> class="form-radio text-red-650 bg-neutral-900 border-neutral-800 focus:ring-0">
                                        <span class="ml-2 text-xs text-neutral-400">Nonaktif (Situs Dapat Diakses Publik)</span>
                                    </label>
                                </div>
                                <div class="flex items-center space-x-3 mt-2">
                                    <label class="inline-flex items-center cursor-pointer">
                                        <input type="radio" name="maintenance_mode" value="1" <?php echo $app_config['maintenance_mode'] ? 'checked' : ''; ?> class="form-radio text-red-650 bg-neutral-900 border-neutral-800 focus:ring-0">
                                        <span class="ml-2 text-xs text-neutral-400">Aktifkan (Kunci Situs dengan Halaman Pemeliharaan)</span>
                                    </label>
                                </div>
                                <p class="text-[10px] text-neutral-550 leading-relaxed mt-2">Jika diaktifkan, halaman utama analyzer tidak bisa diakses dan akan menampilkan visual pemeliharaan sistem.</p>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="px-5 py-2 bg-gradient-to-r from-red-650 to-red-550 hover:from-red-550 hover:to-red-450 text-white font-bold rounded-xl text-xs transition shadow-md active:scale-98">Simpan Pengaturan Umum</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ==================== TAB: SECURITY KREDENSIAL ==================== -->
                <div id="content-security" class="tab-content hidden space-y-6">
                    <div class="glass-card p-6 rounded-3xl border border-neutral-800/80 space-y-6">
                        <div>
                            <h3 class="text-base font-bold text-white">Ganti Kredensial Admin</h3>
                            <p class="text-xs text-neutral-500 mt-1">Ubah username dan password admin demi keamanan.</p>
                        </div>

                        <div class="h-[1px] bg-neutral-800/60 my-4"></div>

                        <form action="" method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_security">

                            <div class="space-y-2">
                                <label class="block text-xs font-semibold text-neutral-350 font-sans">Username Admin</label>
                                <input type="text" name="username" required value="<?php echo htmlspecialchars($app_config['admin_username']); ?>" class="w-full md:w-80 bg-neutral-900 border border-neutral-800 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-red-500">
                            </div>

                            <div class="space-y-2">
                                <label class="block text-xs font-semibold text-neutral-350 font-sans">Ubah Password Baru (Opsional)</label>
                                <div class="relative w-full md:w-80">
                                    <input type="password" name="new_password" id="new_password_input" placeholder="Biarkan kosong jika tidak diganti" class="w-full bg-neutral-900 border border-neutral-800 rounded-xl pl-4 pr-10 py-2.5 text-xs text-white focus:outline-none focus:border-red-500">
                                    <button type="button" onclick="togglePasswordVisibility('new_password_input', 'eye_new')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-neutral-500 hover:text-neutral-300 transition text-xs select-none">
                                        <span id="eye_new" class="flex items-center">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </span>
                                    </button>
                                </div>
                            </div>

                            <div class="h-[1px] bg-neutral-850 my-2"></div>

                            <div class="space-y-2">
                                <label class="block text-xs font-semibold text-neutral-350 font-sans">Password Saat Ini (Konfirmasi)</label>
                                <div class="relative w-full md:w-80">
                                    <input type="password" name="current_password" id="current_password_input" required placeholder="••••••••" class="w-full bg-neutral-900 border border-neutral-800 rounded-xl pl-4 pr-10 py-2.5 text-xs text-white focus:outline-none focus:border-red-500">
                                    <button type="button" onclick="togglePasswordVisibility('current_password_input', 'eye_current')" class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-neutral-500 hover:text-neutral-300 transition text-xs select-none">
                                        <span id="eye_current" class="flex items-center">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                        </span>
                                    </button>
                                </div>
                                <p class="text-[10px] text-neutral-550 leading-relaxed mt-1">Diperlukan untuk memvalidasi dan menyimpan perubahan username/password.</p>
                            </div>

                            <div class="pt-2">
                                <button type="submit" class="px-5 py-2 bg-gradient-to-r from-red-650 to-red-550 hover:from-red-550 hover:to-red-450 text-white font-bold rounded-xl text-xs transition shadow-md active:scale-98">Ubah Username & Password</button>
                            </div>
                        </form>
                    </div>
                </div>

            </main>
        </div>

        <script>
            // Sidebar responsiveness toggle for mobile
            function toggleMobileMenu() {
                const sidebar = document.getElementById('sidebar-container');
                if (sidebar) {
                    sidebar.classList.toggle('-translate-x-full');
                }
            }

            // Admin Dashboard Tabs Manager
            function switchTab(tabId) {
                // Hide all tabs
                document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
                
                // Show current tab
                const contentEl = document.getElementById('content-' + tabId);
                if (contentEl) contentEl.classList.remove('hidden');

                // Toggle active styles on sidebar buttons
                document.querySelectorAll('[id^="tab-"]').forEach(el => {
                    el.classList.remove('bg-red-650/10', 'text-white', 'border-l-2', 'border-red-500');
                    el.classList.add('text-neutral-400');
                    // Reset SVG icons stroke colors
                    const svg = el.querySelector('svg');
                    if (svg) svg.classList.replace('text-red-500', 'text-neutral-450');
                });
                
                const currentTabBtn = document.getElementById('tab-' + tabId);
                if (currentTabBtn) {
                    currentTabBtn.classList.remove('text-neutral-400');
                    currentTabBtn.classList.add('bg-red-650/10', 'text-white', 'border-l-2', 'border-red-500');
                    // Set active SVG icon stroke
                    const activeSvg = currentTabBtn.querySelector('svg');
                    if (activeSvg) activeSvg.classList.replace('text-neutral-450', 'text-red-500');
                }

                // Update current dashboard header text
                const headerTitle = document.getElementById('dashboard-current-title');
                if (headerTitle) {
                    const textLabel = tabId.charAt(0).toUpperCase() + tabId.slice(1);
                    headerTitle.textContent = `Dashboard ${textLabel}`;
                }

                // Close mobile menu after clicking
                const sidebar = document.getElementById('sidebar-container');
                if (sidebar && window.innerWidth < 1024) {
                    sidebar.classList.add('-translate-x-full');
                }
            }

            function toggleQrisFieldsAdmin() {
                const select = document.getElementById('qris_type_select_admin');
                const fileField = document.getElementById('qris_file_field_admin');
                const textField = document.getElementById('qris_text_field_admin');
                
                if (select) {
                    if (select.value === 'text') {
                        if (fileField) fileField.classList.add('hidden');
                        if (textField) textField.classList.remove('hidden');
                    } else {
                        if (fileField) fileField.classList.remove('hidden');
                        if (textField) textField.classList.add('hidden');
                    }
                }
            }
        </script>
    <?php endif; ?>

    <script>
        function togglePasswordVisibility(inputId, iconId) {
            const pwdInput = document.getElementById(inputId);
            const iconEl = document.getElementById(iconId);
            if (pwdInput && iconEl) {
                if (pwdInput.type === 'password') {
                    pwdInput.type = 'text';
                    // Slashed eye icon SVG
                    iconEl.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" /></svg>`;
                } else {
                    pwdInput.type = 'password';
                    // Open eye icon SVG
                    iconEl.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>`;
                }
            }
        }
    </script>
</body>
</html>
