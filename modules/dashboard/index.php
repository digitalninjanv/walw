<?php
// modules/dashboard/index.php
check_user_level(['admin', 'petugas']); // Hanya admin dan petugas yang akses dashboard ini

$user_id = $_SESSION['user_id'];
$user_level = $_SESSION['user_level'];
$user_nama = $_SESSION['user_nama'];

// Data default
$jumlah_warga = 0;
$jumlah_jenis_sampah = 0;
$total_berat_setoran_bulan_ini = 0;
$total_saldo_bank_sampah = 0;
$aktivitas_terbaru = [];

if ($user_level == 'admin' || $user_level == 'petugas') {
    // Ambil data untuk Admin/Petugas
    // Jumlah Warga
    $query_warga = "SELECT COUNT(*) AS total FROM pengguna WHERE level = 'warga'";
    $result_warga = mysqli_query($koneksi, $query_warga);
    if($result_warga) $jumlah_warga = mysqli_fetch_assoc($result_warga)['total'];

    // Jumlah Jenis Sampah
    $query_jenis = "SELECT COUNT(*) AS total FROM jenis_sampah";
    $result_jenis = mysqli_query($koneksi, $query_jenis);
    if($result_jenis) $jumlah_jenis_sampah = mysqli_fetch_assoc($result_jenis)['total'];

    // Total Berat Setoran Bulan Ini
    $bulan_ini_awal = date('Y-m-01 00:00:00');
    $bulan_ini_akhir = date('Y-m-t 23:59:59');
    $query_berat = "SELECT SUM(ds.berat_kg) AS total_berat
                    FROM detail_setoran ds
                    JOIN transaksi t ON ds.id_transaksi_setor = t.id_transaksi
                    WHERE t.tanggal_transaksi BETWEEN ? AND ?";
    $stmt_berat = mysqli_prepare($koneksi, $query_berat);
    mysqli_stmt_bind_param($stmt_berat, "ss", $bulan_ini_awal, $bulan_ini_akhir);
    mysqli_stmt_execute($stmt_berat);
    $result_berat = mysqli_stmt_get_result($stmt_berat);
    if($result_berat) {
        $data_berat = mysqli_fetch_assoc($result_berat);
        $total_berat_setoran_bulan_ini = $data_berat['total_berat'] ? $data_berat['total_berat'] : 0;
    }
    mysqli_stmt_close($stmt_berat);

    // Total Saldo Bank Sampah (akumulasi saldo semua warga)
    $query_saldo_total = "SELECT SUM(saldo) AS total_saldo FROM pengguna WHERE level = 'warga'";
    $result_saldo_total = mysqli_query($koneksi, $query_saldo_total);
    if($result_saldo_total) $total_saldo_bank_sampah = mysqli_fetch_assoc($result_saldo_total)['total_saldo'] ?: 0;

    // Aktivitas Terbaru (5 transaksi terakhir)
    $query_aktivitas = "
        SELECT t.id_transaksi, t.tanggal_transaksi, t.tipe_transaksi, t.total_nilai,
               warga.nama_lengkap as nama_warga, petugas.nama_lengkap as nama_petugas
        FROM transaksi t
        JOIN pengguna warga ON t.id_warga = warga.id_pengguna
        JOIN pengguna petugas ON t.id_petugas_pencatat = petugas.id_pengguna
        ORDER BY t.tanggal_transaksi DESC
        LIMIT 5
    ";
    $result_aktivitas = mysqli_query($koneksi, $query_aktivitas);
    if($result_aktivitas){
        while($row = mysqli_fetch_assoc($result_aktivitas)){
            $aktivitas_terbaru[] = $row;
        }
    }
}

// Karena warga tidak lagi login ke dashboard ini, bagian elseif ($user_level == 'warga') bisa dihapus.
// Kode di bawah ini khusus untuk admin dan petugas.

?>

<div class="container mx-auto max-w-screen-2xl px-4 sm:px-6 lg:px-10 py-8 space-y-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs font-semibold tracking-[0.2em] text-slate-500 uppercase">Ringkasan Sistem</p>
            <h1 class="text-3xl md:text-4xl font-black text-gray-800 leading-tight">Dashboard Utama</h1>
        </div>
        <div class="flex flex-wrap gap-2 text-xs sm:text-sm">
            <span class="px-3 py-1 rounded-full border border-slate-200 bg-white shadow-sm text-slate-600 flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span> Stabil &amp; realtime
            </span>
            <span class="px-3 py-1 rounded-full border border-slate-200 bg-white shadow-sm text-slate-600">Siap untuk data besar</span>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 lg:gap-6">
        <div class="bg-gradient-to-br from-sky-500 to-sky-600 p-5 lg:p-6 rounded-2xl shadow-lg text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 20% 20%, rgba(255,255,255,0.35), transparent 45%), radial-gradient(circle at 80% 0%, rgba(255,255,255,0.25), transparent 40%);"></div>
            <div class="relative flex items-center justify-between gap-3">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] opacity-85">Jumlah Warga</p>
                    <p class="text-4xl font-black leading-tight"><?php echo $jumlah_warga; ?></p>
                    <span class="inline-flex items-center gap-1 text-xs bg-white/20 px-2 py-1 rounded-full">
                        <i class="fas fa-users"></i> Warga aktif
                    </span>
                </div>
                <div class="w-12 h-12 lg:w-14 lg:h-14 rounded-2xl bg-white/15 flex items-center justify-center">
                    <i class="fas fa-users fa-lg opacity-80"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-amber-500 to-amber-600 p-5 lg:p-6 rounded-2xl shadow-lg text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-15" style="background-image: radial-gradient(circle at 25% 25%, rgba(255,255,255,0.4), transparent 45%), radial-gradient(circle at 70% 0%, rgba(255,255,255,0.25), transparent 40%);"></div>
            <div class="relative flex items-center justify-between gap-3">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] opacity-90">Jenis Sampah</p>
                    <p class="text-4xl font-black leading-tight"><?php echo $jumlah_jenis_sampah; ?></p>
                    <span class="inline-flex items-center gap-1 text-xs bg-white/20 px-2 py-1 rounded-full">
                        <i class="fas fa-tag"></i> Kategori aktif
                    </span>
                </div>
                <div class="w-12 h-12 lg:w-14 lg:h-14 rounded-2xl bg-white/15 flex items-center justify-center">
                    <i class="fas fa-dumpster fa-lg opacity-80"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 p-5 lg:p-6 rounded-2xl shadow-lg text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.38), transparent 45%), radial-gradient(circle at 75% 5%, rgba(255,255,255,0.25), transparent 40%);"></div>
            <div class="relative flex items-center justify-between gap-3">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] opacity-90">Setoran Bulan Ini</p>
                    <p class="text-3xl lg:text-4xl font-black leading-tight"><?php echo number_format($total_berat_setoran_bulan_ini, 2, ',', '.'); ?> Kg</p>
                    <span class="inline-flex items-center gap-1 text-xs bg-white/20 px-2 py-1 rounded-full">
                        <i class="fas fa-clock"></i> Real-time
                    </span>
                </div>
                <div class="w-12 h-12 lg:w-14 lg:h-14 rounded-2xl bg-white/15 flex items-center justify-center">
                    <i class="fas fa-weight-hanging fa-lg opacity-80"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 p-5 lg:p-6 rounded-2xl shadow-lg text-white relative overflow-hidden">
            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 15% 30%, rgba(255,255,255,0.35), transparent 45%), radial-gradient(circle at 85% 15%, rgba(255,255,255,0.22), transparent 40%);"></div>
            <div class="relative flex items-center justify-between gap-3">
                <div class="space-y-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.15em] opacity-90">Total Saldo Bank</p>
                    <p class="text-3xl lg:text-4xl font-black leading-tight"><?php echo format_rupiah($total_saldo_bank_sampah); ?></p>
                    <span class="inline-flex items-center gap-1 text-xs bg-white/20 px-2 py-1 rounded-full">
                        <i class="fas fa-wallet"></i> Akumulasi saldo
                    </span>
                </div>
                <div class="w-12 h-12 lg:w-14 lg:h-14 rounded-2xl bg-white/15 flex items-center justify-center">
                    <i class="fas fa-wallet fa-lg opacity-80"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8">
        <div class="xl:col-span-2 bg-white p-5 sm:p-6 rounded-2xl shadow-xl border border-slate-100">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                <h2 class="text-xl font-semibold text-gray-800 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-sky-100 text-sky-600 flex items-center justify-center"><i class="fas fa-stream"></i></span>
                    Aktivitas Transaksi Terbaru
                </h2>
                <a href="<?php echo BASE_URL; ?>index.php?page=transaksi/riwayat" class="text-sm font-semibold text-sky-600 hover:text-sky-800 flex items-center gap-2">
                    Lihat semua <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <?php if (!empty($aktivitas_terbaru)): ?>
                <div class="space-y-4 max-h-[26rem] overflow-y-auto pr-1">
                    <?php foreach($aktivitas_terbaru as $aktivitas): ?>
                        <div class="flex items-start gap-3 p-3 border border-gray-100 rounded-xl hover:shadow-md transition-shadow duration-200 bg-slate-50/60">
                            <div class="flex-shrink-0 mt-1">
                                <?php if ($aktivitas['tipe_transaksi'] == 'setor'): ?>
                                    <span class="w-9 h-9 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center ring-1 ring-emerald-200">
                                        <i class="fas fa-arrow-down"></i>
                                    </span>
                                <?php else: ?>
                                    <span class="w-9 h-9 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center ring-1 ring-orange-200">
                                        <i class="fas fa-arrow-up"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="space-y-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 leading-snug">
                                    <?php echo ($aktivitas['tipe_transaksi'] == 'setor' ? 'Setoran baru dari ' : 'Penarikan oleh '); ?>
                                    <span class="text-sky-600"><?php echo htmlspecialchars($aktivitas['nama_warga']); ?></span>
                                    sebesar <span class="text-gray-900"><?php echo format_rupiah($aktivitas['total_nilai']); ?></span>.
                                </p>
                                <p class="text-xs text-gray-500 flex flex-wrap gap-2">
                                    <span class="inline-flex items-center gap-1"><i class="far fa-calendar"></i> <?php echo format_tanggal_indonesia($aktivitas['tanggal_transaksi']); ?></span>
                                    <span class="inline-flex items-center gap-1 text-gray-600"><i class="far fa-user"></i> Dicatat oleh <?php echo htmlspecialchars($aktivitas['nama_petugas']); ?></span>
                                </p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-10">
                    <i class="fas fa-folder-open fa-3x text-gray-300 mb-3"></i>
                    <p class="text-gray-500">Belum ada aktivitas transaksi terbaru.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="bg-white p-5 sm:p-6 rounded-2xl shadow-xl border border-slate-100 space-y-4">
            <div class="flex items-center gap-3">
                <span class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center"><i class="fas fa-bolt"></i></span>
                <div>
                    <p class="text-xs font-semibold tracking-[0.15em] text-slate-500 uppercase">Aksi cepat</p>
                    <h2 class="text-xl font-semibold text-gray-800">Pintasan Cepat</h2>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3">
                <a href="<?php echo BASE_URL; ?>index.php?page=transaksi/setor" class="flex items-center w-full text-left px-4 py-3 rounded-xl bg-green-500 text-white hover:bg-green-600 focus:bg-green-600 transition duration-200 shadow hover:shadow-lg transform hover:-translate-y-0.5">
                    <i class="fas fa-plus-circle fa-lg mr-3"></i>
                    <div class="min-w-0">
                        <span class="font-semibold block">Input Setoran Sampah</span>
                        <p class="text-xs opacity-80 truncate">Catat setoran baru dari warga.</p>
                    </div>
                </a>
                <a href="<?php echo BASE_URL; ?>index.php?page=transaksi/tarik_saldo" class="flex items-center w-full text-left px-4 py-3 rounded-xl bg-orange-500 text-white hover:bg-orange-600 focus:bg-orange-600 transition duration-200 shadow hover:shadow-lg transform hover:-translate-y-0.5">
                    <i class="fas fa-money-bill-wave fa-lg mr-3"></i>
                    <div class="min-w-0">
                        <span class="font-semibold block">Input Tarik Saldo</span>
                        <p class="text-xs opacity-80 truncate">Proses penarikan saldo warga.</p>
                    </div>
                </a>
                <a href="<?php echo BASE_URL; ?>index.php?page=warga/tambah" class="flex items-center w-full text-left px-4 py-3 rounded-xl bg-sky-500 text-white hover:bg-sky-600 focus:bg-sky-600 transition duration-200 shadow hover:shadow-lg transform hover:-translate-y-0.5">
                    <i class="fas fa-user-plus fa-lg mr-3"></i>
                    <div class="min-w-0">
                        <span class="font-semibold block">Tambah Warga Baru</span>
                        <p class="text-xs opacity-80 truncate">Daftarkan warga baru ke sistem.</p>
                    </div>
                </a>
                <a href="<?php echo BASE_URL; ?>index.php?page=jenis_sampah/tambah" class="flex items-center w-full text-left px-4 py-3 rounded-xl bg-amber-500 text-white hover:bg-amber-600 focus:bg-amber-600 transition duration-200 shadow hover:shadow-lg transform hover:-translate-y-0.5">
                    <i class="fas fa-tag fa-lg mr-3"></i>
                    <div class="min-w-0">
                        <span class="font-semibold block">Tambah Jenis Sampah</span>
                        <p class="text-xs opacity-80 truncate">Kelola daftar jenis sampah.</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>
