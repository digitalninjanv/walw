<?php
// modules/laporan/harian.php (Hanya untuk tampilan)
check_user_level(['admin', 'petugas']);

$tanggal_laporan = isset($_GET['tanggal']) ? sanitize_input($_GET['tanggal']) : date('Y-m-d');

// Query untuk mengambil data setoran harian
$query_setoran_harian = "
    SELECT 
        t.id_transaksi, 
        p_warga.nama_lengkap AS nama_warga, 
        p_petugas.nama_lengkap AS nama_petugas, 
        t.tanggal_transaksi, 
        t.total_nilai,
        GROUP_CONCAT(DISTINCT CONCAT(js.nama_sampah, ' (', ds.berat_kg, ' ', js.satuan, ')') SEPARATOR '; ') AS detail_item_setoran,
        t.keterangan AS keterangan_transaksi
    FROM transaksi t
    JOIN pengguna p_warga ON t.id_warga = p_warga.id_pengguna
    JOIN pengguna p_petugas ON t.id_petugas_pencatat = p_petugas.id_pengguna
    LEFT JOIN detail_setoran ds ON t.id_transaksi = ds.id_transaksi_setor
    LEFT JOIN jenis_sampah js ON ds.id_jenis_sampah = js.id_jenis_sampah
    WHERE DATE(t.tanggal_transaksi) = ? AND t.tipe_transaksi = 'setor'
    GROUP BY t.id_transaksi
    ORDER BY t.tanggal_transaksi DESC
";
$stmt_setoran = mysqli_prepare($koneksi, $query_setoran_harian);
mysqli_stmt_bind_param($stmt_setoran, "s", $tanggal_laporan);
mysqli_stmt_execute($stmt_setoran);
$result_setoran = mysqli_stmt_get_result($stmt_setoran);
$setoran_rows = [];
if ($result_setoran) {
    while ($row = mysqli_fetch_assoc($result_setoran)) {
        $setoran_rows[] = $row;
    }
}

// Query untuk mengambil data penarikan harian
$query_penarikan_harian = "
    SELECT 
        t.id_transaksi, 
        p_warga.nama_lengkap AS nama_warga, 
        p_petugas.nama_lengkap AS nama_petugas, 
        t.tanggal_transaksi, 
        t.total_nilai,
        t.keterangan AS keterangan_transaksi
    FROM transaksi t
    JOIN pengguna p_warga ON t.id_warga = p_warga.id_pengguna
    JOIN pengguna p_petugas ON t.id_petugas_pencatat = p_petugas.id_pengguna
    WHERE DATE(t.tanggal_transaksi) = ? AND t.tipe_transaksi = 'tarik_saldo'
    ORDER BY t.tanggal_transaksi DESC
";
$stmt_penarikan = mysqli_prepare($koneksi, $query_penarikan_harian);
mysqli_stmt_bind_param($stmt_penarikan, "s", $tanggal_laporan);
mysqli_stmt_execute($stmt_penarikan);
$result_penarikan = mysqli_stmt_get_result($stmt_penarikan);
$penarikan_rows = [];
if ($result_penarikan) {
    while ($row = mysqli_fetch_assoc($result_penarikan)) {
        $penarikan_rows[] = $row;
    }
}

// Hitung total pemasukan
$total_pemasukan_hari_ini = 0;
$query_total_pemasukan = "SELECT SUM(total_nilai) AS total FROM transaksi WHERE DATE(tanggal_transaksi) = ? AND tipe_transaksi = 'setor'";
$stmt_total_pemasukan = mysqli_prepare($koneksi, $query_total_pemasukan);
mysqli_stmt_bind_param($stmt_total_pemasukan, "s", $tanggal_laporan);
mysqli_stmt_execute($stmt_total_pemasukan);
$result_total_pemasukan = mysqli_stmt_get_result($stmt_total_pemasukan);
if($data_total_pemasukan = mysqli_fetch_assoc($result_total_pemasukan)) {
    $total_pemasukan_hari_ini = $data_total_pemasukan['total'] ?: 0;
}
mysqli_stmt_close($stmt_total_pemasukan);

// Hitung total pengeluaran
$total_pengeluaran_hari_ini = 0;
$query_total_pengeluaran = "SELECT SUM(total_nilai) AS total FROM transaksi WHERE DATE(tanggal_transaksi) = ? AND tipe_transaksi = 'tarik_saldo'";
$stmt_total_pengeluaran = mysqli_prepare($koneksi, $query_total_pengeluaran);
mysqli_stmt_bind_param($stmt_total_pengeluaran, "s", $tanggal_laporan);
mysqli_stmt_execute($stmt_total_pengeluaran);
$result_total_pengeluaran = mysqli_stmt_get_result($stmt_total_pengeluaran);
if($data_total_pengeluaran = mysqli_fetch_assoc($result_total_pengeluaran)) {
    $total_pengeluaran_hari_ini = $data_total_pengeluaran['total'] ?: 0;
}
mysqli_stmt_close($stmt_total_pengeluaran);

?>
<div class="container mx-auto px-4 py-8">
    <div class="bg-gradient-to-br from-sky-500/10 via-indigo-500/10 to-emerald-500/10 border border-sky-100 rounded-2xl p-5 sm:p-6 shadow-xl mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.2em] text-sky-600 font-semibold">Laporan Harian</p>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Rekap <?php echo format_tanggal_indonesia($tanggal_laporan, false); ?></h1>
            <p class="text-sm text-gray-600 mt-1">Pantau setoran & penarikan harian dengan layout ringkas di layar kecil.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="px-3 py-1 bg-white text-sky-600 border border-sky-100 rounded-full text-xs font-semibold shadow-sm">Export ready</span>
            <span class="px-3 py-1 bg-white text-emerald-600 border border-emerald-100 rounded-full text-xs font-semibold shadow-sm">Mobile first</span>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-3 lg:items-center lg:justify-between mb-6">
        <form method="GET" action="<?php echo BASE_URL; ?>index.php" class="flex flex-col sm:flex-row sm:items-center gap-3 bg-white p-4 rounded-2xl shadow-lg border border-gray-100 flex-1">
            <input type="hidden" name="page" value="laporan/harian">
            <div class="flex flex-1 items-center gap-2">
                <label for="tanggal_laporan_input" class="text-sm font-semibold text-gray-700 whitespace-nowrap">Pilih Tanggal</label>
                <input type="date" name="tanggal" id="tanggal_laporan_input" value="<?php echo $tanggal_laporan; ?>"
                       class="flex-1 px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 text-sm">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-gradient-to-r from-sky-500 to-indigo-600 hover:shadow-lg text-white font-semibold py-2.5 px-4 rounded-xl text-sm flex items-center gap-2">
                    <i class="fas fa-search"></i> <span>Tampilkan</span>
                </button>
                <a href="<?php echo BASE_URL; ?>index.php?page=laporan/export&report_type=harian&tanggal=<?php echo $tanggal_laporan; ?>"
                   class="bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-2.5 px-4 rounded-xl text-sm flex items-center gap-2 shadow-md">
                    <i class="fas fa-file-excel"></i> Ekspor
                </a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 mb-8">
        <div class="bg-white p-5 rounded-2xl shadow-lg border border-emerald-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Total Pemasukan</p>
                    <p class="text-2xl md:text-3xl font-bold text-gray-800 mt-1"><?php echo format_rupiah($total_pemasukan_hari_ini); ?></p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="fas fa-arrow-down"></i>
                </div>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-lg border border-rose-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">Total Pengeluaran</p>
                    <p class="text-2xl md:text-3xl font-bold text-gray-800 mt-1"><?php echo format_rupiah($total_pengeluaran_hari_ini); ?></p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                    <i class="fas fa-arrow-up"></i>
                </div>
            </div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-lg border border-sky-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-600">Selisih</p>
                    <p class="text-2xl md:text-3xl font-bold text-gray-800 mt-1"><?php echo format_rupiah($total_pemasukan_hari_ini - $total_pengeluaran_hari_ini); ?></p>
                </div>
                <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                    <i class="fas fa-balance-scale"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel Setoran -->
    <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100 mb-6">
        <h2 class="text-lg sm:text-xl font-semibold text-gray-800 p-4 sm:p-5 bg-gray-50 border-b flex items-center gap-2">
            <i class="fas fa-arrow-down-wide-short text-emerald-500"></i>
            Detail Setoran Tanggal <?php echo format_tanggal_indonesia($tanggal_laporan, false); ?>
        </h2>
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Waktu</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Warga</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Petugas</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Detail Item</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total Nilai</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($setoran_rows)): ?>
                        <?php foreach($setoran_rows as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo date('H:i', strtotime($row['tanggal_transaksi'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($row['nama_warga']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($row['nama_petugas']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500 max-w-md">
                                <?php
                                $items = explode('; ', $row['detail_item_setoran']);
                                if (!empty($row['detail_item_setoran'])) {
                                    echo "<ul class='list-disc list-inside text-xs'>";
                                    foreach ($items as $item) {
                                        echo "<li>" . htmlspecialchars($item) . "</li>";
                                    }
                                    echo "</ul>";
                                } else {
                                    echo "-";
                                }
                                if(!empty($row['keterangan_transaksi'])) echo "<p class='mt-1 text-xs italic'>Ket: " . htmlspecialchars($row['keterangan_transaksi']) . "</p>";
                                ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-medium"><?php echo format_rupiah($row['total_nilai']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-500">Tidak ada data setoran pada tanggal ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 md:hidden p-4">
            <?php if (!empty($setoran_rows)): ?>
                <?php foreach($setoran_rows as $row): ?>
                    <div class="border border-emerald-100 rounded-xl p-3 bg-emerald-50/40">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs text-gray-500"><?php echo date('H:i', strtotime($row['tanggal_transaksi'])); ?></p>
                                <p class="text-sm font-semibold text-gray-900 leading-tight"><?php echo htmlspecialchars($row['nama_warga']); ?></p>
                                <p class="text-xs text-gray-500">Petugas: <?php echo htmlspecialchars($row['nama_petugas']); ?></p>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-white text-emerald-700 text-xs font-semibold shadow-sm">Setor</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2 text-sm text-gray-700 font-semibold">
                            <i class="fas fa-wallet text-emerald-500"></i>
                            <span><?php echo format_rupiah($row['total_nilai']); ?></span>
                        </div>
                        <div class="mt-2 text-xs text-gray-600 space-y-1">
                            <?php
                            $items = explode('; ', $row['detail_item_setoran']);
                            if (!empty($row['detail_item_setoran'])) {
                                echo "<div class='flex flex-wrap gap-2'>";
                                foreach ($items as $item) {
                                    echo "<span class='px-3 py-1 rounded-full bg-white text-emerald-700 border border-emerald-100 font-semibold'>" . htmlspecialchars($item) . "</span>";
                                }
                                echo "</div>";
                            }
                            if(!empty($row['keterangan_transaksi'])) echo "<p class='italic'>Ket: " . htmlspecialchars($row['keterangan_transaksi']) . "</p>";
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-gray-500 text-sm">Tidak ada data setoran pada tanggal ini.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if($stmt_setoran) mysqli_stmt_close($stmt_setoran); ?>

    <!-- Tabel Penarikan -->
    <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
        <h2 class="text-lg sm:text-xl font-semibold text-gray-800 p-4 sm:p-5 bg-gray-50 border-b flex items-center gap-2">
            <i class="fas fa-arrow-up-short-wide text-rose-500"></i>
            Detail Penarikan Saldo Tanggal <?php echo format_tanggal_indonesia($tanggal_laporan, false); ?>
        </h2>
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                 <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Waktu</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Warga</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Petugas</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Keterangan</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Jumlah Ditarik</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($penarikan_rows)): ?>
                        <?php foreach($penarikan_rows as $row): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo date('H:i', strtotime($row['tanggal_transaksi'])); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($row['nama_warga']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($row['nama_petugas']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500 max-w-md"><?php echo htmlspecialchars($row['keterangan_transaksi'] ? $row['keterangan_transaksi'] : '-'); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-medium"><?php echo format_rupiah($row['total_nilai']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" class="text-center py-4 text-gray-500">Tidak ada data penarikan pada tanggal ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 md:hidden p-4">
            <?php if (!empty($penarikan_rows)): ?>
                <?php foreach($penarikan_rows as $row): ?>
                    <div class="border border-rose-100 rounded-xl p-3 bg-rose-50/40">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs text-gray-500"><?php echo date('H:i', strtotime($row['tanggal_transaksi'])); ?></p>
                                <p class="text-sm font-semibold text-gray-900 leading-tight"><?php echo htmlspecialchars($row['nama_warga']); ?></p>
                                <p class="text-xs text-gray-500">Petugas: <?php echo htmlspecialchars($row['nama_petugas']); ?></p>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-white text-rose-700 text-xs font-semibold shadow-sm">Tarik</span>
                        </div>
                        <div class="mt-2 flex items-center gap-2 text-sm text-gray-700 font-semibold">
                            <i class="fas fa-wallet text-rose-500"></i>
                            <span><?php echo format_rupiah($row['total_nilai']); ?></span>
                        </div>
                        <div class="mt-2 text-xs text-gray-600">
                            <?php echo htmlspecialchars($row['keterangan_transaksi'] ? $row['keterangan_transaksi'] : '-'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="text-center text-gray-500 text-sm">Tidak ada data penarikan pada tanggal ini.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php if($stmt_penarikan) mysqli_stmt_close($stmt_penarikan); ?>
</div>
