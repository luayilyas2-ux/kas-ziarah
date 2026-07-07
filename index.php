<?php
// ==========================================
// 1. PENGATURAN DATABASE SUPABASE (POSTGRESQL)
// ==========================================
$uri = "postgresql://postgres.lhrsliggdllxmufhsvra:luayilyas23@aws-0-ap-northeast-1.pooler.supabase.com:6543/postgres";

try {
    $db_parsed = parse_url($uri);
    $host = $db_parsed['host'];
    $port = $db_parsed['port'];
    $dbname = ltrim($db_parsed['path'], '/');
    $user = $db_parsed['user'];
    $password = $db_parsed['pass'];

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS kas_rombongan (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL,
        amount BIGINT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die("Gagal koneksi ke database: " . $e->getMessage());
}

// ==========================================
// 2. LOGIKA SIMPAN, EDIT & HAPUS DATA
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = trim($_POST['name']);
            $status = $_POST['status'];
            $amount_str = str_replace('.', '', $_POST['amount']);
            $amount = (int) $amount_str;

            if (!empty($name) && $amount > 0) {
                $stmt = $pdo->prepare("INSERT INTO kas_rombongan (name, status, amount) VALUES (?, ?, ?)");
                $stmt->execute([$name, $status, $amount]);
            }
            header("Location: index.php?success=1");
            exit;
        } elseif ($_POST['action'] === 'edit') {
            $id = (int)$_POST['id'];
            $status = $_POST['status'];
            $amount_str = str_replace('.', '', $_POST['amount']);
            $amount = (int) $amount_str;

            if ($id > 0 && $amount > 0) {
                $stmt = $pdo->prepare("UPDATE kas_rombongan SET status = ?, amount = ? WHERE id = ?");
                $stmt->execute([$status, $amount, $id]);
            }
            header("Location: index.php?success_edit=1");
            exit;
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            if ($id > 0) {
                $stmt = $pdo->prepare("DELETE FROM kas_rombongan WHERE id = ?");
                $stmt->execute([$id]);
            }
            header("Location: index.php?success_del=1");
            exit;
        }
    }
}

// ==========================================
// 3. AMBIL DATA
// ==========================================
// DIURUTKAN BERDASARKAN NAMA DARI A SAMPAI Z
$stmt = $pdo->query("SELECT * FROM kas_rombongan ORDER BY name ASC");
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalTerkumpul = 0;
foreach ($transactions as $t) {
    $totalTerkumpul += $t['amount'];
}
?>
<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buku Kas Ziarah</title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                    },
                    colors: {
                        brand: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                            800: '#115e59',
                            900: '#134e4a',
                        }
                    }
                }
            }
        }
    </script>
    
    <script>
        if (localStorage.getItem('theme') === 'dark' || (!localStorage.getItem('theme') && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>

    <style>
        .modal-overlay {
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease-in-out;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            transform: scale(0.95) translateY(10px);
            opacity: 0;
            transition: all 0.3s ease-in-out;
        }
        .modal-overlay.active .modal-content {
            transform: scale(1) translateY(0);
            opacity: 1;
        }
        
        ::-webkit-scrollbar { width: 4px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .dark ::-webkit-scrollbar-thumb { background: #4b5563; }
        div:where(.swal2-container) { font-family: 'Plus Jakarta Sans', sans-serif !important; }
        
        /* CSS Untuk mengatur warna teks pada radio button card saat dicek */
        input:checked + div .radio-text {
            color: inherit !important;
        }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 font-sans antialiased transition-colors duration-300">
    <div class="max-w-md mx-auto bg-white dark:bg-gray-800 min-h-screen shadow-xl relative pb-20 transition-colors duration-300">
        
        <!-- HEADER -->
        <div class="bg-brand-600 dark:bg-brand-700 text-white rounded-b-[2rem] px-6 pt-12 pb-10 shadow-lg relative overflow-hidden transition-colors duration-300">
            <div class="absolute -top-10 -right-10 w-40 h-40 bg-white opacity-10 rounded-full blur-2xl"></div>
            
            <div class="relative z-10 flex justify-between items-start mb-6">
                <div>
                    <p class="text-brand-100 dark:text-brand-50 text-sm font-medium mb-1">Total Dana Terkumpul</p>
                    <h1 class="text-4xl font-extrabold tracking-tight">Rp <?= number_format($totalTerkumpul, 0, ',', '.') ?></h1>
                </div>
                
                <div class="flex gap-2 mt-1">
                    <button onclick="toggleTheme()" class="bg-white/20 hover:bg-white/30 p-2.5 rounded-xl backdrop-blur-sm transition-colors">
                        <svg id="themeIcon" class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"></svg>
                    </button>
                    <button onclick="openSettingsModal()" class="bg-white/20 hover:bg-white/30 p-2.5 rounded-xl backdrop-blur-sm transition-colors">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div id="displayEventTitle" class="relative z-10 text-lg font-bold mb-4">Buku Kas Ziarah</div>
            
            <div class="relative z-10">
                <div class="bg-black/10 dark:bg-black/20 rounded-full h-2 mb-2 overflow-hidden w-full backdrop-blur-sm">
                    <div id="progressBar" class="bg-white h-full rounded-full transition-all duration-700 ease-out shadow-[0_0_10px_rgba(255,255,255,0.7)]" style="width: 0%"></div>
                </div>
                <div class="flex justify-between text-xs font-semibold text-brand-50">
                    <span>Progres: <span id="progressText">0%</span></span>
                    <span>Target: Rp <span id="displayTargetBudget">0</span></span>
                </div>
                <div id="remainingInfo" class="mt-3 text-xs font-bold inline-block transition-all"></div>
            </div>
        </div>

        <!-- FORM TAMBAH DATA -->
        <div class="px-6 -mt-6 relative z-20">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl p-5 border border-gray-50/50 dark:border-gray-700/50 transition-colors duration-300">
                <h2 class="text-gray-800 dark:text-white font-bold text-lg mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-brand-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Catat Pemasukan
                </h2>
                
                <form onsubmit="submitForm(event)" action="index.php" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add">
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">NAMA JAMAAH</label>
                        <input type="text" name="name" required class="w-full bg-gray-50 dark:bg-gray-700 dark:text-white border-0 rounded-xl px-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-brand-500 outline-none transition-colors" placeholder="Masukkan nama...">
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-2">STATUS</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer relative">
                                <input type="radio" name="status" value="Lunas" class="peer sr-only" checked>
                                <div class="rounded-xl border-2 border-gray-100 dark:border-gray-600 p-3 text-center transition-all peer-checked:border-green-500 peer-checked:bg-green-50 dark:peer-checked:bg-green-900/30 text-gray-400 dark:text-gray-500 peer-checked:text-green-600 dark:peer-checked:text-green-400">
                                    <span class="font-bold text-sm">Lunas</span>
                                </div>
                            </label>
                            <label class="cursor-pointer relative">
                                <input type="radio" name="status" value="Cicil" class="peer sr-only">
                                <div class="rounded-xl border-2 border-gray-100 dark:border-gray-600 p-3 text-center transition-all peer-checked:border-orange-500 peer-checked:bg-orange-50 dark:peer-checked:bg-orange-900/30 text-gray-400 dark:text-gray-500 peer-checked:text-orange-600 dark:peer-checked:text-orange-400">
                                    <span class="font-bold text-sm">Cicil</span>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">NOMINAL</label>
                        <div class="relative">
                            <span class="absolute left-4 top-3 text-gray-400 dark:text-gray-400 font-bold">Rp</span>
                            <input type="text" name="amount" required class="w-full bg-gray-50 dark:bg-gray-700 dark:text-white border-0 rounded-xl pl-10 pr-4 py-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 outline-none transition-colors" placeholder="0" oninput="formatRupiah(this)">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3.5 rounded-xl shadow-[0_4px_14px_0_rgba(13,148,136,0.39)] transition transform active:scale-[0.98]">
                        Simpan Data
                    </button>
                </form>
            </div>
        </div>

        <!-- RIWAYAT TRANSAKSI -->
        <div class="px-6 mt-8">
            <h3 class="text-gray-800 dark:text-white font-bold mb-4 flex justify-between items-center text-lg transition-colors">
                Riwayat Pemasukan
                <span class="text-xs bg-brand-50 dark:bg-brand-900/40 text-brand-600 dark:text-brand-300 px-2.5 py-1 rounded-lg font-bold"><?= count($transactions) ?> Data</span>
            </h3>
            
            <!-- LIVE SEARCH BAR -->
            <div class="mb-4 relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="searchInput" oninput="debounceSearch()" placeholder="Cari Nama Jamaah..." class="w-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl pl-10 pr-4 py-3 text-sm font-semibold focus:ring-2 focus:ring-brand-500 outline-none transition-colors dark:text-white placeholder-gray-400 shadow-sm">
            </div>

            <div class="space-y-3" id="jamaahList">
                <?php if(empty($transactions)): ?>
                <div class="text-center text-gray-400 dark:text-gray-500 py-12 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border-2 border-dashed border-gray-100 dark:border-gray-700 font-medium text-sm transition-colors">
                    Belum ada data pemasukan.
                </div>
                <?php endif; ?>
                
                <div id="emptySearchMessage" class="hidden text-center text-gray-400 dark:text-gray-500 py-12 bg-gray-50 dark:bg-gray-800/50 rounded-2xl border-2 border-dashed border-gray-100 dark:border-gray-700 font-medium text-sm transition-colors">
                    Jamaah yang dicari tidak ditemukan.
                </div>

                <?php foreach($transactions as $t): ?>
                <div class="jamaah-item bg-white dark:bg-gray-800 p-4 rounded-2xl shadow-sm border border-gray-50 dark:border-gray-700 flex items-center justify-between group transition-colors" data-name="<?= strtolower(htmlspecialchars($t['name'])) ?>">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-brand-50 to-brand-100 dark:from-brand-900/30 dark:to-brand-800/30 text-brand-600 dark:text-brand-300 flex items-center justify-center font-bold text-xl shadow-inner shrink-0">
                            <?= strtoupper(substr($t['name'], 0, 1)) ?>
                        </div>
                        <div>
                            <p class="font-bold text-gray-800 dark:text-gray-100 text-sm truncate w-24 sm:w-32"><?= htmlspecialchars($t['name']) ?></p>
                            <div class="flex items-center gap-2 mt-1">
                                <?php if($t['status'] === 'Lunas'): ?>
                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-bold uppercase tracking-wider bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 shrink-0">
                                        <?= htmlspecialchars($t['status']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-bold uppercase tracking-wider bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-400 shrink-0">
                                        <?= htmlspecialchars($t['status']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-extrabold text-gray-800 dark:text-white text-sm mb-2">Rp <?= number_format($t['amount'], 0, ',', '.') ?></p>
                        
                        <div class="flex justify-end gap-2">
                            <!-- Tombol Edit -->
                            <button type="button" onclick="openEditModal(<?= $t['id'] ?>, '<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($t['status']) ?>', '<?= number_format($t['amount'], 0, '', '') ?>')" class="text-xs text-brand-600 dark:text-brand-400 hover:text-brand-800 font-semibold flex items-center gap-1 transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                Edit
                            </button>
                            <!-- Tombol Hapus -->
                            <form onsubmit="confirmDelete(event, '<?= htmlspecialchars($t['name'], ENT_QUOTES) ?>')" action="index.php" method="POST" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                <button type="submit" class="text-xs text-gray-400 dark:text-gray-500 hover:text-red-500 dark:hover:text-red-500 font-semibold flex items-center gap-1 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div> <!-- Wrapper Utama -->

    <!-- MODAL PENGATURAN -->
    <div id="settingsModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
        <div class="modal-content bg-white dark:bg-gray-800 w-full max-w-sm rounded-[2rem] p-6 shadow-2xl relative transition-colors duration-300">
            <button onclick="closeSettingsModal()" class="absolute right-5 top-5 text-gray-400 dark:text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-full p-2 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <h3 class="text-xl font-extrabold text-gray-800 dark:text-white mb-6">Pengaturan Aplikasi</h3>
            
            <div class="space-y-5">
                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-2">JUDUL ACARA / PROGRAM</label>
                    <input type="text" id="settingTitle" class="w-full bg-gray-50 dark:bg-gray-700 dark:text-white border-0 rounded-xl px-4 py-3.5 text-sm font-semibold focus:ring-2 focus:ring-brand-500 outline-none transition-colors" placeholder="Contoh: Buku Kas Ziarah">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-2">TARGET ANGGARAN (Rp)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3.5 text-gray-400 dark:text-gray-400 font-bold">Rp</span>
                        <input type="text" id="settingTarget" class="w-full bg-gray-50 dark:bg-gray-700 dark:text-white border-0 rounded-xl pl-10 pr-4 py-3.5 text-sm font-bold focus:ring-2 focus:ring-brand-500 outline-none transition-colors" placeholder="0" oninput="formatRupiah(this)">
                    </div>
                </div>
                <button onclick="saveSettings()" class="w-full bg-gray-900 dark:bg-black hover:bg-black dark:hover:bg-gray-900 text-white font-bold py-3.5 rounded-xl shadow-lg transition transform active:scale-[0.98] mt-2">
                    Simpan Perubahan
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL EDIT JAMAAH -->
    <div id="editModal" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-sm">
        <div class="modal-content bg-white dark:bg-gray-800 w-full max-w-sm rounded-[2rem] p-6 shadow-2xl relative transition-colors duration-300">
            <button onclick="closeEditModal()" class="absolute right-5 top-5 text-gray-400 dark:text-gray-500 hover:text-gray-800 dark:hover:text-gray-200 bg-gray-50 dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 rounded-full p-2 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            <h3 class="text-xl font-extrabold text-gray-800 dark:text-white mb-2">Edit Pemasukan</h3>
            <p id="editNameDisplay" class="text-sm font-semibold text-gray-500 dark:text-gray-400 mb-6 border-b dark:border-gray-700 pb-2"></p>
            
            <form onsubmit="submitForm(event)" action="index.php" method="POST" class="space-y-5">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="editId">
                
                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-2">STATUS</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="cursor-pointer relative">
                            <input type="radio" name="status" id="editStatusLunas" value="Lunas" class="peer sr-only">
                            <div class="rounded-xl border-2 border-gray-100 dark:border-gray-600 p-3 text-center transition-all peer-checked:border-green-500 peer-checked:bg-green-50 dark:peer-checked:bg-green-900/30 text-gray-400 dark:text-gray-500 peer-checked:text-green-600 dark:peer-checked:text-green-400">
                                <span class="font-bold text-sm">Lunas</span>
                            </div>
                        </label>
                        <label class="cursor-pointer relative">
                            <input type="radio" name="status" id="editStatusCicil" value="Cicil" class="peer sr-only">
                            <div class="rounded-xl border-2 border-gray-100 dark:border-gray-600 p-3 text-center transition-all peer-checked:border-orange-500 peer-checked:bg-orange-50 dark:peer-checked:bg-orange-900/30 text-gray-400 dark:text-gray-500 peer-checked:text-orange-600 dark:peer-checked:text-orange-400">
                                <span class="font-bold text-sm">Cicil</span>
                            </div>
                        </label>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-500 dark:text-gray-400 mb-1">NOMINAL (Rp)</label>
                    <div class="relative">
                        <span class="absolute left-4 top-3 text-gray-400 dark:text-gray-400 font-bold">Rp</span>
                        <input type="text" name="amount" id="editAmount" required class="w-full bg-gray-50 dark:bg-gray-700 dark:text-white border-0 rounded-xl pl-10 pr-4 py-3 text-sm font-bold focus:ring-2 focus:ring-brand-500 outline-none transition-colors" placeholder="0" oninput="formatRupiah(this)">
                    </div>
                </div>
                
                <button type="submit" class="w-full bg-brand-600 hover:bg-brand-700 text-white font-bold py-3.5 rounded-xl shadow-[0_4px_14px_0_rgba(13,148,136,0.39)] transition transform active:scale-[0.98] mt-2">
                    Simpan Perubahan
                </button>
            </form>
        </div>
    </div>

    <!-- SCRIPT JS -->
    <script>
        const totalTerkumpul = <?= $totalTerkumpul ?>;
        const DEFAULT_TITLE = "Buku Kas Ziarah";
        const DEFAULT_TARGET = 5000000;

        document.addEventListener('DOMContentLoaded', () => {
            loadSettings();
            updateDashboard();
            updateThemeIcon();
            
            // SweetAlert Notifikasi URL Parameters
            const urlParams = new URLSearchParams(window.location.search);
            const isDark = document.documentElement.classList.contains('dark');
            const bgAlert = isDark ? '#1f2937' : '#ffffff';
            const colorAlert = isDark ? '#f3f4f6' : '#1f2937';

            if(urlParams.has('success')) {
                showToastAlert('success', 'Data pemasukan berhasil dicatat.');
            }
            if(urlParams.has('success_edit')) {
                showToastAlert('success', 'Data pemasukan berhasil diperbarui.');
            }
            if(urlParams.has('success_del')) {
                showToastAlert('success', 'Data riwayat telah dihapus.');
            }
            
            function showToastAlert(icon, text) {
                Swal.fire({
                    icon: icon,
                    title: icon === 'success' ? 'Berhasil!' : 'Selesai',
                    text: text,
                    background: bgAlert,
                    color: colorAlert,
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
                window.history.replaceState(null, '', window.location.pathname);
            }
        });

        // 1. Live Search Logic
        let searchTimeout;
        let isSearchAlertShown = false;

        function debounceSearch() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(searchJamaah, 300);
        }

        function searchJamaah() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const items = document.querySelectorAll('.jamaah-item');
            const emptySearchMsg = document.getElementById('emptySearchMessage');
            let foundCount = 0;

            items.forEach(item => {
                const name = item.getAttribute('data-name');
                if (name.includes(query)) {
                    item.style.display = 'flex';
                    foundCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (foundCount === 0 && query !== '') {
                emptySearchMsg.style.display = 'block';
                
                // Munculkan popup warning jika belum dimunculkan untuk pencarian ini
                if (!isSearchAlertShown) {
                    const isDark = document.documentElement.classList.contains('dark');
                    Swal.fire({
                        icon: 'warning',
                        title: 'Tidak Ditemukan',
                        text: 'Jamaah yang dicari tidak ada dalam daftar.',
                        background: isDark ? '#1f2937' : '#ffffff',
                        color: isDark ? '#f3f4f6' : '#1f2937',
                        toast: true,
                        position: 'top',
                        timer: 2000,
                        showConfirmButton: false
                    });
                    isSearchAlertShown = true;
                }
            } else {
                emptySearchMsg.style.display = 'none';
                isSearchAlertShown = false; // Reset jika ada yang ditemukan / query kosong
            }
        }

        // 2. Dark/Light Mode Logic
        function toggleTheme() {
            const html = document.documentElement;
            if (html.classList.contains('dark')) {
                html.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            } else {
                html.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            }
            updateThemeIcon();
        }

        function updateThemeIcon() {
            const isDark = document.documentElement.classList.contains('dark');
            const iconEl = document.getElementById('themeIcon');
            if (isDark) {
                iconEl.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>`;
            } else {
                iconEl.innerHTML = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>`;
            }
        }

        // 3. SweetAlert pada Form Submissions
        function submitForm(event) {
            event.preventDefault();
            const isDark = document.documentElement.classList.contains('dark');
            Swal.fire({
                title: 'Menyimpan Data...',
                text: 'Harap tunggu sebentar',
                background: isDark ? '#1f2937' : '#ffffff',
                color: isDark ? '#f3f4f6' : '#1f2937',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            setTimeout(() => {
                event.target.submit();
            }, 300); 
        }

        function confirmDelete(event, name) {
            event.preventDefault();
            const isDark = document.documentElement.classList.contains('dark');
            Swal.fire({
                title: 'Hapus Riwayat?',
                text: `Yakin ingin menghapus riwayat atas nama ${name}?`,
                icon: 'warning',
                background: isDark ? '#1f2937' : '#ffffff',
                color: isDark ? '#f3f4f6' : '#1f2937',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: isDark ? '#4b5563' : '#9ca3af',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Menghapus...',
                        background: isDark ? '#1f2937' : '#ffffff',
                        color: isDark ? '#f3f4f6' : '#1f2937',
                        allowOutsideClick: false,
                        didOpen: () => Swal.showLoading()
                    });
                    event.target.submit();
                }
            });
        }

        // 4. Format Rupiah
        function formatRupiah(input) {
            let value = input.value.replace(/[^,\d]/g, '').toString();
            let split = value.split(',');
            let sisa = split[0].length % 3;
            let rupiah = split[0].substr(0, sisa);
            let ribuan = split[0].substr(sisa).match(/\d{3}/gi);
            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            input.value = rupiah;
        }

        function formatRupiahNumber(angka) {
            return new Intl.NumberFormat('id-ID').format(angka);
        }

        // 5. Pengaturan & Dashboard
        function loadSettings() {
            const title = localStorage.getItem('appTitle') || DEFAULT_TITLE;
            const target = localStorage.getItem('appTarget') || DEFAULT_TARGET;
            
            document.getElementById('displayEventTitle').innerText = title;
            document.getElementById('displayTargetBudget').innerText = formatRupiahNumber(target);
            
            document.getElementById('settingTitle').value = title;
            document.getElementById('settingTarget').value = formatRupiahNumber(target);
        }

        function saveSettings() {
            const title = document.getElementById('settingTitle').value.trim();
            const targetVal = document.getElementById('settingTarget').value.replace(/\./g, ''); 
            
            if(title) localStorage.setItem('appTitle', title);
            if(targetVal) localStorage.setItem('appTarget', targetVal);
            
            loadSettings();
            updateDashboard(); 
            closeSettingsModal();
            
            const isDark = document.documentElement.classList.contains('dark');
            Swal.fire({
                icon: 'success',
                title: 'Tersimpan',
                toast: true,
                position: 'top-end',
                background: isDark ? '#1f2937' : '#ffffff',
                color: isDark ? '#f3f4f6' : '#1f2937',
                showConfirmButton: false,
                timer: 1500
            });
        }

        function updateDashboard() {
            const target = parseInt(localStorage.getItem('appTarget') || DEFAULT_TARGET);
            let percentage = 0;
            if(target > 0) percentage = Math.min((totalTerkumpul / target) * 100, 100);
            
            document.getElementById('progressBar').style.width = percentage + '%';
            document.getElementById('progressText').innerText = percentage.toFixed(1) + '%';
            
            const remaining = target - totalTerkumpul;
            const infoEl = document.getElementById('remainingInfo');
            
            if (remaining > 0) {
                infoEl.innerHTML = `Kekurangan: Rp ${formatRupiahNumber(remaining)}`;
                infoEl.className = "mt-3 text-[11px] font-bold bg-white/20 dark:bg-black/30 text-white px-3 py-1.5 rounded-lg backdrop-blur-md border border-white/10 dark:border-white/5";
            } else if (remaining < 0) {
                infoEl.innerHTML = `Kelebihan: Rp ${formatRupiahNumber(Math.abs(remaining))}`;
                infoEl.className = "mt-3 text-[11px] font-bold bg-brand-100 dark:bg-brand-900/60 text-brand-700 dark:text-brand-300 px-3 py-1.5 rounded-lg shadow-sm";
            } else {
                infoEl.innerHTML = `🎉 Target Terpenuhi!`;
                infoEl.className = "mt-3 text-[11px] font-bold bg-brand-100 dark:bg-brand-900/60 text-brand-700 dark:text-brand-300 px-3 py-1.5 rounded-lg shadow-sm";
            }
        }

        function openSettingsModal() { document.getElementById('settingsModal').classList.add('active'); }
        function closeSettingsModal() { document.getElementById('settingsModal').classList.remove('active'); }
        
        function openEditModal(id, name, status, amount) {
            document.getElementById('editId').value = id;
            document.getElementById('editNameDisplay').innerText = `Jamaah: ${name}`;
            
            if (status === 'Lunas') {
                document.getElementById('editStatusLunas').checked = true;
            } else {
                document.getElementById('editStatusCicil').checked = true;
            }
            
            const amountInput = document.getElementById('editAmount');
            amountInput.value = amount;
            formatRupiah(amountInput); // Format ke titik Rupiah
            
            document.getElementById('editModal').classList.add('active');
        }
        function closeEditModal() { document.getElementById('editModal').classList.remove('active'); }
    </script>
</body>
</html>