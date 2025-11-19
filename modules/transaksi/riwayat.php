<?php
// modules/transaksi/riwayat.php
check_user_level(['admin', 'petugas']);

$filter_warga = isset($_GET['filter_warga']) ? sanitize_input($_GET['filter_warga']) : '';
$filter_tipe = isset($_GET['filter_tipe']) ? sanitize_input($_GET['filter_tipe']) : '';
$filter_tanggal_mulai = isset($_GET['filter_tanggal_mulai']) ? sanitize_input($_GET['filter_tanggal_mulai']) : '';
$filter_tanggal_akhir = isset($_GET['filter_tanggal_akhir']) ? sanitize_input($_GET['filter_tanggal_akhir']) : '';

$conditions = [];
$params_type = "";
$params_value = [];

if (!empty($filter_warga)) {
    $conditions[] = "t.id_warga = ?";
    $params_type .= "i";
    $params_value[] = $filter_warga;
}
if (!empty($filter_tipe)) {
    $conditions[] = "t.tipe_transaksi = ?";
    $params_type .= "s";
    $params_value[] = $filter_tipe;
}
if (!empty($filter_tanggal_mulai)) {
    $conditions[] = "DATE(t.tanggal_transaksi) >= ?";
    $params_type .= "s";
    $params_value[] = $filter_tanggal_mulai;
}
if (!empty($filter_tanggal_akhir)) {
    $conditions[] = "DATE(t.tanggal_transaksi) <= ?";
    $params_type .= "s";
    $params_value[] = $filter_tanggal_akhir;
}

$where_clause = "";
if (!empty($conditions)) {
    $where_clause = "WHERE " . implode(" AND ", $conditions);
}

$query_transaksi = "
    SELECT
        t.id_transaksi,
        t.tanggal_transaksi,
        t.tipe_transaksi,
        t.total_nilai,
        t.keterangan AS keterangan_transaksi,
        warga.nama_lengkap AS nama_warga,
        warga.username AS username_warga,
        petugas.nama_lengkap AS nama_petugas
    FROM transaksi t
    JOIN pengguna warga ON t.id_warga = warga.id_pengguna
    JOIN pengguna petugas ON t.id_petugas_pencatat = petugas.id_pengguna
    $where_clause
    ORDER BY t.tanggal_transaksi DESC
";

$stmt_transaksi = mysqli_prepare($koneksi, $query_transaksi);
if ($stmt_transaksi) {
    if (!empty($params_type) && !empty($params_value)) {
        mysqli_stmt_bind_param($stmt_transaksi, $params_type, ...$params_value);
    }
    mysqli_stmt_execute($stmt_transaksi);
    $result_transaksi = mysqli_stmt_get_result($stmt_transaksi);
} else {
    $result_transaksi = false;
}

$riwayat_transaksi = [];
if ($result_transaksi) {
    while ($row = mysqli_fetch_assoc($result_transaksi)) {
        $row['detail_items'] = [];
        $riwayat_transaksi[] = $row;
    }
    mysqli_free_result($result_transaksi);
}

$detail_query = "SELECT js.nama_sampah, ds.berat_kg, ds.harga_saat_setor, ds.subtotal_nilai
                 FROM detail_setoran ds
                 JOIN jenis_sampah js ON ds.id_jenis_sampah = js.id_jenis_sampah
                 WHERE ds.id_transaksi_setor = ?";
$detail_stmt = mysqli_prepare($koneksi, $detail_query);
if ($detail_stmt) {
    $detail_transaksi_id = 0;
    mysqli_stmt_bind_param($detail_stmt, "i", $detail_transaksi_id);
    foreach ($riwayat_transaksi as &$trx) {
        if ($trx['tipe_transaksi'] === 'setor') {
            $detail_transaksi_id = $trx['id_transaksi'];
            mysqli_stmt_execute($detail_stmt);
            $detail_result = mysqli_stmt_get_result($detail_stmt);
            $detail_items = [];
            if ($detail_result) {
                while ($item = mysqli_fetch_assoc($detail_result)) {
                    $detail_items[] = $item;
                }
                mysqli_free_result($detail_result);
            }
            $trx['detail_items'] = $detail_items;
        }
    }
    unset($trx);
    mysqli_stmt_close($detail_stmt);
}

// Ambil daftar warga untuk filter
$query_all_warga = "SELECT id_pengguna, nama_lengkap, username FROM pengguna WHERE level = 'warga' ORDER BY nama_lengkap ASC";
$result_all_warga = mysqli_query($koneksi, $query_all_warga);
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-gradient-to-br from-sky-500/10 via-indigo-500/10 to-cyan-500/10 border border-sky-100 rounded-2xl p-5 sm:p-6 shadow-xl mb-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs uppercase tracking-[0.3em] text-sky-500 font-semibold">Riwayat Transaksi</p>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Semua Aktivitas Keuangan Warga</h1>
                <p class="text-sm text-gray-600 mt-1">Lihat seluruh setoran dan penarikan dengan tampilan yang rapi di layar mobile maupun desktop.</p>
            </div>
            <div class="flex gap-2 flex-wrap">
                <span class="inline-flex items-center gap-2 text-xs bg-white px-3 py-1 rounded-full border border-gray-200 text-gray-600"><i class="fas fa-database text-sky-500"></i><?php echo count($riwayat_transaksi); ?> data</span>
            </div>
        </div>
    </div>

    <form method="GET" action="<?php echo BASE_URL; ?>index.php" class="mb-8 bg-white border border-gray-100 rounded-2xl shadow-lg p-4 sm:p-6">
        <input type="hidden" name="page" value="transaksi/riwayat">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div>
                <label for="filter_warga" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Warga</label>
                <div class="relative">
                    <i class="fas fa-users text-sky-500 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <select name="filter_warga" id="filter_warga" class="pl-10 pr-4 py-3 w-full border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 bg-gray-50 text-sm">
                        <option value="">Semua Warga</option>
                        <?php if ($result_all_warga && mysqli_num_rows($result_all_warga) > 0): ?>
                            <?php while($w = mysqli_fetch_assoc($result_all_warga)): ?>
                            <option value="<?php echo $w['id_pengguna']; ?>" <?php echo ($filter_warga == $w['id_pengguna']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($w['nama_lengkap'] . ' (' . $w['username'] . ')'); ?>
                            </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div>
                <label for="filter_tipe" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Tipe Transaksi</label>
                <div class="relative">
                    <i class="fas fa-random text-indigo-500 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <select name="filter_tipe" id="filter_tipe" class="pl-10 pr-4 py-3 w-full border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-indigo-500 bg-gray-50 text-sm">
                        <option value="">Semua Tipe</option>
                        <option value="setor" <?php echo ($filter_tipe == 'setor') ? 'selected' : ''; ?>>Setor Sampah</option>
                        <option value="tarik_saldo" <?php echo ($filter_tipe == 'tarik_saldo') ? 'selected' : ''; ?>>Tarik Saldo</option>
                    </select>
                </div>
            </div>
            <div>
                <label for="filter_tanggal_mulai" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Dari Tanggal</label>
                <input type="date" name="filter_tanggal_mulai" id="filter_tanggal_mulai" value="<?php echo htmlspecialchars($filter_tanggal_mulai); ?>" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sky-500 bg-gray-50 text-sm">
            </div>
            <div>
                <label for="filter_tanggal_akhir" class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Sampai Tanggal</label>
                <div class="flex gap-2">
                    <input type="date" name="filter_tanggal_akhir" id="filter_tanggal_akhir" value="<?php echo htmlspecialchars($filter_tanggal_akhir); ?>" class="w-full border border-gray-200 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-sky-500 bg-gray-50 text-sm">
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-3 rounded-xl bg-gradient-to-r from-sky-500 to-indigo-500 text-white font-semibold shadow-lg hover:shadow-xl transition">
                        <i class="fas fa-filter mr-2"></i> Terapkan
                    </button>
                </div>
            </div>
        </div>
    </form>

    <div class="space-y-6">
        <div class="hidden md:block bg-white border border-gray-100 rounded-2xl shadow-xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ID Trans.</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tanggal</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Warga</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tipe</th>
                            <th class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Nilai (Rp)</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Dicatat Oleh</th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Detail/Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($riwayat_transaksi)): ?>
                            <?php foreach($riwayat_transaksi as $trx): ?>
                            <tr class="hover:bg-sky-50/50">
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500">#<?php echo $trx['id_transaksi']; ?></td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d M Y, H:i', strtotime($trx['tanggal_transaksi'])); ?></td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($trx['nama_warga']); ?></td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($trx['tipe_transaksi'] == 'setor'): ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-emerald-50 text-emerald-600">Setor Sampah</span>
                                    <?php elseif ($trx['tipe_transaksi'] == 'tarik_saldo'): ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-amber-50 text-amber-600">Tarik Saldo</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-700"><?php echo htmlspecialchars($trx['tipe_transaksi']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900"><?php echo format_rupiah($trx['total_nilai']); ?></td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($trx['nama_petugas']); ?></td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-600">
                                    <?php if ($trx['tipe_transaksi'] == 'setor' && !empty($trx['detail_items'])): ?>
                                        <ul class="list-disc list-inside text-xs space-y-0.5">
                                            <?php foreach($trx['detail_items'] as $item): ?>
                                                <li><?php echo htmlspecialchars($item['nama_sampah']); ?>: <?php echo $item['berat_kg']; ?>kg @ <?php echo format_rupiah($item['harga_saat_setor']); ?> = <?php echo format_rupiah($item['subtotal_nilai']); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <?php if(!empty($trx['keterangan_transaksi'])): ?>
                                            <p class="mt-1 text-xs italic">Ket: <?php echo htmlspecialchars($trx['keterangan_transaksi']); ?></p>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($trx['keterangan_transaksi'] ? $trx['keterangan_transaksi'] : '-'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500">
                                    Tidak ada data transaksi ditemukan dengan filter yang diterapkan.
                                    <div class="mt-3">
                                        <a href="<?php echo BASE_URL; ?>index.php?page=transaksi/riwayat" class="text-sky-500 hover:underline font-semibold">Reset filter dan tampilkan semua</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-4 md:hidden">
            <?php if (!empty($riwayat_transaksi)): ?>
                <?php foreach($riwayat_transaksi as $trx): ?>
                    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs uppercase tracking-widest text-gray-400">ID #<?php echo $trx['id_transaksi']; ?></p>
                                <p class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($trx['nama_warga']); ?></p>
                                <p class="text-xs text-gray-500"><?php echo date('d M Y, H:i', strtotime($trx['tanggal_transaksi'])); ?></p>
                            </div>
                            <?php if ($trx['tipe_transaksi'] == 'setor'): ?>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-emerald-50 text-emerald-600">Setor</span>
                            <?php elseif ($trx['tipe_transaksi'] == 'tarik_saldo'): ?>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-amber-50 text-amber-600">Tarik</span>
                            <?php else: ?>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600"><?php echo htmlspecialchars($trx['tipe_transaksi']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-4 space-y-2 text-sm text-gray-600">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Nilai</span>
                                <span class="text-base font-semibold text-gray-900"><?php echo format_rupiah($trx['total_nilai']); ?></span>
                            </div>
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">Dicatat oleh</span>
                                <span class="font-medium text-gray-800"><?php echo htmlspecialchars($trx['nama_petugas']); ?></span>
                            </div>
                            <div class="pt-2 border-t border-dashed">
                                <?php if ($trx['tipe_transaksi'] == 'setor' && !empty($trx['detail_items'])): ?>
                                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1">Detail Setoran</p>
                                    <ul class="text-xs space-y-1">
                                        <?php foreach($trx['detail_items'] as $item): ?>
                                            <li class="flex justify-between gap-4">
                                                <span class="text-gray-600"><?php echo htmlspecialchars($item['nama_sampah']); ?></span>
                                                <span class="text-gray-900 font-semibold"><?php echo $item['berat_kg']; ?>kg · <?php echo format_rupiah($item['subtotal_nilai']); ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php if(!empty($trx['keterangan_transaksi'])): ?>
                                        <p class="mt-2 text-xs text-gray-500 italic">Ket: <?php echo htmlspecialchars($trx['keterangan_transaksi']); ?></p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-xs text-gray-600"><?php echo htmlspecialchars($trx['keterangan_transaksi'] ? $trx['keterangan_transaksi'] : '-'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white border border-gray-100 rounded-2xl p-6 text-center shadow-md">
                    <i class="fas fa-receipt fa-2x text-gray-300 mb-3"></i>
                    <p class="text-sm text-gray-500">Tidak ada data transaksi ditemukan.</p>
                    <a href="<?php echo BASE_URL; ?>index.php?page=transaksi/riwayat" class="mt-3 inline-flex items-center gap-2 text-sky-600 font-semibold">Reset filter <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php if ($stmt_transaksi) { mysqli_stmt_close($stmt_transaksi); } ?>
