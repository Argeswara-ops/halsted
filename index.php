<?php
/**
 * Aplikasi KodeMetrik - Halstead & McCabe Complexity Analyzer
 * Premium Full-Width Edition
 */

$results = null;
$code_input = '';
$analysis_source = '';
$inputType = 'paste';
$active_tab = 'home';
$files_data = [];

require_once __DIR__ . '/lib/Analyzer.php';

// Form Submission Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $inputType = $_POST['input_type'] ?? 'paste';

    if ($inputType === 'paste' && !empty($_POST['code'])) {
        $code_input = $_POST['code'];
        
        // Dynamically detect file extension to parse functions correctly
        $detected_ext = 'php'; // fallback default
        $trimmed_code = trim($code_input);
        
        if (stripos($trimmed_code, '<?php') !== false || stripos($trimmed_code, 'namespace ') !== false || stripos($trimmed_code, 'use ') !== false) {
            $detected_ext = 'php';
        } elseif (stripos($trimmed_code, '<!DOCTYPE') !== false || stripos($trimmed_code, '<html') !== false || (stripos($trimmed_code, '<body') !== false && stripos($trimmed_code, '<script') !== false)) {
            $detected_ext = 'html';
        } elseif (stripos($trimmed_code, 'const ') !== false || stripos($trimmed_code, 'let ') !== false || stripos($trimmed_code, 'console.log') !== false || stripos($trimmed_code, 'document.') !== false || stripos($trimmed_code, 'window.') !== false || stripos($trimmed_code, '=>') !== false) {
            $detected_ext = 'js';
        }
        
        $files_data['pasted_code.' . $detected_ext] = $code_input;
        $analysis_source = "Teks Kode yang Di-paste (" . strtoupper($detected_ext) . ")";
        $results = analyzeProject($files_data);

    } elseif ($inputType === 'file' && isset($_FILES['single_file']) && $_FILES['single_file']['error'] == 0) {
        $code_input = file_get_contents($_FILES['single_file']['tmp_name']);
        $analysis_source = htmlspecialchars($_FILES['single_file']['name']);
        $files_data[$analysis_source] = $code_input;
        $results = analyzeProject($files_data);

    } elseif ($inputType === 'folder' && isset($_FILES['folder_files'])) {
        $file_count = 0;
        foreach ($_FILES['folder_files']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['folder_files']['error'][$key] == 0) {
                $fileName = $_FILES['folder_files']['name'][$key];
                $ext = pathinfo($fileName, PATHINFO_EXTENSION);
                
                if (in_array($ext, ['php', 'js', 'html'])) {
                    $files_data[$fileName] = file_get_contents($tmpName);
                    $file_count++;
                }
            }
        }
        
        if ($file_count > 0) {
            $code_input = "";
            foreach ($files_data as $fName => $fCode) {
                $code_input .= "\n// --- File: $fName ---\n" . $fCode;
            }
            $analysis_source = "Folder Proyek ($file_count file)";
            $results = analyzeProject($files_data);
        } else {
            $analysis_source = "Gagal: Tidak ditemukan file valid.";
        }
    }
}

if ($results) {
    $active_tab = 'results';
} elseif (isset($_POST['action'])) {
    $active_tab = 'analyzer';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Aplikasi analisis kompleksitas kode menggunakan metode Halstead Metrics & McCabe Complexity dengan dukungan upload file dan folder.">
    <title>CodePulse - Premium Static Code Analyzer</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        cafe: {
                            50: '#fcfaf7',
                            100: '#f5efe6',
                            200: '#e6d5c3',
                            300: '#cbb29b',
                            400: '#ab886d',
                            500: '#8c6239',
                            600: '#6f4e37',
                            700: '#5c3d2e',
                            800: '#432c23',
                            900: '#2b1b17',
                            950: '#120b08',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Custom Vanilla CSS for Premium Theme & Effects -->
    <style>
        body {
            background-color: #030303;
            color: #e5e5e5;
            background-image: 
                radial-gradient(circle at 50% -10%, rgba(232, 31, 37, 0.12) 0%, transparent 65%),
                linear-gradient(rgba(255, 255, 255, 0.006) 1px, transparent 1px), 
                linear-gradient(90deg, rgba(255, 255, 255, 0.006) 1px, transparent 1px);
            background-size: auto, 32px 32px, 32px 32px;
            overflow-x: hidden;
        }

        /* Glowing Gradients */
        .glow-sphere {
            position: absolute;
            border-radius: 50%;
            filter: blur(140px);
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #050814;
        }
        ::-webkit-scrollbar-thumb {
            background: #111827;
            border-radius: 4px;
            border: 1px solid #1f2937;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #1f2937;
        }

        /* Main View State switcher styles */
        .main-tab-content { display: none; }
        .main-tab-content.active { display: block; animation: fadeIn 0.4s ease-out; }
        
        /* Inner Results tabs switcher styles */
        .results-tab-panel { display: none; }
        .results-tab-panel.active { display: block; animation: fadeIn 0.3s ease-out; }

        .input-panel { display: none; }
        .input-panel.active { display: block; animation: fadeIn 0.2s ease-out; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Glassmorphism Classes */
        .glass-card {
            background: rgba(10, 10, 10, 0.85);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(232, 31, 37, 0.08);
            box-shadow: 0 12px 40px 0 rgba(0, 0, 0, 0.65);
        }

        .glass-nav {
            background: rgba(4, 4, 4, 0.92);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-bottom: 1px solid rgba(232, 31, 37, 0.12);
            box-shadow: 0 4px 20px 0 rgba(0, 0, 0, 0.9);
        }

        /* Custom Drag and Drop Hover Effect */
        .dropzone-hover {
            border-color: #e81f25 !important;
            background: rgba(232, 31, 37, 0.05) !important;
        }

        .animate-spin-slow {
            animation: spin 10s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        /* Printing adjustments to make a clean minimalist report */
        @media print {
            body {
                background: white !important;
                color: black !important;
                background-image: none !important;
            }
            nav, footer, button, .no-print, .action-buttons, .instructions-container {
                display: none !important;
            }
            .glass-card {
                background: transparent !important;
                border: 1px solid #e5e7eb !important;
                box-shadow: none !important;
                backdrop-filter: none !important;
                color: black !important;
            }
            .text-white, .text-neutral-200, .text-neutral-300 {
                color: #111827 !important;
            }
            .text-neutral-500, .text-neutral-550 {
                color: #4b5563 !important;
            }
            .border-neutral-800 {
                border-color: #e5e7eb !important;
            }
            .results-tab-panel {
                display: block !important;
            }
            .print-grid {
                display: block !important;
            }
        }
    </style>
</head>
<body class="min-h-screen relative antialiased selection:bg-red-600/30 selection:text-white">

    <!-- Decorative Glow Spheres (Teal & Cyan accents) -->
    <div class="glow-sphere w-[500px] h-[500px] bg-[#050505]0 top-[-100px] left-[-100px]"></div>
    <div class="glow-sphere w-[500px] h-[500px] bg-neutral-800 bottom-[100px] right-[-100px]"></div>

    <!-- Navigation Header -->
    <nav class="glass-nav sticky top-0 z-50 transition-all duration-300 no-print">
        <div class="max-w-[1600px] mx-auto px-6 lg:px-10">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center space-x-2.5 cursor-pointer" onclick="switchMainTab('home')">
                    <div class="w-9 h-9 bg-gradient-to-br from-red-600 to-red-400 rounded-lg flex items-center justify-center text-white font-mono font-bold text-sm shadow-md shadow-red-600/10">
                        [C•P]
                    </div>
                    <span class="text-lg font-bold text-white tracking-tight font-sans">CodePulse<span class="text-red-500 font-light">.io</span></span>
                </div>
                <div class="flex items-center space-x-6">
                    <button onclick="switchMainTab('home')" id="nav-home" class="text-sm font-semibold transition-colors duration-200">Overview</button>
                    <button onclick="switchMainTab('analyzer')" id="nav-analyzer" class="text-sm font-semibold transition-colors duration-200">Analyzer Console</button>
                    <button onclick="switchMainTab('history')" id="nav-history" class="text-sm font-semibold text-neutral-500 hover:text-white transition duration-200">History Logs</button>
                    <button onclick="switchMainTab('home'); setTimeout(() => document.getElementById('how-it-works').scrollIntoView({behavior: 'smooth'}), 150)" class="text-sm font-semibold text-neutral-500 hover:text-white transition duration-200">Docs</button>
                    <div class="h-4 w-[1px] bg-slate-800"></div>
                    <button onclick="switchMainTab('analyzer')" class="px-3.5 py-1.5 bg-white hover:bg-neutral-200 text-xs font-bold rounded-lg text-neutral-950 transition duration-200 shadow-md">
                        Start Analyzer
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="max-w-[1600px] mx-auto px-6 lg:px-10 py-8 relative z-10">
        
        <!-- ==================== VIEW: HOME ==================== -->
        <div id="main-view-home" class="main-tab-content active">
            <div class="text-center max-w-3xl mx-auto mb-16 mt-12">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-[#050505]0/10 text-red-500 border border-neutral-800 uppercase tracking-wider mb-6">
                    <span class="w-1.5 h-1.5 rounded-full bg-indigo-400 mr-2 animate-pulse"></span>
                    Software Quality Metrics
                </span>
                <h1 class="text-4xl font-extrabold tracking-tight mt-3 mb-6 sm:text-6xl text-white">
                    Ukur Kualitas Kode dengan <br>
                    <span class="bg-gradient-to-r from-red-600 via-neutral-100 to-red-500 bg-clip-text text-transparent">Halstead & McCabe Metrics</span>
                </h1>
                <p class="text-lg text-neutral-500 leading-relaxed max-w-2xl mx-auto">
                    Ketahui tingkat kesulitan logika, beban kognitif (effort), estimasi bug, hingga kompleksitas percabangan program menggunakan analisis statis formal terpadu.
                </p>
                <div class="mt-10 flex justify-center gap-4">
                    <button onclick="switchMainTab('analyzer')" class="inline-flex items-center px-6 py-3.5 border border-transparent text-sm font-semibold rounded-xl text-white bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 shadow-lg shadow-red-600/10 transition duration-300 transform hover:-translate-y-0.5">
                        Mulai Menganalisis
                        <svg class="ml-2 -mr-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>
                    </button>
                    <button onclick="document.getElementById('how-it-works').scrollIntoView({behavior: 'smooth'})" class="inline-flex items-center px-6 py-3.5 rounded-xl text-sm font-semibold text-neutral-300 hover:text-white border border-neutral-800 hover:border-slate-700 transition duration-300">
                        Pelajari Parameter
                    </button>
                </div>
            </div>

            <!-- Highlights -->
            <div id="how-it-works" class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-16">
                <div class="glass-card p-6 rounded-2xl transition duration-300 hover:scale-[1.02] hover:border-slate-600/25">
                    <div class="w-11 h-11 bg-[#050505]0/10 rounded-xl flex items-center justify-center text-red-500 mb-5 border border-neutral-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">1. Tokenizing (Halstead)</h3>
                    <p class="text-xs text-neutral-500 leading-relaxed">
                        Kode program dipecah menjadi <strong>Operator</strong> (kata kunci, operator aritmatika/logika, tanda baca) dan <strong>Operand</strong> (nama variabel, nilai konstanta, literal string).
                    </p>
                </div>
                <div class="glass-card p-6 rounded-2xl transition duration-300 hover:scale-[1.02] hover:border-red-500/20">
                    <div class="w-11 h-11 bg-neutral-800/10 rounded-xl flex items-center justify-center text-neutral-550 mb-5 border border-neutral-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">2. Cyclomatic Complexity (McCabe)</h3>
                    <p class="text-xs text-neutral-500 leading-relaxed">
                        Mengukur jumlah jalur eksekusi independen secara linier melalui grafik alir kontrol kode untuk mengidentifikasi tingkat kerumitan percabangan dan perulangan.
                    </p>
                </div>
                <div class="glass-card p-6 rounded-2xl transition duration-300 hover:scale-[1.02] hover:border-red-500/25">
                    <div class="w-11 h-11 bg-[#050505]0/15 rounded-xl flex items-center justify-center text-red-500 mb-5 border border-slate-500/20">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">3. Cognitive Load & Bugs</h3>
                    <p class="text-xs text-neutral-500 leading-relaxed">
                        Memproyeksikan usaha mental ($E$) programmer untuk menyelesaikan kode ini, estimasi waktu ($T$) pemahaman, dan prediksi jumlah potensi bug ($B$) bawaan.
                    </p>
                </div>
            </div>
        </div>

        <!-- ==================== VIEW: ANALYZER CONSOLE ==================== -->
        <div id="main-view-analyzer" class="main-tab-content">
            
            <div class="mb-6">
                <h1 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                    <span class="px-2.5 py-2.5 bg-neutral-900/40 border border-neutral-800 rounded-xl text-red-500 text-sm flex items-center justify-center">
                        <svg class="w-4 h-4 text-red-500 animate-spin-slow" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </span>
                    Analyzer Console
                </h1>
                <p class="text-xs text-neutral-500 mt-1">Masukkan kode sumber Anda di bawah ini melalui metode paste, berkas tunggal, atau seluruh folder proyek.</p>
            </div>

            <!-- Full Width Input Interface (lg:grid-cols-12 layout) -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Form Box: Span 8 (2/3 width) -->
                <div class="lg:col-span-8 glass-card rounded-2xl p-6 shadow-xl border border-neutral-800">
                    
                    <!-- Form Tabs switcher -->
                    <div class="flex bg-neutral-900/20/80 p-1.5 rounded-xl mb-6 text-xs font-semibold border border-neutral-800 max-w-sm">
                        <button type="button" onclick="switchInputMode('paste')" id="tab-input-paste" class="flex-1 py-2.5 text-center rounded-lg transition-all duration-200">Paste Code</button>
                        <button type="button" onclick="switchInputMode('file')" id="tab-input-file" class="flex-1 py-2.5 text-center rounded-lg transition-all duration-200">File Upload</button>
                        <button type="button" onclick="switchInputMode('folder')" id="tab-input-folder" class="flex-1 py-2.5 text-center rounded-lg transition-all duration-200">Folder Upload</button>
                    </div>

                    <form id="calculator-form" action="" method="POST" enctype="multipart/form-data" class="space-y-6">
                        <input type="hidden" name="action" value="calculate">
                        <input type="hidden" name="input_type" id="hidden_input_type" value="paste">
                        
                        <!-- Paste Section -->
                        <div id="panel-input-paste" class="input-panel">
                            <div class="flex justify-between items-center mb-2">
                                <label class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Tempel Kode Sumber:</label>
                                <span class="text-[10px] text-neutral-550">Mendukung PHP, JS, HTML</span>
                            </div>
                            <textarea id="code" name="code" rows="22" class="w-full p-4 font-mono text-[11px] border border-neutral-800/60 rounded-xl focus:ring-2 focus:ring-teal-500/50 focus:border-slate-500/80 bg-neutral-950 text-neutral-100 border-neutral-800/80 outline-none transition duration-200 min-h-[420px]" placeholder="Paste kode program Anda di sini untuk memulai penghitungan..."><?php echo htmlspecialchars($inputType === 'paste' ? $code_input : ''); ?></textarea>
                        </div>

                        <!-- File Upload Section (Full Width drag zones) -->
                        <div id="panel-input-file" class="input-panel">
                            <label class="block text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Pilih File (.php / .js / .html):</label>
                            <div id="dropzone-file" class="border-2 border-dashed border-neutral-800 rounded-xl py-24 px-8 text-center bg-neutral-900/20 hover:bg-neutral-900/80 hover:border-red-500/30 transition duration-300 cursor-pointer relative group min-h-[280px] flex flex-col justify-center items-center">
                                <input type="file" id="file-input-control" name="single_file" accept=".php,.js,.html" class="absolute inset-0 opacity-0 cursor-pointer">
                                <div class="space-y-4 pointer-events-none flex flex-col items-center">
                                    <svg class="w-12 h-12 text-neutral-550 group-hover:text-red-500 transition-colors duration-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"></path>
                                    </svg>
                                    <p class="text-sm font-semibold text-neutral-300" id="file-display-name">Drag & Drop file Anda di sini, atau cari dari komputer</p>
                                    <p class="text-xs text-neutral-550">Mendukung format berkas .php, .js, .html</p>
                                </div>
                            </div>
                        </div>

                        <!-- Folder Upload Section -->
                        <div id="panel-input-folder" class="input-panel">
                            <label class="block text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2">Pilih Folder Proyek Web:</label>
                            <div id="dropzone-folder" class="border-2 border-dashed border-neutral-800 rounded-xl py-24 px-8 text-center bg-neutral-900/20 hover:bg-neutral-900/80 hover:border-red-500/30 transition duration-300 cursor-pointer relative group min-h-[280px] flex flex-col justify-center items-center">
                                <input type="file" id="folder-input-control" name="folder_files[]" webkitdirectory directory multiple class="absolute inset-0 opacity-0 cursor-pointer">
                                <div class="space-y-4 pointer-events-none flex flex-col items-center">
                                    <svg class="w-12 h-12 text-neutral-550 group-hover:text-red-500 transition-colors duration-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 00-1.061-.44H4.5A2.25 2.25 0 002.25 6v12a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9a2.25 2.25 0 00-2.25-2.25h-5.379a1.5 1.5 0 01-1.06-.44z"></path>
                                    </svg>
                                    <p class="text-sm font-semibold text-neutral-300" id="folder-display-name">Drag & Drop folder proyek Anda di sini, atau cari folder</p>
                                    <p class="text-xs text-neutral-550">Sistem akan otomatis menggabungkan seluruh file .php, .js, dan .html di dalamnya</p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions row -->
                        <div class="flex gap-3">
                            <button type="submit" class="flex-1 py-3.5 bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 text-white font-bold text-sm rounded-xl transition duration-300 shadow-lg shadow-red-600/10 active:scale-[0.99] flex items-center justify-center gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-0.5"><polygon points="6 3 20 12 6 21 6 3"></polygon></svg> Jalankan Analisis Kode
                              </button>
                            <button type="button" onclick="resetCalculator()" class="px-4 py-3.5 border border-neutral-800 hover:bg-slate-900 text-slate-450 hover:text-white rounded-xl transition duration-200" title="Bersihkan input">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline mr-1"><path d="M3 6h18"></path><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path></svg> Clear
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Guidelines: Span 4 (1/3 width) -->
                <div class="lg:col-span-4 space-y-6 instructions-container">
                    
                    <!-- Direct Downloads Box -->
                    <div class="glass-card rounded-2xl p-6 border border-neutral-800">
                        <h3 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-3 flex items-center">
                             <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-500 mr-2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" x2="12" y1="15" y2="3"></line></svg> File Uji Coba Cepat
                        </h3>
                        <p class="text-xs text-neutral-500 mb-4 leading-relaxed">
                            Unduh file pengujian di bawah ini langsung ke komputer Anda untuk mengetes fitur upload file:
                        </p>
                        <div class="flex flex-col gap-2">
                            <a href="tests/samples/test-upload.js" download="test-upload.js" class="flex items-center justify-between px-4 py-3 bg-neutral-900/20 hover:bg-slate-900 border border-neutral-800 hover:border-slate-600/25 rounded-xl text-xs font-semibold text-neutral-300 hover:text-white transition duration-200">
                                <span>JavaScript Sample (`.js`)</span>
                                <span class="text-red-500 font-mono text-[10px]">Unduh <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline ml-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" x2="12" y1="15" y2="3"></line></svg></span>
                            </a>
                            <a href="tests/samples/test-upload-php.txt" download="test-upload.php" class="flex items-center justify-between px-4 py-3 bg-neutral-900/20 hover:bg-slate-900 border border-neutral-800 hover:border-slate-600/25 rounded-xl text-xs font-semibold text-neutral-300 hover:text-white transition duration-200">
                                <span>PHP Object Sample (`.php`)</span>
                                <span class="text-red-500 font-mono text-[10px]">Unduh <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline ml-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" x2="12" y1="15" y2="3"></line></svg></span>
                            </a>
                            <a href="tests/samples/ProductController-php.txt" download="ProductController.php" class="flex items-center justify-between px-4 py-3 bg-neutral-900/20 hover:bg-slate-900 border border-neutral-800 hover:border-slate-600/25 rounded-xl text-xs font-semibold text-neutral-300 hover:text-white transition duration-200">
                                <span>Laravel Controller Sample (`.php`)</span>
                                <span class="text-red-500 font-mono text-[10px]">Unduh <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline ml-1"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" x2="12" y1="15" y2="3"></line></svg></span>
                            </a>
                        </div>
                    </div>

                    <!-- Steps Guide Box -->
                    <div class="glass-card rounded-2xl p-6 border border-neutral-800">
                        <h3 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-4 flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-500 mr-2"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-.9 1.5-2.2 1.5-3.5A7 7 0 0 0 4 8c0 1 .3 2.2 1.3 3.1.7.8 1.2 1.5 1.7 2.4"></path><path d="M9 18h6"></path><path d="M10 22h4"></path></svg> Petunjuk Cara Pengujian
                        </h3>
                        <div class="space-y-4 text-xs text-neutral-500">
                            <div>
                                <p class="font-semibold text-red-500 mb-1">A. Uji Coba Upload File:</p>
                                <ol class="list-decimal pl-4 space-y-1 leading-relaxed">
                                    <li>Klik salah satu tombol unduh file di atas.</li>
                                    <li>Pilih tab <strong>"File Upload"</strong> di sebelah kiri.</li>
                                    <li>Seret file yang terunduh ke area dropzone, atau klik untuk memilih file tersebut.</li>
                                    <li>Klik <strong>"Jalankan Analisis Kode"</strong>.</li>
                                </ol>
                            </div>
                            <div class="border-t border-neutral-800/40 pt-3">
                                <p class="font-semibold text-red-500 mb-1">B. Uji Coba Upload Folder:</p>
                                <ol class="list-decimal pl-4 space-y-1 leading-relaxed">
                                    <li>Pilih tab <strong>"Folder Upload"</strong> di sebelah kiri.</li>
                                    <li>Klik dropzone folder.</li>
                                    <li>Arahkan dan pilih direktori bernama <code class="bg-neutral-900/20 px-1 py-0.5 rounded text-red-500 font-mono">samples/</code> di dalam folder proyek Anda.</li>
                                    <li>Setujui dialog konfirmasi upload di browser Anda.</li>
                                    <li>Klik <strong>"Jalankan Analisis Kode"</strong>.</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ==================== VIEW: RESULTS DASHBOARD ==================== -->
        <div id="main-view-results" class="main-tab-content">
            <?php if ($results): ?>
                
                <!-- Action Title Block (Matches KodeMetrik header layout) -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                    <div>
                        <!-- Navigation actions -->
                        <div class="flex items-center space-x-2 mb-2 action-buttons">
                            <button onclick="switchMainTab('analyzer')" class="px-3 py-1.5 text-xs font-semibold text-neutral-300 hover:text-white border border-neutral-800 bg-[#050505] rounded-lg transition duration-200">
                                &larr; Back to Analyzer
                            </button>
                            <button onclick="window.location.href = window.location.pathname;" class="px-3 py-1.5 text-xs font-semibold text-neutral-300 hover:text-white border border-neutral-800 bg-[#050505] rounded-lg transition duration-200">
                                Refresh
                            </button>
                        </div>
                        <!-- Program Title info -->
                        <h1 class="text-2xl font-bold text-white tracking-tight flex items-center">
                            <span id="analysis-source-title"><?php echo $analysis_source; ?></span> <span class="mx-2 text-red-500 font-normal">&middot;</span> <span id="live-clock" class="text-neutral-500 font-normal text-lg"><?php echo date('n/j/Y, g:i:s A'); ?></span>
                        </h1>
                        <p class="text-[11px] text-neutral-550 mt-1">Print PDF exports only the data tables (Functions &amp; Files) in a clean minimalist format.</p>
                    </div>

                    <!-- Right buttons (Matching colors exactly) -->
                    <div class="flex items-center space-x-2 action-buttons">
                        <button onclick="window.print()" class="px-4 py-2 text-xs font-bold text-white bg-gradient-to-r from-red-600 to-red-500 hover:from-red-500 hover:to-red-400 rounded-lg shadow-md shadow-indigo-500/5 transition duration-200 flex items-center gap-1.5">
                            Print PDF
                        </button>
                        <button onclick="exportToCSV()" class="px-4 py-2 text-xs font-bold text-red-500 hover:text-red-500 border border-indigo-200 hover:border-neutral-800 bg-[#050505] hover:bg-slate-900 rounded-lg transition duration-200 flex items-center gap-1.5">
                            Export Excel
                        </button>
                    </div>
                </div>

                <!-- Custom Navigation Tabs bar (Matches KodeMetrik tabs) -->
                <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center border-b border-neutral-800 mb-6 gap-4 no-print">
                    <div class="flex flex-wrap bg-neutral-900/20 p-1.5 rounded-xl border border-neutral-800 mb-[-1px] text-xs font-semibold gap-1">
                        <button onclick="switchResultsTab('overview')" id="res-tab-overview" class="flex items-center gap-1.5 px-4 py-2 rounded-lg transition-all duration-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"></path></svg>
                            Overview
                        </button>
                        <button onclick="switchResultsTab('mccabe')" id="res-tab-mccabe" class="flex items-center gap-1.5 px-4 py-2 rounded-lg transition-all duration-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                            McCabe Complexity
                        </button>
                        <button onclick="switchResultsTab('halstead')" id="res-tab-halstead" class="flex items-center gap-1.5 px-4 py-2 rounded-lg transition-all duration-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path></svg>
                            Halstead Metrics
                        </button>
                        <button onclick="switchResultsTab('cfg')" id="res-tab-cfg" class="flex items-center gap-1.5 px-4 py-2 rounded-lg transition-all duration-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 10.742l-2.022 1.348A2 2 0 114.75 10a2 2 0 012.38 2.01l2.022-1.348m0 0l2.022 1.348A2 2 0 1113 12a2 2 0 012.38-2.01l-2.022 1.348m0-2.696V7a2 2 0 10-4 0v3.304"></path></svg>
                            Control Flow Graph (CFG)
                        </button>
                        <button onclick="switchResultsTab('files')" id="res-tab-files" class="flex items-center gap-1.5 px-4 py-2 rounded-lg transition-all duration-200">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            Files Report
                        </button>
                        <button onclick="switchResultsTab('tokens')" id="res-tab-tokens" class="px-4 py-2 rounded-lg transition-all duration-200">Parsed Tokens</button>
                        <button onclick="switchResultsTab('source')" id="res-tab-source" class="px-4 py-2 rounded-lg transition-all duration-200">Source Code</button>
                    </div>
                    <div class="text-[9px] font-mono text-neutral-550 tracking-widest uppercase py-2">
                        CLIENT STATIC AST PIPELINE <span class="text-red-500 font-bold">DONE</span>
                    </div>
                </div>

                <!-- ==================== RESULTS TAB: OVERVIEW ==================== -->
                <div id="results-panel-overview" class="results-tab-panel">
                    
                    <!-- Metrics Highlights Row (6 Columns, full width!) -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                        
                        <!-- Card 1: TOTAL FILES -->
                        <div class="glass-card p-5 rounded-2xl border-l-4 border-l-red-600 hover:border-slate-700/80 transition duration-305 flex flex-col justify-between min-h-[110px]">
                            <div>
                                <span class="text-[10px] font-bold text-neutral-550 block uppercase tracking-wider mb-2">TOTAL FILES</span>
                                <div class="mt-1 inline-block">
                                    <span id="res-total-files" class="text-2xl font-extrabold text-white font-mono bg-neutral-900/20/60 px-2.5 py-1 rounded-xl border border-neutral-800/60 shadow-inner"><?php echo $results['total_files']; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: TOTAL FUNCTIONS -->
                        <div class="glass-card p-5 rounded-2xl border-l-4 border-l-red-500 hover:border-slate-700/80 transition duration-305 flex flex-col justify-between min-h-[110px]">
                            <div>
                                <span class="text-[10px] font-bold text-neutral-550 block uppercase tracking-wider mb-2">TOTAL FUNCTIONS</span>
                                <div class="mt-1 inline-block">
                                    <span id="res-total-functions" class="text-2xl font-extrabold text-white font-mono bg-neutral-900/20/60 px-2.5 py-1 rounded-xl border border-neutral-800/60 shadow-inner"><?php echo $results['total_functions']; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: AVERAGE COMPLEXITY -->
                        <div class="glass-card p-5 rounded-2xl border-l-4 border-l-neutral-700 hover:border-slate-700/80 transition duration-305 flex flex-col justify-between min-h-[110px]">
                            <div>
                                <span class="text-[10px] font-bold text-neutral-550 block uppercase tracking-wider mb-2">AVERAGE COMPLEXITY</span>
                                <div class="mt-1 inline-block">
                                    <span id="res-avg-complexity" class="text-2xl font-extrabold text-white font-mono bg-neutral-900/20/60 px-2.5 py-1 rounded-xl border border-neutral-800/60 shadow-inner"><?php echo number_format($results['avg_complexity'], 1); ?></span>
                                </div>
                            </div>
                            <span class="text-[10px] text-neutral-550 block mt-2">Average linear branch complexity</span>
                        </div>

                        <!-- Card 4: HIGH RISK FUNCTIONS -->
                        <div class="glass-card p-5 rounded-2xl border-l-4 border-l-amber-500 hover:border-slate-700/80 transition duration-305 flex flex-col justify-between min-h-[110px]">
                            <div>
                                <span class="text-[10px] font-bold text-neutral-550 block uppercase tracking-wider mb-2">HIGH RISK FUNCTIONS</span>
                                <div class="mt-1 inline-block">
                                    <span id="res-high-risk-functions" class="text-2xl font-extrabold text-white font-mono bg-neutral-900/20/60 px-2.5 py-1 rounded-xl border border-neutral-800/60 shadow-inner"><?php echo $results['high_risk_functions']; ?></span>
                                </div>
                            </div>
                            <span class="text-[10px] text-neutral-550 block mt-2">Complexity status = high</span>
                        </div>

                        <!-- Card 5: ESTIMATED BUGS -->
                        <div class="glass-card p-5 rounded-2xl border-l-4 border-l-rose-500 hover:border-slate-700/80 transition duration-305 flex flex-col justify-between min-h-[110px]">
                            <div>
                                <span class="text-[10px] font-bold text-neutral-550 block uppercase tracking-wider mb-2">ESTIMATED BUGS</span>
                                <div class="mt-1 inline-block">
                                    <span id="res-estimated-bugs" class="text-2xl font-extrabold text-white font-mono bg-neutral-900/20/60 px-2.5 py-1 rounded-xl border border-neutral-800/60 shadow-inner"><?php echo number_format($results['B'], 3); ?></span>
                                </div>
                            </div>
                            <span class="text-[10px] text-neutral-550 block mt-2">Mathematical Halstead bug...</span>
                        </div>

                        <!-- Card 6: GLOBAL RATING -->
                        <?php
                            $rating = 'Good';
                            $rating_color = 'text-sky-400';
                            $rating_dot = 'bg-sky-400';
                            $rating_border = 'border-l-sky-500';
                            if ($results['avg_complexity'] > 20) {
                                $rating = 'Refactor';
                                $rating_color = 'text-rose-500';
                                $rating_dot = 'bg-rose-500';
                                $rating_border = 'border-l-rose-500';
                            } elseif ($results['avg_complexity'] > 10) {
                                $rating = 'Moderate';
                                $rating_color = 'text-amber-500';
                                $rating_dot = 'bg-amber-500';
                                $rating_border = 'border-l-amber-500';
                            }
                        ?>
                        <div id="res-global-rating-card" class="glass-card p-5 rounded-2xl border-l-4 <?php echo $rating_border; ?> hover:border-slate-700/80 transition duration-305 flex flex-col justify-between min-h-[110px]">
                            <div>
                                <span class="text-[10px] font-bold text-neutral-550 block uppercase tracking-wider mb-1">GLOBAL RATING</span>
                                <div class="mt-1 inline-block" id="res-global-rating-container">
                                    <span class="text-xl font-extrabold font-mono <?php echo $rating_color; ?> flex items-center gap-2 bg-neutral-900/20/60 px-2.5 py-1 rounded-xl border border-neutral-800/60 shadow-inner">
                                        <span class="w-2.5 h-2.5 rounded-full <?php echo $rating_dot; ?> inline-block animate-pulse"></span>
                                        <?php echo $rating; ?>
                                    </span>
                                </div>
                            </div>
                            <span class="text-[10px] text-neutral-550 block mt-2">Based on average linear branch counts</span>
                        </div>
                    </div>

                    <!-- Lower details: 2 column layout matching side-by-side specs -->
                    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 print-grid">
                        
                        <!-- Left card: Complexity Breakdown (5 columns) -->
                        <div class="lg:col-span-5 glass-card rounded-2xl p-6 border border-neutral-800">
                            <h3 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-6 pb-2 border-b border-neutral-800/60">
                                COMPLEXITY BREAKDOWN
                            </h3>
                            <p class="text-xs text-neutral-550 mb-6">Function distribution based on safety levels.</p>
                            
                            <?php
                                $safe_count = $results['cc_breakdown']['safe'];
                                $mod_count = $results['cc_breakdown']['moderate'];
                                $high_count = $results['cc_breakdown']['high'];
                                $total_funcs = max(1, $results['total_functions']);
                                
                                $safe_pct = ($safe_count / $total_funcs) * 100;
                                $mod_pct = ($mod_count / $total_funcs) * 100;
                                $high_pct = ($high_count / $total_funcs) * 100;
                            ?>
                             <div class="space-y-6">
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-2">
                                        <span class="text-red-500">Simple &amp; Safe (CC &le; 10)</span>
                                        <span id="breakdown-safe-count" class="text-white font-mono bg-neutral-900/20/80 px-2 py-0.5 rounded border border-neutral-800/60 shadow-inner"><?php echo $safe_count; ?></span>
                                    </div>
                                    <div class="w-full bg-neutral-900/20 h-2 rounded-full overflow-hidden border border-neutral-800 shadow-inner">
                                        <div id="breakdown-safe-bar" class="bg-gradient-to-r from-red-600 to-red-400 h-full rounded-full" style="width: <?php echo $safe_pct; ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-2">
                                        <span class="text-amber-400">Moderate Risk (CC 11-20)</span>
                                        <span id="breakdown-mod-count" class="text-white font-mono bg-neutral-900/20/80 px-2 py-0.5 rounded border border-neutral-800/60 shadow-inner"><?php echo $mod_count; ?></span>
                                    </div>
                                    <div class="w-full bg-neutral-900/20 h-2 rounded-full overflow-hidden border border-neutral-800 shadow-inner">
                                        <div id="breakdown-mod-bar" class="bg-gradient-to-r from-amber-500 to-yellow-400 h-full rounded-full" style="width: <?php echo $mod_pct; ?>%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs font-semibold mb-2">
                                        <span class="text-rose-450">High Risk / Critical (CC &gt; 20)</span>
                                        <span id="breakdown-high-count" class="text-white font-mono bg-neutral-900/20/80 px-2 py-0.5 rounded border border-neutral-800/60 shadow-inner"><?php echo $high_count; ?></span>
                                    </div>
                                    <div class="w-full bg-neutral-900/20 h-2 rounded-full overflow-hidden border border-neutral-800 shadow-inner">
                                        <div id="breakdown-high-bar" class="bg-gradient-to-r from-rose-500 to-red-500 h-full rounded-full" style="width: <?php echo $high_pct; ?>%"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 pt-4 border-t border-neutral-800/40 flex items-center gap-2 text-[11px] text-neutral-550">
                                <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18h.01M8 21h8a2 2 0 002-2H6a2 2 0 002 2zm10-10a6 6 0 11-12 0c0-1.657.672-3.157 1.757-4.243C8.843 5.672 10.343 5 12 5s3.157.672 4.243 1.757A5.977 5.977 0 0118 11z"></path>
                                </svg>
                                <span>Target CC &le; 10 to make unit testing and maintenance easier.</span>
                            </div>
                        </div>

                        <!-- Right card: Refactoring Recommendations & Insights (7 columns) -->
                        <div class="lg:col-span-7 glass-card rounded-2xl p-6 border border-neutral-800 flex flex-col justify-between">
                            <div>
                                <h3 class="text-xs font-bold text-neutral-500 uppercase tracking-wider mb-2 pb-2 border-b border-neutral-800/60">
                                    REFACTORING RECOMMENDATIONS
                                </h3>
                                <p class="text-xs text-neutral-550 mb-6">Actionable steps to optimize static code structures.</p>
                                
                                <div id="refactoring-recommendations-container" class="flex flex-col min-h-[190px]">
                                    <?php if ($results['high_risk_functions'] == 0 && $results['avg_complexity'] <= 10): ?>
                                         <!-- Excellent code placeholder -->
                                         <div class="flex-1 flex flex-col items-center justify-center text-center py-6">
                                             <div class="w-14 h-14 bg-[#050505]0/15 border border-slate-500/20 text-red-500 rounded-full flex items-center justify-center mb-4 relative">
                                                 <span class="absolute inset-0 rounded-full bg-emerald-500/15 animate-ping opacity-75"></span>
                                                 <svg class="w-6 h-6 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                     <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                                 </svg>
                                             </div>
                                             <h4 class="text-sm font-bold text-white mb-1">System Secure &amp; Clean</h4>
                                             <p class="text-xs text-neutral-500">All logic flows are highly optimized. No critical complexity risk detected.</p>
                                         </div>
                                     <?php else: ?>
                                        <div class="space-y-4 overflow-y-auto max-h-[220px] pr-2">
                                            <?php 
                                            $shown = 0;
                                            foreach ($results['functions'] as $f) {
                                                if ($f['complexity'] > 10) {
                                                    $shown++;
                                                    $level = $f['complexity'] > 20 ? 'Critical' : 'Moderate';
                                                    $badge_color = $f['complexity'] > 20 ? 'bg-rose-500/10 text-rose-400 border-rose-500/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                                                    ?>
                                                    <div class="p-3 bg-neutral-900/20/65 border border-neutral-800 rounded-xl flex items-start gap-3">
                                                        <span class="text-sm mt-0.5">⚠️</span>
                                                        <div class="flex-1">
                                                            <div class="flex justify-between items-center mb-1">
                                                                <h5 class="text-xs font-bold text-white font-mono"><?php echo htmlspecialchars($f['name']); ?>()</h5>
                                                                <span class="px-2 py-0.5 rounded-full text-[9px] font-bold border <?php echo $badge_color; ?>">
                                                                    CC: <?php echo $f['complexity']; ?> (<?php echo $level; ?>)
                                                                </span>
                                                            </div>
                                                            <p class="text-[11px] text-neutral-500 leading-relaxed">
                                                                Function has high McCabe complexity. Consider splitting it into smaller helpers and reducing nested branching statements to simplify control flows.
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                            if ($shown === 0 && $results['D'] > 15) {
                                                ?>
                                                <div class="p-3 bg-neutral-900/20/65 border border-neutral-800 rounded-xl flex items-start gap-3">
                                                    <span class="text-sm mt-0.5">💡</span>
                                                    <div class="flex-1">
                                                        <h5 class="text-xs font-bold text-white">Global Difficulty is High</h5>
                                                        <p class="text-[11px] text-neutral-500 leading-relaxed">
                                                            Overall logic difficulty ($D = <?php echo $results['D']; ?>$) is relatively high. Extract nested operations into class methods or separate functions to reduce cognitive strain.
                                                        </p>
                                                    </div>
                                                </div>
                                                <?php
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- ==================== RESULTS TAB: MCCABE COMPLEXITY ==================== -->
                <div id="results-panel-mccabe" class="results-tab-panel">
                    <div class="glass-card border border-neutral-800 rounded-2xl overflow-hidden shadow-xl">
                        <div class="bg-neutral-900/20 px-5 py-4 border-b border-neutral-800 flex justify-between items-center">
                            <h3 class="text-xs font-bold text-neutral-500 uppercase tracking-wider">McCabe Cyclomatic Complexity Analysis</h3>
                            <span class="text-[10px] text-neutral-550">Formulasi Keputusan: (Jalur Keputusan + 1)</span>
                        </div>
                        <table class="w-full text-left text-xs text-neutral-300">
                            <thead class="bg-neutral-900/20/80 text-[10px] text-neutral-550 uppercase font-semibold border-b border-neutral-800">
                                <tr>
                                    <th class="px-6 py-4 font-bold">Nama Fungsi</th>
                                    <th class="px-6 py-4">Lokasi Berkas</th>
                                    <th class="px-6 py-4">Baris Kode (LOC)</th>
                                    <th class="px-6 py-4 text-center">Kompleksitas (CC)</th>
                                    <th class="px-6 py-4 text-right">Status Risiko</th>
                                </tr>
                            </thead>
                            <tbody id="mccabe-table-body" class="divide-y divide-slate-850">
                                <?php foreach ($results['functions'] as $f): ?>
                                    <?php
                                        $cc = $f['complexity'];
                                        if ($cc <= 10) {
                                            $status = 'Safe';
                                            $badge = 'bg-sky-500/10 text-sky-400 border-sky-500/20';
                                        } elseif ($cc <= 20) {
                                            $status = 'Moderate';
                                            $badge = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                                        } else {
                                            $status = 'High Risk';
                                            $badge = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                                        }
                                    ?>
                                    <tr class="hover:bg-neutral-900/40">
                                        <td class="px-6 py-4 font-mono font-bold text-white"><?php echo htmlspecialchars($f['name']); ?>()</td>
                                        <td class="px-6 py-4 text-neutral-500 font-mono"><?php echo htmlspecialchars($f['file']); ?></td>
                                        <td class="px-6 py-4 font-mono text-neutral-550"><?php echo $f['start_line']; ?> - <?php echo $f['end_line']; ?></td>
                                        <td class="px-6 py-4 text-center font-bold font-mono text-white"><?php echo $cc; ?></td>
                                        <td class="px-6 py-4 text-right">
                                            <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold border <?php echo $badge; ?>">
                                                <?php echo $status; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== RESULTS TAB: HALSTEAD METRICS ==================== -->
                <div id="results-panel-halstead" class="results-tab-panel">
                    <div class="glass-card border border-neutral-800 rounded-2xl overflow-hidden shadow-xl">
                        <div class="bg-neutral-900/20 px-5 py-4 border-b border-neutral-800 flex justify-between items-center">
                            <h3 class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Rincian Lengkap Halstead Complexity Metrics</h3>
                            <span class="text-[10px] text-neutral-550">Standar Formula Formal</span>
                        </div>
                        <table class="w-full text-left text-xs text-neutral-300">
                            <thead class="bg-neutral-900/20/80 text-[10px] text-neutral-550 uppercase font-semibold border-b border-neutral-800">
                                <tr>
                                    <th class="px-6 py-4 font-bold">Simbol</th>
                                    <th class="px-6 py-4">Nama Metrik</th>
                                    <th class="px-6 py-4">Rumus / Formula</th>
                                    <th class="px-6 py-4 text-right">Nilai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-850">
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-red-500 font-bold">n1</td>
                                    <td class="px-6 py-4 text-neutral-500">Operator Unik</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">-</td>
                                    <td id="halstead-n1" class="px-6 py-4 text-right font-semibold text-white font-mono"><?php echo $results['n1']; ?></td>
                                </tr>
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-red-500 font-bold">n2</td>
                                    <td class="px-6 py-4 text-neutral-500">Operand Unik</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">-</td>
                                    <td id="halstead-n2" class="px-6 py-4 text-right font-semibold text-white font-mono"><?php echo $results['n2']; ?></td>
                                </tr>
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-neutral-550 font-bold">N1</td>
                                    <td class="px-6 py-4 text-neutral-500">Total Operator</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">-</td>
                                    <td id="halstead-N1" class="px-6 py-4 text-right font-semibold text-white font-mono"><?php echo $results['N1']; ?></td>
                                </tr>
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-neutral-550 font-bold">N2</td>
                                    <td class="px-6 py-4 text-neutral-500">Total Operand</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">-</td>
                                    <td id="halstead-N2" class="px-6 py-4 text-right font-semibold text-white font-mono"><?php echo $results['N2']; ?></td>
                                </tr>
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-pink-400 font-bold">n</td>
                                    <td class="px-6 py-4 text-neutral-500">Program Vocabulary</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">n1 + n2</td>
                                    <td id="halstead-n" class="px-6 py-4 text-right font-semibold text-white font-mono"><?php echo $results['n']; ?></td>
                                </tr>
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-pink-400 font-bold">N</td>
                                    <td class="px-6 py-4 text-neutral-500">Program Length</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">N1 + N2</td>
                                    <td id="halstead-N" class="px-6 py-4 text-right font-semibold text-white font-mono"><?php echo $results['N']; ?></td>
                                </tr>
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-white font-bold">V</td>
                                    <td class="px-6 py-4 text-neutral-500">Program Volume (Bit)</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">N * log2(n)</td>
                                    <td id="halstead-V" class="px-6 py-4 text-right font-semibold text-white font-mono"><?php echo $results['V']; ?></td>
                                </tr>
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-amber-400 font-bold">D</td>
                                    <td class="px-6 py-4 text-neutral-500">Difficulty</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">(n1 / 2) * (N2 / n2)</td>
                                    <td id="halstead-D" class="px-6 py-4 text-right font-semibold text-white font-mono"><?php echo $results['D']; ?></td>
                                </tr>
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-red-500 font-bold">E</td>
                                    <td class="px-6 py-4 text-neutral-500">Programming Effort</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">D * V</td>
                                    <td id="halstead-E" class="px-6 py-4 text-right font-semibold text-white font-mono"><?php echo $results['E']; ?></td>
                                </tr>
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-sky-400 font-bold">T</td>
                                    <td class="px-6 py-4 text-neutral-500">Time Required (Estimasi)</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">E / 18 (Detik)</td>
                                    <td id="halstead-T" class="px-6 py-4 text-right font-semibold text-white font-mono">
                                        <?php echo $results['T'] > 60 ? round($results['T']/60, 2).' Menit' : $results['T'].' Detik'; ?>
                                    </td>
                                </tr>
                                <tr class="hover:bg-neutral-900/40">
                                    <td class="px-6 py-4 font-mono text-red-400 font-bold">B</td>
                                    <td class="px-6 py-4 text-neutral-500">Delivered Bugs Estimate</td>
                                    <td class="px-6 py-4 font-mono text-neutral-400">V / 3000</td>
                                    <td id="halstead-B" class="px-6 py-4 text-right font-semibold text-white font-mono"><?php echo $results['B']; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ==================== RESULTS TAB: CONTROL FLOW GRAPH ==================== -->
                <div id="results-panel-cfg" class="results-tab-panel">
                    <div class="glass-card border border-neutral-800 rounded-2xl overflow-hidden shadow-xl p-6">
                        <div class="mb-6">
                            <h3 class="text-sm font-bold text-white">Visualisasi Control Flow Graph (CFG)</h3>
                            <p class="text-xs text-neutral-500 mt-1">Diagram alir terpadu seluruh struktur fungsi di dalam kode sumber (Unified Interactive Whiteboard).</p>
                        </div>

                        <!-- Interactive Whiteboard Canvas -->
                        <div class="relative w-full bg-white rounded-2xl border border-neutral-800 shadow-sm min-h-[600px] overflow-hidden select-none flex flex-col justify-between" id="cfg-canvas-container">
                            <!-- SVG Canvas -->
                            <svg id="cfg-svg" width="100%" height="600" class="w-full h-full cursor-grab active:cursor-grabbing overflow-visible">
                                <defs>
                                    <!-- Grid Pattern -->
                                    <pattern id="cfg-grid-pattern" width="20" height="20" patternUnits="userSpaceOnUse">
                                        <path d="M 20 0 L 0 0 0 20" fill="none" stroke="#e2e8f0" stroke-width="0.75" />
                                    </pattern>
                                    <!-- Arrow marker -->
                                    <marker id="cfg-arrow" viewBox="0 0 10 10" refX="10" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
                                        <path d="M 0 1 L 10 5 L 0 9 z" fill="#1e293b" />
                                    </marker>
                                </defs>
                                <!-- Grid background -->
                                <rect width="100%" height="100%" fill="url(#cfg-grid-pattern)" />
                                
                                <!-- Zoom/Pan Group wrapper -->
                                <g id="cfg-zoom-group">
                                    <!-- Dynamic elements will be injected here -->
                                </g>
                            </svg>

                            <!-- Floating Control Panel in top-right corner -->
                            <div class="absolute top-4 right-4 z-20 flex items-center bg-indigo-950/95 text-white rounded-xl shadow-lg border border-neutral-800 px-3 py-1.5 gap-2 text-xs font-semibold select-none">
                                <button type="button" onclick="exportCFGToPNG()" class="px-2.5 py-1 hover:text-red-500 transition duration-150 rounded hover:bg-slate-800">PNG</button>
                                <button type="button" onclick="exportCFGToPDF()" class="px-2.5 py-1 hover:text-red-500 transition duration-150 rounded hover:bg-slate-800">PDF</button>
                                <div class="w-[1px] h-4 bg-slate-800 self-center mx-1"></div>
                                <button type="button" onclick="zoomCFG(-0.1)" class="w-7 h-7 flex items-center justify-center hover:bg-slate-800 rounded text-base transition duration-150">−</button>
                                <span id="cfg-zoom-level" class="min-w-[40px] text-center text-[11px] font-mono">100%</span>
                                <button type="button" onclick="zoomCFG(0.1)" class="w-7 h-7 flex items-center justify-center hover:bg-slate-800 rounded text-base transition duration-150">+</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== RESULTS TAB: FILES REPORT ==================== -->
                <div id="results-panel-files" class="results-tab-panel">
                    <div class="glass-card border border-neutral-800 rounded-2xl overflow-hidden shadow-xl">
                        <div class="bg-neutral-900/20 px-5 py-4 border-b border-neutral-800 flex justify-between items-center">
                            <h3 class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Laporan Metrik Berkas (Files Report)</h3>
                            <span class="text-[10px] text-neutral-550">Analisis per Berkas</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs text-neutral-300 min-w-[800px]">
                                <thead class="bg-neutral-900/20/80 text-[10px] text-neutral-550 uppercase font-semibold border-b border-neutral-800">
                                    <tr>
                                        <th class="px-6 py-4 font-bold">Nama Berkas</th>
                                        <th class="px-6 py-4">Baris Kode (LOC)</th>
                                        <th class="px-6 py-4 text-center">Jumlah Fungsi</th>
                                        <th class="px-6 py-4 text-center">Rata-rata CC</th>
                                        <th class="px-6 py-4 text-center text-neutral-550">Volume (V)</th>
                                        <th class="px-6 py-4 text-center text-amber-400">Difficulty (D)</th>
                                        <th class="px-6 py-4 text-center text-rose-450">Est. Bugs (B)</th>
                                        <th class="px-6 py-4 text-center">Total Operator</th>
                                        <th class="px-6 py-4 text-right">Total Operand</th>
                                    </tr>
                                </thead>
                                <tbody id="files-report-table-body" class="divide-y divide-slate-850">
                                    <?php foreach ($results['files_report'] as $file): ?>
                                        <tr class="hover:bg-neutral-900/40">
                                            <td class="px-6 py-4 font-mono font-bold text-red-500"><?php echo htmlspecialchars($file['name']); ?></td>
                                            <td class="px-6 py-4 font-mono text-white"><?php echo $file['lines']; ?></td>
                                            <td class="px-6 py-4 text-center font-mono text-neutral-500"><?php echo $file['functions_count']; ?></td>
                                            <td class="px-6 py-4 text-center font-bold font-mono text-white"><?php echo $file['avg_complexity']; ?></td>
                                            <td class="px-6 py-4 text-center font-mono text-cyan-450"><?php echo $file['volume']; ?></td>
                                            <td class="px-6 py-4 text-center font-mono text-amber-450"><?php echo $file['difficulty']; ?></td>
                                            <td class="px-6 py-4 text-center font-mono text-rose-400"><?php echo $file['bugs']; ?></td>
                                            <td class="px-6 py-4 text-center font-mono text-neutral-550"><?php echo $file['operators_count']; ?></td>
                                            <td class="px-6 py-4 text-right font-mono text-neutral-550"><?php echo $file['operands_count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ==================== RESULTS TAB: PARSED TOKENS ==================== -->
                <div id="results-panel-tokens" class="results-tab-panel">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="glass-card border border-neutral-800 rounded-2xl p-5 shadow-xl">
                            <h4 class="text-xs font-bold text-red-500 uppercase tracking-wider mb-4 flex items-center justify-between">
                                <span class="flex items-center gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-red-500"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg> Operator Unik (<span id="parsed-operators-count"><?php echo $results['n1']; ?></span>)</span>
                            </h4>
                            <div id="unique-operators-container" class="flex flex-wrap gap-2 p-4 bg-neutral-900/20 rounded-xl border border-neutral-800 min-h-[160px] align-content-start">
                                <?php if (!empty($results['unique_operators_list'])): ?>
                                    <?php foreach ($results['unique_operators_list'] as $op): ?>
                                        <code class="px-2.5 py-1 text-xs bg-slate-900 border border-neutral-800 rounded text-neutral-300 font-mono hover:border-red-500/30 transition-colors"><?php echo htmlspecialchars($op); ?></code>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-xs text-neutral-400 italic">Tidak ada operator yang ditemukan.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="glass-card border border-neutral-800 rounded-2xl p-5 shadow-xl">
                            <h4 class="text-xs font-bold text-neutral-550 uppercase tracking-wider mb-4 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-neutral-550">
                                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                                    </svg>
                                    Operand Unik (<span id="parsed-operands-count"><?php echo $results['n2']; ?></span>)
                                </span>
                            </h4>
                            <div id="unique-operands-container" class="flex flex-wrap gap-2 p-4 bg-neutral-900/20 rounded-xl border border-neutral-800 min-h-[160px] align-content-start">
                                <?php if (!empty($results['unique_operands_list'])): ?>
                                    <?php foreach ($results['unique_operands_list'] as $operand): ?>
                                        <code class="px-2.5 py-1 text-xs bg-slate-900 border border-neutral-800 rounded text-neutral-300 font-mono hover:border-red-500/30 transition-colors"><?php echo htmlspecialchars($operand); ?></code>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-xs text-neutral-400 italic">Tidak ada operand yang ditemukan.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ==================== RESULTS TAB: SOURCE CODE ==================== -->
                <div id="results-panel-source" class="results-tab-panel">
                    <div class="glass-card border border-neutral-800 rounded-2xl overflow-hidden shadow-xl">
                        <div class="bg-neutral-900/20 px-5 py-4 border-b border-neutral-800 flex justify-between items-center">
                            <h3 class="text-xs font-bold text-neutral-500 uppercase tracking-wider">Preview Source Code</h3>
                            <button onclick="copyAnalyzedCode()" class="px-3 py-1.5 bg-slate-900 border border-neutral-800 hover:bg-slate-800 text-[10px] font-bold text-neutral-300 hover:text-white rounded-lg transition duration-200 flex items-center gap-1">
                                📋 Copy Code
                            </button>
                        </div>
                        <div class="p-4 bg-neutral-900/20/60">
                            <pre id="analyzed-code-block" class="text-[10px] font-mono text-neutral-500 bg-neutral-900/20 p-4 border border-neutral-800/60 rounded-xl max-h-[500px] overflow-y-auto leading-relaxed"><?php echo htmlspecialchars($code_input); ?></pre>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Blank state results fallback -->
                <div class="glass-card border border-neutral-800 border-dashed rounded-2xl p-16 text-center text-slate-550 flex flex-col items-center justify-center h-full min-h-[350px]">
                    <div class="w-16 h-16 bg-slate-900 rounded-2xl flex items-center justify-center text-3xl mb-4 border border-neutral-800">
                        📊
                    </div>
                    <h3 class="text-sm font-bold text-neutral-300 mb-1">Belum Ada Data Hasil Analisis</h3>
                    <p class="text-xs text-neutral-550 max-w-sm">Jalankan proses analisis terlebih dahulu di Console Analyzer untuk memunculkan laporan metrik.</p>
                    <button onclick="switchMainTab('analyzer')" class="mt-4 px-4 py-2 bg-white hover:bg-neutral-200 text-neutral-950 text-xs font-bold rounded-lg transition duration-200 shadow-md">
                        Ke Analyzer Console
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- ==================== VIEW: HISTORY LOGS ==================== -->
        <div id="main-view-history" class="main-tab-content">
            <!-- Header Block -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
                <div>
                    <h1 class="text-2xl font-extrabold text-white tracking-tight flex items-center gap-2.5">
                        <span class="px-2.5 py-2.5 bg-neutral-900/40 border border-neutral-800 rounded-xl text-red-500 text-sm flex items-center justify-center">
                            <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                        </span>
                        Riwayat Analisis Kode
                    </h1>
                    <p class="text-xs text-neutral-550 mt-1">Daftar lengkap berkas dan folder yang telah Anda analisis sebelumnya secara real-time.</p>
                </div>
                <!-- Action Buttons -->
                <div class="flex items-center gap-3">
                    <button id="btn-clear-all-history" onclick="confirmClearHistory()" class="px-4 py-2 text-xs font-bold text-red-500 hover:text-white border border-red-500/35 hover:bg-red-650 hover:border-red-550 rounded-lg shadow-md transition duration-200 flex items-center gap-1.5 group">
                        <svg class="w-3.5 h-3.5 text-red-500 group-hover:text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        Hapus Semua Riwayat
                    </button>
                </div>
            </div>

            <!-- Stats Bar (Runs, Files, Max CC) -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="glass-card p-5 rounded-2xl border-l-4 border-l-red-500 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-neutral-555 block uppercase tracking-wider mb-1">TOTAL ANALISIS</span>
                        <span id="history-stat-runs" class="text-2xl font-extrabold text-white font-mono">0</span>
                    </div>
                    <svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                </div>
                <div class="glass-card p-5 rounded-2xl border-l-4 border-l-cyan-500 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-neutral-555 block uppercase tracking-wider mb-1">TOTAL BERKAS DIANALISIS</span>
                        <span id="history-stat-files" class="text-2xl font-extrabold text-white font-mono">0</span>
                    </div>
                    <svg class="w-6 h-6 text-cyan-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                    </svg>
                </div>
                <div class="glass-card p-5 rounded-2xl border-l-4 border-l-amber-500 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-neutral-555 block uppercase tracking-wider mb-1">MAX COMPLEXITY (CC)</span>
                        <span id="history-stat-maxcc" class="text-2xl font-extrabold text-white font-mono">0</span>
                    </div>
                    <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            </div>

            <!-- Filter & Search Panel -->
            <div class="glass-card rounded-2xl p-4 mb-6 border border-neutral-800 flex flex-col sm:flex-row gap-4 items-center justify-between">
                <div class="relative w-full sm:max-w-md">
                    <input type="text" id="history-search" oninput="filterHistory()" placeholder="Cari riwayat berdasarkan nama file/folder..." class="w-full pl-10 pr-4 py-2 border border-neutral-850 rounded-xl focus:ring-2 focus:ring-red-500/50 bg-neutral-950 text-neutral-100 border-neutral-800/80 outline-none text-xs transition duration-205">
                    <span class="absolute left-3.5 top-3 text-neutral-550">
                        <svg class="w-4 h-4 text-neutral-550" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </span>
                </div>
                <div class="flex items-center gap-2 text-xs text-neutral-500">
                    <span>Urutkan:</span>
                    <select id="history-sort" onchange="sortHistory()" class="bg-neutral-950 border border-neutral-800 rounded-lg px-2.5 py-1.5 text-neutral-300 outline-none focus:ring-1 focus:ring-red-500/40">
                        <option value="newest">Terbaru (Waktu)</option>
                        <option value="oldest">Terlama (Waktu)</option>
                        <option value="files-desc">Berkas Terbanyak</option>
                        <option value="cc-desc">Kompleksitas Tertinggi</option>
                    </select>
                </div>
            </div>

            <!-- History Cards Container -->
            <div id="history-list-container" class="space-y-4">
                <!-- Dynamically injected history cards -->
            </div>

            <!-- Empty State fallback -->
            <div id="history-empty-state" class="glass-card border border-neutral-850 border-dashed rounded-2xl p-16 text-center text-slate-550 flex flex-col items-center justify-center min-h-[350px] hidden">
                <div class="w-16 h-16 bg-slate-900 rounded-2xl flex items-center justify-center text-3xl mb-4 border border-neutral-800 flex items-center justify-center">
                    <svg class="w-8 h-8 text-neutral-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"></path>
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-neutral-300 mb-1">Belum Ada Riwayat Analisis</h3>
                <p class="text-xs text-neutral-550 max-w-sm">Jalankan proses analisis terlebih dahulu di Console Analyzer untuk memunculkan laporan metrik.</p>
                <button onclick="switchMainTab('analyzer')" class="mt-4 px-4 py-2 bg-white hover:bg-neutral-200 text-neutral-950 text-xs font-bold rounded-lg transition duration-200 shadow-md">
                    Ke Analyzer Console
                </button>
            </div>
        </div>

        <!-- Custom Delete Confirmation Modal -->
        <div id="confirm-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm hidden">
            <div class="glass-card rounded-2xl border border-neutral-800 max-w-md w-full p-6 shadow-2xl animate-fadeIn">
                <h3 class="text-lg font-bold text-white mb-2">Hapus Semua Riwayat?</h3>
                <p class="text-xs text-neutral-550 mb-6 leading-relaxed">Tindakan ini akan menghapus seluruh catatan riwayat analisis secara permanen dari browser Anda. Tindakan ini tidak dapat dibatalkan.</p>
                <div class="flex justify-end gap-3">
                    <button onclick="closeConfirmModal()" class="px-4 py-2 bg-neutral-900 hover:bg-slate-800 text-neutral-300 rounded-lg text-xs font-bold transition">Batal</button>
                    <button onclick="executeClearHistory()" class="px-4 py-2 bg-red-650 hover:bg-red-550 text-white rounded-lg text-xs font-bold transition shadow-lg shadow-red-600/10">Ya, Hapus Semua</button>
                </div>
            </div>
        </div>

    </main>

    <!-- Footer -->
    <footer class="mt-20 border-t border-neutral-800 bg-neutral-900/20/50 py-8 relative z-10 no-print">
        <div class="max-w-[1600px] mx-auto px-6 text-center text-xs text-neutral-400">
            <p>&copy; 2026 CodePulse. Dibuat oleh <a href="https://github.com/Argeswara-ops" target="_blank" class="text-red-500 hover:underline">Argeswara-ops</a>. Premium Edition.</p>
        </div>
    </footer>

    <!-- JavaScript logic -->
    <script>
        // Store parsed function structure in JavaScript for CFG rendering
        let parsedFunctions = <?php echo $results ? json_encode($results['functions']) : '[]'; ?>;
        let activeResults = <?php echo $results ? json_encode($results) : 'null'; ?>;
        let activeSource = <?php echo json_encode($analysis_source); ?>;
        let activeTimestamp = <?php echo json_encode(date('n/j/Y, g:i:s A')); ?>;
        let activeCodeInput = <?php echo json_encode($code_input); ?>;

        // Main Tab Switcher (Home vs Analyzer Console vs Results vs History)
        function switchMainTab(tabId) {
            document.querySelectorAll('.main-tab-content').forEach(view => view.classList.remove('active'));
            const activeView = document.getElementById('main-view-' + tabId);
            if (activeView) activeView.classList.add('active');

            const btnHome = document.getElementById('nav-home');
            const btnCalc = document.getElementById('nav-analyzer');
            const btnHist = document.getElementById('nav-history');
            
            // Reset nav styles
            if (btnHome) btnHome.className = 'text-sm font-semibold text-neutral-500 hover:text-white transition-colors duration-200';
            if (btnCalc) btnCalc.className = 'text-sm font-semibold text-neutral-500 hover:text-white transition-colors duration-200';
            if (btnHist) btnHist.className = 'text-sm font-semibold text-neutral-500 hover:text-white transition-colors duration-200';
            
            // Set styles based on tab selection
            if (tabId === 'home') {
                if (btnHome) btnHome.className = 'text-sm font-semibold text-red-500 transition-colors duration-200';
            } else if (tabId === 'analyzer') {
                if (btnCalc) btnCalc.className = 'text-sm font-semibold text-red-500 transition-colors duration-200';
            } else if (tabId === 'history') {
                if (btnHist) btnHist.className = 'text-sm font-semibold text-red-500 transition-colors duration-200';
                renderHistory();
            }
        }

        // Inner Results Sub-tabs Switcher (Overview vs McCabe vs Halstead vs CFG vs Files vs Tokens vs Source)
        function switchResultsTab(subTabId) {
            document.querySelectorAll('.results-tab-panel').forEach(panel => panel.classList.remove('active'));
            const activePanel = document.getElementById('results-panel-' + subTabId);
            if (activePanel) activePanel.classList.add('active');

            ['overview', 'mccabe', 'halstead', 'cfg', 'files', 'tokens', 'source'].forEach(tab => {
                const btn = document.getElementById('res-tab-' + tab);
                if (!btn) return;
                if (tab === subTabId) {
                    btn.className = 'flex items-center gap-1.5 px-4 py-2 rounded-lg bg-slate-900 text-white border border-neutral-800 shadow-sm font-semibold';
                } else {
                    btn.className = 'flex items-center gap-1.5 px-4 py-2 rounded-lg text-neutral-500 hover:text-neutral-200 transition-colors font-medium border border-transparent';
                }
            });

            if (subTabId === 'cfg') {
                setTimeout(renderCFG, 50);
            }
        }

        // Input Methods Inside Analyzer Panel
        function switchInputMode(modeId) {
            document.querySelectorAll('.input-panel').forEach(panel => panel.classList.remove('active'));
            const activePanel = document.getElementById('panel-input-' + modeId);
            if (activePanel) activePanel.classList.add('active');
            
            document.getElementById('hidden_input_type').value = modeId;

            ['paste', 'file', 'folder'].forEach(m => {
                const tab = document.getElementById('tab-input-' + m);
                if (!tab) return;
                if (m === modeId) {
                    tab.className = 'flex-1 py-2.5 text-center rounded-lg bg-white text-neutral-950 shadow-md font-bold';
                } else {
                    tab.className = 'flex-1 py-2.5 text-center rounded-lg text-neutral-500 hover:text-neutral-200 transition-colors font-medium';
                }
            });
        }

        function resetCalculator() {
            const codeArea = document.getElementById('code');
            if (codeArea) codeArea.value = '';

            const fileInput = document.getElementById('file-input-control');
            if (fileInput) fileInput.value = '';
            const fileDisplay = document.getElementById('file-display-name');
            if (fileDisplay) fileDisplay.textContent = 'Drag & Drop file Anda di sini, atau cari dari komputer';

            const folderInput = document.getElementById('folder-input-control');
            if (folderInput) folderInput.value = '';
            const folderDisplay = document.getElementById('folder-display-name');
            if (folderDisplay) folderDisplay.textContent = 'Drag & Drop folder proyek Anda di sini, atau cari folder';
        }

        // File and Folder Selection Display Listeners
        document.getElementById('file-input-control')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name || 'File dipilih';
            document.getElementById('file-display-name').textContent = '📄 ' + fileName;
            document.getElementById('file-display-name').classList.add('text-red-500');
        });

        document.getElementById('folder-input-control')?.addEventListener('change', function(e) {
            const fileCount = e.target.files?.length || 0;
            document.getElementById('folder-display-name').textContent = '📁 Terpilih: ' + fileCount + ' berkas di dalam folder';
            document.getElementById('folder-display-name').classList.add('text-red-500');
        });

        // Drag & Drop visual feedbacks
        const dragEvents = ['dragenter', 'dragover', 'dragleave', 'drop'];
        ['dropzone-file', 'dropzone-folder'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;

            dragEvents.forEach(evt => {
                el.addEventListener(evt, (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                }, false);
            });

            ['dragenter', 'dragover'].forEach(evt => {
                el.addEventListener(evt, () => el.classList.add('dropzone-hover'), false);
            });

            ['dragleave', 'drop'].forEach(evt => {
                el.addEventListener(evt, () => el.classList.remove('dropzone-hover'), false);
            });
        });

        // Copy analyzed source code
        function copyAnalyzedCode() {
            const codeBlock = document.getElementById('analyzed-code-block');
            if (!codeBlock) return;
            
            navigator.clipboard.writeText(codeBlock.textContent).then(() => {
                alert('Kode berhasil disalin ke clipboard!');
            }).catch(err => {
                console.error('Gagal menyalin kode: ', err);
            });
        }

        // CSV Export function for Halstead metrics
        // CSV Export function for Halstead metrics
        function exportToCSV() {
            if (!activeResults) return;
            let csvContent = "data:text/csv;charset=utf-8,";
            
            // Section 1: Global Summary
            csvContent += "=== GLOBAL SUMMARY METRICS ===\r\n";
            csvContent += '"Metric","Symbol","Value","Description"\r\n';
            const summary = [
                ["Total Files", "total_files", activeResults.total_files, "Jumlah Berkas"],
                ["Total Functions", "total_functions", activeResults.total_functions, "Jumlah Fungsi"],
                ["Average McCabe Complexity", "avg_complexity", activeResults.avg_complexity, "Rata-rata Cyclomatic Complexity"],
                ["High Risk Functions", "high_risk_functions", activeResults.high_risk_functions, "Fungsi Risiko Tinggi"],
                ["Estimated Bugs", "B", activeResults.B, "Potensi Bug Bawaan"],
                ["Total Lines", "lines", activeResults.lines, "Jumlah Baris Kode"],
                ["Vocabulary", "n", activeResults.n, "Vocabulary (n1 + n2)"],
                ["Length", "N", activeResults.N, "Length (N1 + N2)"],
                ["Volume", "V", activeResults.V, "Program Volume (Bits)"],
                ["Difficulty", "D", activeResults.D, "Difficulty"],
                ["Effort", "E", activeResults.E, "Programming Effort"],
                ["Time Required", "T", activeResults.T, "Time Required (Seconds)"]
            ];
            summary.forEach(function(rowArray) {
                let row = rowArray.map(val => `"${String(val).replace(/"/g, '""')}"`).join(",");
                csvContent += row + "\r\n";
            });
            csvContent += "\r\n";

            // Section 2: Files Report
            csvContent += "=== FILES REPORT ===\r\n";
            csvContent += '"File Name","Lines of Code (LOC)","Functions Count","Average Complexity (CC)","Volume (V)","Difficulty (D)","Est. Bugs (B)","Total Operators","Total Operands"\r\n';
            if (activeResults.files_report && activeResults.files_report.length > 0) {
                activeResults.files_report.forEach(function(file) {
                    csvContent += `"${file.name}","${file.lines}","${file.functions_count}","${file.avg_complexity}","${file.volume}","${file.difficulty}","${file.bugs}","${file.operators_count}","${file.operands_count}"\r\n`;
                });
            }
            csvContent += "\r\n";

            // Section 3: Functions Report
            csvContent += "=== FUNCTIONS & MCCABE COMPLEXITY ===\r\n";
            csvContent += '"Function Name","Source File","Line Range","Complexity (CC)","Risk Status"\r\n';
            if (activeResults.functions && activeResults.functions.length > 0) {
                activeResults.functions.forEach(function(f) {
                    let cc = f.complexity;
                    let status = 'Safe';
                    if (cc > 20) {
                        status = 'High Risk';
                    } else if (cc > 10) {
                        status = 'Moderate';
                    }
                    csvContent += `"${f.name}","${f.file}","${f.start_line} - ${f.end_line}","${cc}","${status}"\r\n`;
                });
            }

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `code_pulse_report_${activeSource.toLowerCase().replace(/[^a-z0-9]/g, '_')}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        let cfgScale = 1.0;
        let cfgPanX = 0;
        let cfgPanY = 0;
        let isDragging = false;
        let startX, startY;

        function zoomCFG(delta) {
            cfgScale = Math.max(0.3, Math.min(3.0, cfgScale + delta));
            const zoomLevelEl = document.getElementById('cfg-zoom-level');
            if (zoomLevelEl) {
                zoomLevelEl.textContent = Math.round(cfgScale * 100) + '%';
            }
            updateCFGTransform();
        }

        function updateCFGTransform() {
            const zoomGroup = document.getElementById('cfg-zoom-group');
            if (zoomGroup) {
                zoomGroup.setAttribute('transform', `translate(${cfgPanX}, ${cfgPanY}) scale(${cfgScale})`);
            }
        }

        function resetCFGZoom() {
            cfgScale = 1.0;
            cfgPanX = 0;
            cfgPanY = 0;
            const zoomLevelEl = document.getElementById('cfg-zoom-level');
            if (zoomLevelEl) {
                zoomLevelEl.textContent = '100%';
            }
            updateCFGTransform();
        }

        function initCFGPanZoom() {
            const canvasContainer = document.getElementById('cfg-canvas-container');
            const svg = document.getElementById('cfg-svg');
            if (!canvasContainer || !svg) return;

            svg.addEventListener('mousedown', (e) => {
                if (e.target.closest('button') || e.target.closest('.absolute')) return;
                isDragging = true;
                svg.style.cursor = 'grabbing';
                startX = e.clientX - cfgPanX;
                startY = e.clientY - cfgPanY;
            });

            window.addEventListener('mousemove', (e) => {
                if (!isDragging) return;
                cfgPanX = e.clientX - startX;
                cfgPanY = e.clientY - startY;
                updateCFGTransform();
            });

            window.addEventListener('mouseup', () => {
                if (isDragging) {
                    isDragging = false;
                    svg.style.cursor = 'grab';
                }
            });

            svg.addEventListener('wheel', (e) => {
                e.preventDefault();
                const delta = e.deltaY < 0 ? 0.05 : -0.05;
                zoomCFG(delta);
            });
        }

        function exportCFGToPDF() {
            const svg = document.getElementById('cfg-svg');
            if (!svg) return;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Control Flow Graph - PDF Export</title>
                    <style>
                        body { margin: 0; padding: 20px; display: flex; justify-content: center; align-items: center; background: white; }
                        svg { max-width: 100%; height: auto; }
                    </style>
                </head>
                <body>
                    ${svg.outerHTML}
                    <script>
                        window.onload = function() {
                            window.print();
                            setTimeout(() => window.close(), 100);
                        };
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        function exportCFGToPNG() {
            const svg = document.getElementById('cfg-svg');
            if (!svg) return;

            const svgCloned = svg.cloneNode(true);
            const zoomGroup = svgCloned.querySelector('#cfg-zoom-group');
            if (zoomGroup) {
                zoomGroup.removeAttribute('transform');
            }

            const liveZoomGroup = svg.querySelector('#cfg-zoom-group');
            const bounds = liveZoomGroup.getBBox();
            const padding = 60;
            const width = Math.max(600, bounds.width + bounds.x * 2 + padding);
            const height = Math.max(600, bounds.height + bounds.y + padding);

            svgCloned.setAttribute('width', width);
            svgCloned.setAttribute('height', height);

            const serializer = new XMLSerializer();
            const svgString = serializer.serializeToString(svgCloned);
            const svgBlob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
            const URL = window.URL || window.webkitURL || window;
            const blobURL = URL.createObjectURL(svgBlob);

            const image = new Image();
            image.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const context = canvas.getContext('2d');
                
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, width, height);
                context.drawImage(image, 0, 0);

                const png = canvas.toDataURL('image/png');
                const downloadLink = document.createElement('a');
                downloadLink.href = png;
                downloadLink.download = 'control_flow_graph.png';
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
                URL.revokeObjectURL(blobURL);
            };
            image.src = blobURL;
        }

        function renderCFG() {
            const svg = document.getElementById('cfg-svg');
            const zoomGroup = document.getElementById('cfg-zoom-group');
            if (!svg || !zoomGroup) return;
            zoomGroup.innerHTML = ''; // Clear contents

            // If there are no functions parsed, return early
            if (!parsedFunctions || parsedFunctions.length === 0) {
                return;
            }

            let nodes = [];
            let links = [];

            // Global Start Node at x: 260, y: 50
            nodes.push({ id: 'global_start', label: 'Start', type: 'start', x: 260, y: 50 });
            let lastNodeId = 'global_start';
            let currentY = 130;

            // Iterate over all parsed functions and build unified diagram sequential blocks
            parsedFunctions.forEach((func, fIdx) => {
                // Header block for function name
                const headerId = `func_header_${fIdx}`;
                nodes.push({ 
                    id: headerId, 
                    label: `function ${func.name}()`, 
                    type: 'statement', 
                    x: 260, 
                    y: currentY 
                });
                links.push({ from: lastNodeId, to: headerId, label: 'next' });
                lastNodeId = headerId;
                currentY += 100;

                // Simple parser of function body lines to find control flow decisions
                const cleanBody = func.body.replace(/\/\*[\s\S]*?\*\/|\/\/.*$/gm, '');
                const lines = cleanBody.split('\n')
                    .map(l => l.trim())
                    .filter(l => l.length > 0 && !l.startsWith('//') && !l.startsWith('/*') && !l.startsWith('*'));

                let decisions = [];
                lines.forEach((line, i) => {
                    const match = line.match(/\b(if|while|for|foreach|elseif|else if)\b/i);
                    if (match) {
                        decisions.push({
                            type: match[1].toLowerCase() === 'else if' ? 'elseif' : match[1].toLowerCase(),
                            lineContent: line.trim(),
                            index: i
                        });
                    }
                });

                // Render sequential statement nodes before the decisions
                const stmt1Id = `func_${fIdx}_stmt_1`;
                nodes.push({ id: stmt1Id, label: 'expressionstatement', type: 'statement', x: 260, y: currentY });
                links.push({ from: lastNodeId, to: stmt1Id, label: 'next' });
                lastNodeId = stmt1Id;
                currentY += 100;

                const stmt2Id = `func_${fIdx}_stmt_2`;
                nodes.push({ id: stmt2Id, label: 'expressionstatement', type: 'statement', x: 260, y: currentY });
                links.push({ from: lastNodeId, to: stmt2Id, label: 'next' });
                lastNodeId = stmt2Id;
                currentY += 100;

                // Process decisions
                decisions.forEach((dec, decIdx) => {
                    const isLoop = ['while', 'for', 'foreach'].includes(dec.type);
                    const condId = `func_${fIdx}_cond_${decIdx}`;

                    nodes.push({
                        id: condId,
                        label: `${dec.type} (...)`,
                        type: 'statement',
                        x: 260,
                        y: currentY
                    });
                    links.push({ from: lastNodeId, to: condId, label: 'next' });

                    if (isLoop) {
                        // T branch (left body)
                        const bodyId = `func_${fIdx}_loop_body_${decIdx}`;
                        nodes.push({
                            id: bodyId,
                            label: 'expressionstatement',
                            type: 'statement',
                            x: 100,
                            y: currentY + 110
                        });
                        links.push({ from: condId, to: bodyId, label: 'T' });
                        links.push({ from: bodyId, to: condId, label: 'back', type: 'loopback' });

                        // F branch (right merge circle dot)
                        const exitId = `func_${fIdx}_loop_exit_${decIdx}`;
                        nodes.push({
                            id: exitId,
                            label: '•',
                            type: 'merge',
                            x: 380,
                            y: currentY + 110
                        });
                        links.push({ from: condId, to: exitId, label: 'F' });

                        lastNodeId = exitId;
                        currentY += 210;
                    } else {
                        // Conditional branch (if)
                        // T branch
                        const trueId = `func_${fIdx}_if_true_${decIdx}`;
                        nodes.push({
                            id: trueId,
                            label: 'expressionstatement',
                            type: 'statement',
                            x: 100,
                            y: currentY + 110
                        });
                        links.push({ from: condId, to: trueId, label: 'T' });

                        // F branch / Merge
                        const mergeId = `func_${fIdx}_if_merge_${decIdx}`;
                        nodes.push({
                            id: mergeId,
                            label: '•',
                            type: 'merge',
                            x: 260,
                            y: currentY + 200
                        });
                        links.push({ from: trueId, to: mergeId, label: 'next' });
                        links.push({ from: condId, to: mergeId, label: 'F', type: 'bypass' });

                        lastNodeId = mergeId;
                        currentY += 200;
                    }
                });

                // Function exit statement representation
                const exitStmtId = `func_${fIdx}_exit_stmt`;
                nodes.push({ id: exitStmtId, label: 'expressionstatement', type: 'statement', x: 260, y: currentY });
                links.push({ from: lastNodeId, to: exitStmtId, label: 'next' });
                lastNodeId = exitStmtId;
                currentY += 100;
            });

            // Global final exit merge node
            const globalExitId = 'global_exit';
            nodes.push({ id: globalExitId, label: '•', type: 'merge', x: 260, y: currentY });
            links.push({ from: lastNodeId, to: globalExitId, label: 'next' });

            // Set canvas size dynamically based on layout height
            svg.setAttribute('height', (currentY + 120).toString());

            // 1. Draw Paths & Links first
            links.forEach(l => {
                const fromNode = nodes.find(n => n.id === l.from);
                const toNode = nodes.find(n => n.id === l.to);
                if (!fromNode || !toNode) return;

                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                let d = '';

                // Curve routing logic
                if (l.type === 'loopback') {
                    // Loop back to the bottom of the loop condition node
                    // from loop body (x: 100, y: body.y) to loop condition (x: 260, y: cond.y)
                    // goes down from body bottom center, curves left, goes up, curves right back in
                    d = `M ${fromNode.x} ${fromNode.y + 21} C ${fromNode.x} ${fromNode.y + 70}, ${fromNode.x - 70} ${fromNode.y + 75}, ${fromNode.x - 70} ${(fromNode.y + toNode.y)/2} C ${fromNode.x - 70} ${toNode.y + 75}, ${toNode.x} ${toNode.y + 70}, ${toNode.x} ${toNode.y + 21}`;
                } else if (l.type === 'bypass') {
                    // Bypass curve around the True branch
                    // from condition (x: 260, y: cond.y) to merge (x: 260, y: merge.y)
                    // curves to the right
                    d = `M ${fromNode.x} ${fromNode.y + 21} C ${fromNode.x + 140} ${(fromNode.y + toNode.y)/2}, ${toNode.x + 140} ${(fromNode.y + toNode.y)/2}, ${toNode.x} ${toNode.y - 10}`;
                } else if (l.label === 'T') {
                    // T branch curving to the left
                    d = `M ${fromNode.x} ${fromNode.y + 21} C ${fromNode.x} ${(fromNode.y + toNode.y)/2}, ${toNode.x} ${(fromNode.y + toNode.y)/2}, ${toNode.x} ${toNode.y - 21}`;
                } else if (l.label === 'F') {
                    // F branch curving to the right to merge node
                    d = `M ${fromNode.x} ${fromNode.y + 21} C ${fromNode.x} ${(fromNode.y + toNode.y)/2}, ${toNode.x} ${(fromNode.y + toNode.y)/2}, ${toNode.x} ${toNode.y - 10}`;
                } else {
                    // Straight line paths
                    const fromOffset = fromNode.type === 'merge' ? 10 : 21;
                    const toOffset = toNode.type === 'merge' ? 10 : 21;
                    d = `M ${fromNode.x} ${fromNode.y + fromOffset} L ${toNode.x} ${toNode.y - toOffset}`;
                }

                path.setAttribute('d', d);
                path.setAttribute('fill', 'none');
                path.setAttribute('stroke', '#1e293b');
                path.setAttribute('stroke-width', '1.5');
                path.setAttribute('marker-end', 'url(#cfg-arrow)');
                zoomGroup.appendChild(path);

                // Add Label/Badge at the path's midpoint using getPointAtLength
                if (l.label) {
                    let pt;
                    try {
                        const length = path.getTotalLength();
                        if (length > 0) {
                            pt = path.getPointAtLength(length / 2);
                        }
                    } catch (e) {}

                    if (!pt) {
                        pt = { x: (fromNode.x + toNode.x) / 2, y: (fromNode.y + toNode.y) / 2 };
                    }

                    const badgeGroup = document.createElementNS('http://www.w3.org/2000/svg', 'g');
                    badgeGroup.setAttribute('transform', `translate(${pt.x}, ${pt.y})`);

                    let badgeW = 34;
                    let badgeH = 18;
                    if (l.label === 'T' || l.label === 'F') {
                        badgeW = 16;
                    }

                    const badgeRect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                    badgeRect.setAttribute('x', (-badgeW / 2).toString());
                    badgeRect.setAttribute('y', (-badgeH / 2).toString());
                    badgeRect.setAttribute('width', badgeW.toString());
                    badgeRect.setAttribute('height', badgeH.toString());
                    badgeRect.setAttribute('rx', '3');
                    badgeRect.setAttribute('ry', '3');
                    badgeRect.setAttribute('fill', '#d9f99d'); // light green background
                    badgeRect.setAttribute('stroke', '#bbf7d0');
                    badgeRect.setAttribute('stroke-width', '0.75');

                    const badgeText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    badgeText.textContent = l.label;
                    badgeText.setAttribute('text-anchor', 'middle');
                    badgeText.setAttribute('dominant-baseline', 'central');
                    badgeText.setAttribute('fill', '#166534'); // dark green text
                    badgeText.setAttribute('font-size', '10px');
                    badgeText.setAttribute('font-family', 'Outfit, sans-serif');
                    badgeText.setAttribute('font-weight', 'bold');

                    badgeGroup.appendChild(badgeRect);
                    badgeGroup.appendChild(badgeText);
                    zoomGroup.appendChild(badgeGroup);
                }
            });

            // 2. Draw Nodes
            nodes.forEach(n => {
                const group = document.createElementNS('http://www.w3.org/2000/svg', 'g');

                if (n.type === 'start') {
                    // Pill capsule: width 100, height 36
                    const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                    rect.setAttribute('x', (n.x - 50).toString());
                    rect.setAttribute('y', (n.y - 18).toString());
                    rect.setAttribute('width', '100');
                    rect.setAttribute('height', '36');
                    rect.setAttribute('rx', '18');
                    rect.setAttribute('ry', '18');
                    rect.setAttribute('fill', '#93c5fd'); // light blue
                    rect.setAttribute('stroke', '#3b82f6'); // blue border
                    rect.setAttribute('stroke-width', '1.5');
                    group.appendChild(rect);

                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.textContent = n.label;
                    text.setAttribute('x', n.x.toString());
                    text.setAttribute('y', n.y.toString());
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('dominant-baseline', 'central');
                    text.setAttribute('fill', '#1e293b');
                    text.setAttribute('font-size', '12px');
                    text.setAttribute('font-family', 'Outfit, sans-serif');
                    text.setAttribute('font-weight', '500');
                    group.appendChild(text);

                } else if (n.type === 'merge') {
                    // Circle: radius 10 with dot in center
                    const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    circle.setAttribute('cx', n.x.toString());
                    circle.setAttribute('cy', n.y.toString());
                    circle.setAttribute('r', '10');
                    circle.setAttribute('fill', '#93c5fd');
                    circle.setAttribute('stroke', '#3b82f6');
                    circle.setAttribute('stroke-width', '1.5');
                    group.appendChild(circle);

                    const dot = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
                    dot.setAttribute('cx', n.x.toString());
                    dot.setAttribute('cy', n.y.toString());
                    dot.setAttribute('r', '2.5');
                    dot.setAttribute('fill', '#1e293b');
                    group.appendChild(dot);

                } else {
                    // Statement or condition: rounded rect width 180, height 42
                    const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
                    rect.setAttribute('x', (n.x - 90).toString());
                    rect.setAttribute('y', (n.y - 21).toString());
                    rect.setAttribute('width', '180');
                    rect.setAttribute('height', '42');
                    rect.setAttribute('rx', '4');
                    rect.setAttribute('ry', '4');
                    rect.setAttribute('fill', '#93c5fd');
                    rect.setAttribute('stroke', '#3b82f6');
                    rect.setAttribute('stroke-width', '1.5');
                    group.appendChild(rect);

                    const text = document.createElementNS('http://www.w3.org/2000/svg', 'text');
                    text.textContent = n.label;
                    text.setAttribute('x', n.x.toString());
                    text.setAttribute('y', n.y.toString());
                    text.setAttribute('text-anchor', 'middle');
                    text.setAttribute('dominant-baseline', 'central');
                    text.setAttribute('fill', '#1e293b');
                    text.setAttribute('font-size', '11px');
                    text.setAttribute('font-family', 'Outfit, sans-serif');
                    text.setAttribute('font-weight', '400');
                    group.appendChild(text);
                }

                zoomGroup.appendChild(group);
            });
        }

        // Capture static browser local time when analysis is rendered
        function setAnalysisTimestamp() {
            const clockEl = document.getElementById('live-clock');
            if (!clockEl) return;
            
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            
            hours = hours % 12;
            hours = hours ? hours : 12; // hour '0' should be '12'
            
            const month = now.getMonth() + 1;
            const day = now.getDate();
            const year = now.getFullYear();
            
            clockEl.textContent = `${month}/${day}/${year}, ${hours}:${minutes}:${seconds} ${ampm}`;
        }

        // --- HISTORY MANAGEMENT LOGIC ---

        function loadHistory() {
            try {
                return JSON.parse(localStorage.getItem('codepulse_history')) || [];
            } catch (e) {
                console.error('Failed to parse history: ', e);
                return [];
            }
        }

        function saveToHistory(source, results, codeInput) {
            if (!results) return;
            let history = loadHistory();
            
            // Deduplication: check if the exact same analysis was saved very recently (e.g., within 3 seconds)
            const now = Date.now();
            const isDuplicate = history.some(item => {
                return item.source === source && 
                       item.results.lines === results.lines &&
                       item.results.avg_complexity === results.avg_complexity &&
                       item.results.total_functions === results.total_functions &&
                       (now - item.timestamp < 3000); // 3 seconds threshold
            });
            
            if (isDuplicate) return;
            
            const item = {
                id: 'run_' + now + '_' + Math.random().toString(36).substr(2, 5),
                source: source,
                timestamp: now,
                results: results,
                codeInput: codeInput
            };
            
            history.unshift(item);
            if (history.length > 50) {
                history = history.slice(0, 50);
            }
            localStorage.setItem('codepulse_history', JSON.stringify(history));
        }

        function deleteHistoryItem(id, event) {
            if (event) event.stopPropagation();
            let history = loadHistory();
            history = history.filter(item => item.id !== id);
            localStorage.setItem('codepulse_history', JSON.stringify(history));
            renderHistory();
        }

        function confirmClearHistory() {
            const modal = document.getElementById('confirm-modal');
            if (modal) modal.classList.remove('hidden');
        }

        function closeConfirmModal() {
            const modal = document.getElementById('confirm-modal');
            if (modal) modal.classList.add('hidden');
        }

        function executeClearHistory() {
            localStorage.removeItem('codepulse_history');
            closeConfirmModal();
            renderHistory();
        }

        function getIndonesianDateTime(timestamp) {
            const date = new Date(timestamp);
            const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            const dayName = days[date.getDay()];
            const day = date.getDate();
            const monthName = months[date.getMonth()];
            const year = date.getFullYear();
            
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            const seconds = String(date.getSeconds()).padStart(2, '0');
            
            return `${dayName}, ${day} ${monthName} ${year} pukul ${hours}:${minutes}:${seconds}`;
        }

        function getRelativeTime(timestamp) {
            const diffMs = Date.now() - timestamp;
            const diffSec = Math.floor(diffMs / 1000);
            const diffMin = Math.floor(diffSec / 60);
            const diffHour = Math.floor(diffMin / 60);
            const diffDay = Math.floor(diffHour / 24);

            if (diffSec < 5) return 'Baru saja';
            if (diffSec < 60) return `${diffSec} detik yang lalu`;
            if (diffMin < 60) return `${diffMin} menit yang lalu`;
            if (diffHour < 24) return `${diffHour} jam yang lalu`;
            if (diffDay === 1) return 'Kemarin';
            return `${diffDay} hari yang lalu`;
        }

        function renderHistory() {
            const container = document.getElementById('history-list-container');
            const emptyState = document.getElementById('history-empty-state');
            const clearAllBtn = document.getElementById('btn-clear-all-history');
            
            if (!container) return;

            let history = loadHistory();
            
            // Search filter
            const searchVal = document.getElementById('history-search')?.value.toLowerCase().trim() || '';
            if (searchVal) {
                history = history.filter(item => item.source.toLowerCase().includes(searchVal));
            }

            // Sort logic
            const sortVal = document.getElementById('history-sort')?.value || 'newest';
            if (sortVal === 'newest') {
                history.sort((a, b) => b.timestamp - a.timestamp);
            } else if (sortVal === 'oldest') {
                history.sort((a, b) => a.timestamp - b.timestamp);
            } else if (sortVal === 'files-desc') {
                history.sort((a, b) => b.results.total_files - a.results.total_files);
            } else if (sortVal === 'cc-desc') {
                history.sort((a, b) => b.results.avg_complexity - a.results.avg_complexity);
            }

            // Update stats indicators
            const runsStat = document.getElementById('history-stat-runs');
            const filesStat = document.getElementById('history-stat-files');
            const ccStat = document.getElementById('history-stat-maxcc');

            const allHistory = loadHistory();
            let totalFilesCount = 0;
            let maxCC = 0;

            allHistory.forEach(item => {
                totalFilesCount += item.results.total_files || 0;
                if (item.results.avg_complexity > maxCC) {
                    maxCC = item.results.avg_complexity;
                }
            });

            if (runsStat) runsStat.textContent = allHistory.length;
            if (filesStat) filesStat.textContent = totalFilesCount;
            if (ccStat) ccStat.textContent = maxCC ? Number(maxCC).toFixed(1) : '0';

            if (history.length === 0) {
                container.innerHTML = '';
                container.classList.add('hidden');
                emptyState.classList.remove('hidden');
                if (clearAllBtn) clearAllBtn.disabled = true;
                if (clearAllBtn) clearAllBtn.classList.add('opacity-40', 'cursor-not-allowed');
                return;
            }

            container.classList.remove('hidden');
            emptyState.classList.add('hidden');
            if (clearAllBtn) clearAllBtn.disabled = false;
            if (clearAllBtn) clearAllBtn.classList.remove('opacity-40', 'cursor-not-allowed');

            let listHtml = '';
            history.forEach(item => {
                let badgeText = 'Paste';
                let badgeClass = 'bg-red-500/10 text-red-400 border-red-500/20';
                if (item.source.includes('Folder')) {
                    badgeText = 'Folder';
                    badgeClass = 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20';
                } else if (item.source !== 'Teks Kode yang Di-paste' && !item.source.includes('Folder')) {
                    badgeText = 'File';
                    badgeClass = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                }

                // Global Rating badge for history card
                let rating = 'Good';
                let ratingColor = 'text-sky-400';
                if (item.results.avg_complexity > 20) {
                    rating = 'Refactor';
                    ratingColor = 'text-rose-500';
                } else if (item.results.avg_complexity > 10) {
                    rating = 'Moderate';
                    ratingColor = 'text-amber-500';
                }

                listHtml += `
                    <div class="glass-card p-6 rounded-2xl border border-neutral-800 hover:border-red-500/20 transition-all duration-300 hover:scale-[1.01] flex flex-col md:flex-row justify-between items-start md:items-center gap-6 group">
                        <div class="space-y-3 flex-1">
                            <div class="flex items-center gap-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold border ${badgeClass}">${badgeText}</span>
                                <h4 class="text-sm font-bold text-white group-hover:text-red-500 transition duration-200 truncate max-w-sm md:max-w-md">${escapeHtml(item.source)}</h4>
                            </div>
                            <div class="flex flex-col gap-1 text-[11px] text-neutral-550">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5 text-neutral-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <span>${getIndonesianDateTime(item.timestamp)}</span>
                                </div>
                                <div class="flex items-center gap-1.5 font-medium text-red-450/80">
                                    <svg class="w-3.5 h-3.5 text-red-450/80" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span>${getRelativeTime(item.timestamp)}</span>
                                </div>
                            </div>
                            <!-- Mini stats grid -->
                            <div class="flex flex-wrap gap-2 pt-2">
                                <span class="px-2.5 py-1 bg-neutral-900/40 border border-neutral-800/80 rounded-lg text-[10px] font-mono text-neutral-450 flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-neutral-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                                    </svg>
                                    ${item.results.total_files} Berkas
                                </span>
                                <span class="px-2.5 py-1 bg-neutral-900/40 border border-neutral-800/80 rounded-lg text-[10px] font-mono text-neutral-450 flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-neutral-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    ${item.results.total_functions} Fungsi
                                </span>
                                <span class="px-2.5 py-1 bg-neutral-900/40 border border-neutral-800/80 rounded-lg text-[10px] font-mono text-neutral-450 flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-neutral-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                    CC: ${Number(item.results.avg_complexity).toFixed(1)}
                                </span>
                                <span class="px-2.5 py-1 bg-neutral-900/40 border border-neutral-800/80 rounded-lg text-[10px] font-mono text-neutral-450 flex items-center gap-1.5">
                                    <svg class="w-3 h-3 text-neutral-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    LOC: ${item.results.lines}
                                </span>
                                <span class="px-2.5 py-1 bg-neutral-900/40 border border-neutral-800/80 rounded-lg text-[10px] font-mono ${ratingColor} flex items-center gap-1.5">
                                    <svg class="w-3 h-3 fill-current" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                    Rating: ${rating}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 w-full md:w-auto justify-end">
                            <button onclick='loadAnalysisData(${JSON.stringify(item.results).replace(/'/g, "&#39;")}, ${JSON.stringify(item.source).replace(/'/g, "&#39;")}, ${JSON.stringify(getIndonesianDateTime(item.timestamp)).replace(/'/g, "&#39;")}, ${JSON.stringify(item.codeInput).replace(/'/g, "&#39;")})' class="px-4 py-2 bg-gradient-to-r from-red-650 to-red-550 hover:from-red-550 hover:to-red-450 text-white text-xs font-bold rounded-xl transition duration-300 shadow-md active:scale-95 flex-1 md:flex-initial text-center">
                                Muat Hasil
                            </button>
                            <button onclick="deleteHistoryItem('${item.id}', event)" class="p-2 border border-neutral-800 hover:bg-red-500/10 hover:border-red-500/30 text-neutral-500 hover:text-red-500 rounded-xl transition duration-200 flex items-center justify-center" title="Hapus catatan">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = listHtml;
        }

        function filterHistory() {
            renderHistory();
        }

        function sortHistory() {
            renderHistory();
        }

        // Auto render relative times update interval
        setInterval(() => {
            const activeTab = document.getElementById('main-view-history');
            if (activeTab && activeTab.classList.contains('active')) {
                renderHistory();
            }
        }, 15000);

        function loadAnalysisData(results, source, timestamp, codeInput) {
            // Update global state
            activeResults = results;
            activeSource = source;
            activeTimestamp = timestamp;
            activeCodeInput = codeInput;
            parsedFunctions = results.functions;

            // 1. Update program header details
            const sourceTitleEl = document.getElementById('analysis-source-title');
            if (sourceTitleEl) sourceTitleEl.textContent = source;
            
            const clockEl = document.getElementById('live-clock');
            if (clockEl) clockEl.textContent = timestamp;

            // 2. Update overview cards
            const totalFilesEl = document.getElementById('res-total-files');
            if (totalFilesEl) totalFilesEl.textContent = results.total_files;

            const totalFunctionsEl = document.getElementById('res-total-functions');
            if (totalFunctionsEl) totalFunctionsEl.textContent = results.total_functions;

            const avgComplexityEl = document.getElementById('res-avg-complexity');
            if (avgComplexityEl) avgComplexityEl.textContent = Number(results.avg_complexity).toFixed(1);

            const highRiskEl = document.getElementById('res-high-risk-functions');
            if (highRiskEl) highRiskEl.textContent = results.high_risk_functions;

            const bugsEl = document.getElementById('res-estimated-bugs');
            if (bugsEl) bugsEl.textContent = Number(results.B).toFixed(3);

            // Update global rating badge
            let rating = 'Good';
            let ratingColor = 'text-sky-400';
            let ratingDot = 'bg-sky-400';
            let ratingBorder = 'border-l-sky-500';
            if (results.avg_complexity > 20) {
                rating = 'Refactor';
                ratingColor = 'text-rose-500';
                ratingDot = 'bg-rose-500';
                ratingBorder = 'border-l-rose-500';
            } else if (results.avg_complexity > 10) {
                rating = 'Moderate';
                ratingColor = 'text-amber-500';
                ratingDot = 'bg-amber-500';
                ratingBorder = 'border-l-amber-500';
            }
            
            const ratingCard = document.getElementById('res-global-rating-card');
            if (ratingCard) {
                ratingCard.className = `glass-card p-5 rounded-2xl border-l-4 ${ratingBorder} hover:border-slate-700/80 transition duration-305 flex flex-col justify-between min-h-[110px]`;
            }
            
            const ratingContainer = document.getElementById('res-global-rating-container');
            if (ratingContainer) {
                ratingContainer.innerHTML = `
                    <span class="text-xl font-extrabold font-mono ${ratingColor} flex items-center gap-2 bg-neutral-900/20/60 px-2.5 py-1 rounded-xl border border-neutral-800/60 shadow-inner">
                        <span class="w-2.5 h-2.5 rounded-full ${ratingDot} inline-block animate-pulse"></span>
                        ${rating}
                    </span>
                `;
            }

            // 3. Update Complexity Breakdown
            const totalFuncs = Math.max(1, results.total_functions);
            const safeCount = results.cc_breakdown.safe;
            const modCount = results.cc_breakdown.moderate;
            const highCount = results.cc_breakdown.high;
            
            const safePct = (safeCount / totalFuncs) * 100;
            const modPct = (modCount / totalFuncs) * 100;
            const highPct = (highCount / totalFuncs) * 100;

            const safeCountEl = document.getElementById('breakdown-safe-count');
            if (safeCountEl) safeCountEl.textContent = safeCount;
            const safeBarEl = document.getElementById('breakdown-safe-bar');
            if (safeBarEl) safeBarEl.style.width = safePct + '%';

            const modCountEl = document.getElementById('breakdown-mod-count');
            if (modCountEl) modCountEl.textContent = modCount;
            const modBarEl = document.getElementById('breakdown-mod-bar');
            if (modBarEl) modBarEl.style.width = modPct + '%';

            const highCountEl = document.getElementById('breakdown-high-count');
            if (highCountEl) highCountEl.textContent = highCount;
            const highBarEl = document.getElementById('breakdown-high-bar');
            if (highBarEl) highBarEl.style.width = highPct + '%';

            // 4. Update Refactoring Recommendations
            const recContainer = document.getElementById('refactoring-recommendations-container');
            if (recContainer) {
                if (results.high_risk_functions === 0 && results.avg_complexity <= 10) {
                    recContainer.innerHTML = `
                        <div class="flex-1 flex flex-col items-center justify-center text-center py-6">
                            <div class="w-14 h-14 bg-neutral-900/20 border border-slate-500/20 text-red-500 rounded-full flex items-center justify-center mb-4 relative">
                                <span class="absolute inset-0 rounded-full bg-emerald-500/15 animate-ping opacity-75"></span>
                                <svg class="w-6 h-6 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                            <h4 class="text-sm font-bold text-white mb-1">System Secure &amp; Clean</h4>
                            <p class="text-xs text-neutral-550">All logic flows are highly optimized. No critical complexity risk detected.</p>
                        </div>
                    `;
                } else {
                    let recHtml = '<div class="space-y-4 overflow-y-auto max-h-[220px] pr-2">';
                    let shown = 0;
                    results.functions.forEach(f => {
                        if (f.complexity > 10) {
                            shown++;
                            let level = f.complexity > 20 ? 'Critical' : 'Moderate';
                            let badgeColor = f.complexity > 20 ? 'bg-rose-500/10 text-rose-400 border-rose-500/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                            recHtml += `
                                <div class="p-3 bg-neutral-900/20 border border-neutral-800 rounded-xl flex items-start gap-3">
                                    <span class="text-sm mt-0.5">⚠️</span>
                                    <div class="flex-1">
                                        <div class="flex justify-between items-center mb-1">
                                            <h5 class="text-xs font-bold text-white font-mono">${escapeHtml(f.name)}()</h5>
                                            <span class="px-2 py-0.5 rounded-full text-[9px] font-bold border ${badgeColor}">
                                                CC: ${f.complexity} (${level})
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-neutral-550 leading-relaxed">
                                            Function has high McCabe complexity. Consider splitting it into smaller helpers and reducing nested branching statements to simplify control flows.
                                        </p>
                                    </div>
                                </div>
                            `;
                        }
                    });
                    if (shown === 0 && results.D > 15) {
                        recHtml += `
                            <div class="p-3 bg-neutral-900/20 border border-neutral-800 rounded-xl flex items-start gap-3">
                                <span class="text-sm mt-0.5">💡</span>
                                <div class="flex-1">
                                    <h5 class="text-xs font-bold text-white">Global Difficulty is High</h5>
                                    <p class="text-[11px] text-neutral-550 leading-relaxed">
                                        Overall logic difficulty ($D = ${results.D}$) is relatively high. Extract nested operations into class methods or separate functions to reduce cognitive strain.
                                    </p>
                                </div>
                            </div>
                        `;
                    }
                    recHtml += '</div>';
                    recContainer.innerHTML = recHtml;
                }
            }

            // 5. Update McCabe Table
            const mccabeTableBody = document.getElementById('mccabe-table-body');
            if (mccabeTableBody) {
                let tableHtml = '';
                results.functions.forEach(f => {
                    let cc = f.complexity;
                    let status = 'Safe';
                    let badge = 'bg-sky-500/10 text-sky-400 border-sky-500/20';
                    if (cc > 20) {
                        status = 'High Risk';
                        badge = 'bg-rose-500/10 text-rose-400 border-rose-500/20';
                    } else if (cc > 10) {
                        status = 'Moderate';
                        badge = 'bg-amber-500/10 text-amber-400 border-amber-500/20';
                    }
                    tableHtml += `
                        <tr class="hover:bg-neutral-900/40">
                            <td class="px-6 py-4 font-mono font-bold text-white">${escapeHtml(f.name)}()</td>
                            <td class="px-6 py-4 text-neutral-550 font-mono">${escapeHtml(f.file)}</td>
                            <td class="px-6 py-4 font-mono text-neutral-550">${f.start_line} - ${f.end_line}</td>
                            <td class="px-6 py-4 text-center font-bold font-mono text-white">${cc}</td>
                            <td class="px-6 py-4 text-right">
                                <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold border ${badge}">
                                    ${status}
                                </span>
                            </td>
                        </tr>
                    `;
                });
                mccabeTableBody.innerHTML = tableHtml;
            }

            // 6. Update Halstead metrics
            const hn1 = document.getElementById('halstead-n1'); if (hn1) hn1.textContent = results.n1;
            const hn2 = document.getElementById('halstead-n2'); if (hn2) hn2.textContent = results.n2;
            const hN1 = document.getElementById('halstead-N1'); if (hN1) hN1.textContent = results.N1;
            const hN2 = document.getElementById('halstead-N2'); if (hN2) hN2.textContent = results.N2;
            const hn = document.getElementById('halstead-n'); if (hn) hn.textContent = results.n;
            const hN = document.getElementById('halstead-N'); if (hN) hN.textContent = results.N;
            const hV = document.getElementById('halstead-V'); if (hV) hV.textContent = results.V;
            const hD = document.getElementById('halstead-D'); if (hD) hD.textContent = results.D;
            const hE = document.getElementById('halstead-E'); if (hE) hE.textContent = results.E;
            const hT = document.getElementById('halstead-T'); if (hT) {
                hT.textContent = results.T > 60 ? (results.T / 60).toFixed(2) + ' Menit' : results.T + ' Detik';
            }
            const hB = document.getElementById('halstead-B'); if (hB) hB.textContent = results.B;

            // 7. Update Files Report Table
            const filesReportBody = document.getElementById('files-report-table-body');
            if (filesReportBody) {
                let filesHtml = '';
                results.files_report.forEach(file => {
                    filesHtml += `
                        <tr class="hover:bg-neutral-900/40">
                            <td class="px-6 py-4 font-mono font-bold text-red-500">${escapeHtml(file.name)}</td>
                            <td class="px-6 py-4 font-mono text-white">${file.lines}</td>
                            <td class="px-6 py-4 text-center font-mono text-neutral-550">${file.functions_count}</td>
                            <td class="px-6 py-4 text-center font-bold font-mono text-white">${file.avg_complexity}</td>
                            <td class="px-6 py-4 text-center font-mono text-cyan-450">${file.volume}</td>
                            <td class="px-6 py-4 text-center font-mono text-amber-450">${file.difficulty}</td>
                            <td class="px-6 py-4 text-center font-mono text-rose-400">${file.bugs}</td>
                            <td class="px-6 py-4 text-center font-mono text-neutral-550">${file.operators_count}</td>
                            <td class="px-6 py-4 text-right font-mono text-neutral-550">${file.operands_count}</td>
                        </tr>
                    `;
                });
                filesReportBody.innerHTML = filesHtml;
            }

            // 8. Update parsed tokens counts
            const opCountEl = document.getElementById('parsed-operators-count');
            if (opCountEl) opCountEl.textContent = results.n1;
            const opContainer = document.getElementById('unique-operators-container');
            if (opContainer) {
                if (results.unique_operators_list && results.unique_operators_list.length > 0) {
                    opContainer.innerHTML = results.unique_operators_list.map(op => 
                        `<code class="px-2.5 py-1 text-xs bg-slate-900 border border-neutral-800 rounded text-neutral-300 font-mono hover:border-red-500/30 transition-colors">${escapeHtml(op)}</code>`
                    ).join('');
                } else {
                    opContainer.innerHTML = '<span class="text-xs text-neutral-400 italic">Tidak ada operator yang ditemukan.</span>';
                }
            }

            const ordCountEl = document.getElementById('parsed-operands-count');
            if (ordCountEl) ordCountEl.textContent = results.n2;
            const ordContainer = document.getElementById('unique-operands-container');
            if (ordContainer) {
                if (results.unique_operands_list && results.unique_operands_list.length > 0) {
                    ordContainer.innerHTML = Math.max(0, results.unique_operands_list.length) ? results.unique_operands_list.map(operand => 
                        `<code class="px-2.5 py-1 text-xs bg-slate-900 border border-neutral-800 rounded text-neutral-300 font-mono hover:border-red-500/30 transition-colors">${escapeHtml(operand)}</code>`
                    ).join('') : '<span class="text-xs text-neutral-400 italic">Tidak ada operand yang ditemukan.</span>';
                } else {
                    ordContainer.innerHTML = '<span class="text-xs text-neutral-400 italic">Tidak ada operand yang ditemukan.</span>';
                }
            }

            // 9. Update source code preview
            const codeBlock = document.getElementById('analyzed-code-block');
            if (codeBlock) {
                codeBlock.textContent = codeInput;
            }

            // Switch view
            switchMainTab('results');
            switchResultsTab('overview');
            
            // Re-render Control Flow Graph with the newly loaded data
            setTimeout(() => {
                renderCFG();
                resetCFGZoom();
            }, 100);
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Initialize active tabs and subtabs on page load
        switchMainTab('<?php echo $active_tab; ?>');
        <?php if ($results): ?>
            // Save this analysis run to localStorage history
            saveToHistory(
                <?php echo json_encode($analysis_source); ?>,
                <?php echo json_encode($results); ?>,
                <?php echo json_encode($code_input); ?>
            );
            
            switchResultsTab('overview');
            initCFGPanZoom();
            setAnalysisTimestamp();
        <?php endif; ?>
        switchInputMode('<?php echo $inputType; ?>');
    </script>
</body>
</html>