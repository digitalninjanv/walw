<?php
// modules/laporan/bulanan.php
check_user_level(['admin', 'petugas']);

$bulan_tahun_input = isset($_GET['bulan_tahun']) ? sanitize_input($_GET['bulan_tahun']) : date('Y-m');
// Pastikan format YYYY-MM
if (!preg_match('/^\d{4}-\d{2}$/', $bulan_tahun_input)) {
    $bulan_tahun_input = date('Y-m'); // Default ke bulan ini jika format salah
}
list($tahun, $bulan) = explode('-', $bulan_tahun_input);

// Query untuk mengambil data setoran bulanan
$query_setoran_bulanan = "
    SELECT 
        DATE(t.tanggal_transaksi) as tanggal,
        COUNT(CASE WHEN t.tipe_transaksi = 'setor' THEN t.id_transaksi END) as jumlah_setoran,
        SUM(CASE WHEN t.tipe_transaksi = 'setor' THEN t.total_nilai ELSE 0 END) as total_nilai_setoran,
        SUM(CASE WHEN t.tipe_transaksi = 'tarik_saldo' THEN t.total_nilai ELSE 0 END) as total_nilai_penarikan
    FROM transaksi t
    WHERE YEAR(t.tanggal_transaksi) = ? AND MONTH(t.tanggal_transaksi) = ?
    GROUP BY DATE(t.tanggal_transaksi)
    ORDER BY tanggal ASC
";
$stmt_bulanan = mysqli_prepare($koneksi, $query_setoran_bulanan);
mysqli_stmt_bind_param($stmt_bulanan, "ss", $tahun, $bulan);
mysqli_stmt_execute($stmt_bulanan);
$result_bulanan = mysqli_stmt_get_result($stmt_bulanan);
$bulanan_rows = [];
if ($result_bulanan) {
    while ($row = mysqli_fetch_assoc($result_bulanan)) {
        $bulanan_rows[] = $row;
    }
}

// Hitung total pemasukan dan pengeluaran untuk bulan yang dipilih
$total_pemasukan_bulan_ini = 0;
$total_pengeluaran_bulan_ini = 0;

$query_summary_bulan = "
    SELECT 
        SUM(CASE WHEN tipe_transaksi = 'setor' THEN total_nilai ELSE 0 END) as total_setor_bulan,
        SUM(CASE WHEN tipe_transaksi = 'tarik_saldo' THEN total_nilai ELSE 0 END) as total_tarik_bulan
    FROM transaksi
    WHERE YEAR(tanggal_transaksi) = ? AND MONTH(tanggal_transaksi) = ?
";
$stmt_summary = mysqli_prepare($koneksi, $query_summary_bulan);
mysqli_stmt_bind_param($stmt_summary, "ss", $tahun, $bulan);
mysqli_stmt_execute($stmt_summary);
$result_summary = mysqli_stmt_get_result($stmt_summary);
if($data_summary = mysqli_fetch_assoc($result_summary)){
    $total_pemasukan_bulan_ini = $data_summary['total_setor_bulan'] ?: 0;
    $total_pengeluaran_bulan_ini = $data_summary['total_tarik_bulan'] ?: 0;
}
mysqli_stmt_close($stmt_summary);

?>
<div class="container mx-auto px-4 py-8">
    <div class="bg-gradient-to-br from-sky-500/10 via-indigo-500/10 to-emerald-500/10 border border-sky-100 rounded-2xl p-5 sm:p-6 shadow-xl mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.2em] text-sky-600 font-semibold">Laporan Bulanan</p>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Rekap <?php echo format_tanggal_indonesia($bulan_tahun_input."-01", false); ?></h1>
            <p class="text-sm text-gray-600 mt-1">Detail transaksi per hari dengan tampilan yang tetap nyaman di layar mobile.</p>
        </div>
        <form method="GET" action="<?php echo BASE_URL; ?>index.php" class="flex flex-col sm:flex-row sm:items-center gap-2 bg-white/80 backdrop-blur rounded-2xl px-4 py-3 shadow-md border border-gray-100">
            <input type="hidden" name="page" value="laporan/bulanan">
            <div class="flex items-center gap-2">
                <label for="bulan_tahun_input" class="text-xs font-semibold text-gray-700">Pilih Bulan</label>
                <input type="month" name="bulan_tahun" id="bulan_tahun_input" value="<?php echo $bulan_tahun_input; ?>"
                       class="px-3 py-2 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 text-sm">
            </div>
            <button type="submit" class="bg-gradient-to-r from-sky-500 to-indigo-600 hover:shadow-lg text-white font-semibold py-2.5 px-4 rounded-xl text-sm flex items-center gap-2">
                <i class="fas fa-search"></i> <span>Tampilkan</span>
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 md:gap-6 mb-8">
        <div class="bg-white p-5 rounded-2xl shadow-lg border border-emerald-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Total Pemasukan</p>
            <p class="text-2xl md:text-3xl font-bold text-gray-800 mt-1"><?php echo format_rupiah($total_pemasukan_bulan_ini); ?></p>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-lg border border-rose-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-rose-600">Total Pengeluaran</p>
            <p class="text-2xl md:text-3xl font-bold text-gray-800 mt-1"><?php echo format_rupiah($total_pengeluaran_bulan_ini); ?></p>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-lg border border-sky-100">
            <p class="text-xs font-semibold uppercase tracking-wide text-sky-600">Selisih</p>
            <p class="text-2xl md:text-3xl font-bold text-gray-800 mt-1"><?php echo format_rupiah($total_pemasukan_bulan_ini - $total_pengeluaran_bulan_ini); ?></p>
        </div>
    </div>

    <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
        <h2 class="text-lg sm:text-xl font-semibold text-gray-800 p-4 sm:p-5 bg-gray-50 border-b flex items-center gap-2">
            <i class="fas fa-calendar-alt text-sky-500"></i>
            Rincian Transaksi Bulan <?php echo format_tanggal_indonesia($bulan_tahun_input."-01", false); ?>
        </h2>
        <div class="hidden md:block overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tanggal</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total Setoran (Rp)</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total Penarikan (Rp)</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Selisih Harian (Rp)</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($bulanan_rows)): ?>
                        <?php
                        $grand_total_setoran = 0;
                        $grand_total_penarikan = 0;
                        ?>
                        <?php foreach($bulanan_rows as $row): ?>
                        <?php
                        $selisih_harian = $row['total_nilai_setoran'] - $row['total_nilai_penarikan'];
                        $grand_total_setoran += $row['total_nilai_setoran'];
                        $grand_total_penarikan += $row['total_nilai_penarikan'];
                        ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo format_tanggal_indonesia($row['tanggal'], false); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-emerald-600 text-right"><?php echo format_rupiah($row['total_nilai_setoran']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-rose-600 text-right"><?php echo format_rupiah($row['total_nilai_penarikan']); ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 text-right font-semibold"><?php echo format_rupiah($selisih_harian); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="bg-gray-50 font-bold">
                            <td class="px-6 py-3 text-left text-sm text-gray-700 uppercase">Total Bulan Ini</td>
                            <td class="px-6 py-3 text-right text-sm text-emerald-700"><?php echo format_rupiah($grand_total_setoran); ?></td>
                            <td class="px-6 py-3 text-right text-sm text-rose-700"><?php echo format_rupiah($grand_total_penarikan); ?></td>
                            <td class="px-6 py-3 text-right text-sm text-sky-700"><?php echo format_rupiah($grand_total_setoran - $grand_total_penarikan); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr><td colspan="4" class="text-center py-4 text-gray-500">Tidak ada data transaksi pada bulan ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="grid gap-3 md:hidden p-4">
            <?php if (!empty($bulanan_rows)): ?>
                <?php
                $grand_total_setoran = 0;
                $grand_total_penarikan = 0;
                ?>
                <?php foreach($bulanan_rows as $row): ?>
                    <?php
                    $selisih_harian = $row['total_nilai_setoran'] - $row['total_nilai_penarikan'];
                    $grand_total_setoran += $row['total_nilai_setoran'];
                    $grand_total_penarikan += $row['total_nilai_penarikan'];
                    ?>
                    <div class="border border-gray-100 rounded-xl p-3 bg-slate-50">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs text-gray-500"><?php echo format_tanggal_indonesia($row['tanggal'], false); ?></p>
                                <p class="text-sm font-semibold text-gray-900">Saldo Harian</p>
                            </div>
                            <span class="px-3 py-1 rounded-full bg-white text-sky-700 text-xs font-semibold shadow-sm">Rincian</span>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-xs font-semibold">
                            <div class="px-3 py-2 rounded-lg bg-emerald-50 text-emerald-700">Setor: <?php echo format_rupiah($row['total_nilai_setoran']); ?></div>
                            <div class="px-3 py-2 rounded-lg bg-rose-50 text-rose-700 text-right">Tarik: <?php echo format_rupiah($row['total_nilai_penarikan']); ?></div>
                        </div>
                        <div class="mt-2 text-sm font-bold text-gray-800 text-right">Selisih: <?php echo format_rupiah($selisih_harian); ?></div>
                    </div>
                <?php endforeach; ?>
                <div class="border border-gray-100 rounded-xl p-3 bg-white shadow-inner text-sm font-semibold text-gray-800 flex justify-between">
                    <span>Total Bulan Ini</span>
                    <span class="text-sky-700"><?php echo format_rupiah($grand_total_setoran - $grand_total_penarikan); ?></span>
                </div>
            <?php else: ?>
                <p class="text-center text-gray-500 text-sm">Tidak ada data transaksi pada bulan ini.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php mysqli_stmt_close($stmt_bulanan); ?>
</div>
