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
if (!empty($params_type) && !empty($params_value)) {
    mysqli_stmt_bind_param($stmt_transaksi, $params_type, ...$params_value);
}
mysqli_stmt_execute($stmt_transaksi);
$result_transaksi = mysqli_stmt_get_result($stmt_transaksi);
$transaksi_data = [];
if ($result_transaksi) {
    while ($trx = mysqli_fetch_assoc($result_transaksi)) {
        $transaksi_data[] = $trx;
    }
}

// Ambil daftar warga untuk filter
$query_all_warga = "SELECT id_pengguna, nama_lengkap, username FROM pengguna WHERE level = 'warga' ORDER BY nama_lengkap ASC";
$result_all_warga = mysqli_query($koneksi, $query_all_warga);
$warga_filter_list = [];
if ($result_all_warga) {
    while ($w = mysqli_fetch_assoc($result_all_warga)) {
        $warga_filter_list[] = $w;
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-gradient-to-br from-sky-500/10 via-indigo-500/10 to-emerald-500/10 border border-sky-100 rounded-2xl p-5 sm:p-6 shadow-xl mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.2em] text-sky-600 font-semibold">Transaksi</p>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Riwayat Semua Transaksi</h1>
            <p class="text-sm text-gray-600 mt-1">Filter dan telusuri catatan setor & tarik saldo dengan tampilan ramah mobile.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="px-3 py-1 bg-white text-sky-600 border border-sky-100 rounded-full text-xs font-semibold shadow-sm">Live log</span>
            <span class="px-3 py-1 bg-white text-emerald-600 border border-emerald-100 rounded-full text-xs font-semibold shadow-sm">Mobile ready</span>
        </div>
    </div>

    <form method="GET" action="<?php echo BASE_URL; ?>index.php" class="mb-6 bg-white p-4 rounded-2xl shadow-lg border border-gray-100">
        <input type="hidden" name="page" value="transaksi/riwayat">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="space-y-1">
                <label for="filter_warga" class="block text-xs font-semibold text-gray-600 tracking-wide">Pilih Warga</label>
                <select name="filter_warga" id="filter_warga" class="mt-1 block w-full py-3 px-3 border border-gray-200 bg-white rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm">
                    <option value="">Semua Warga</option>
                    <?php foreach($warga_filter_list as $w): ?>
                    <option value="<?php echo $w['id_pengguna']; ?>" <?php echo ($filter_warga == $w['id_pengguna']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($w['nama_lengkap'] . ' (' . $w['username'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="space-y-1">
                <label for="filter_tipe" class="block text-xs font-semibold text-gray-600 tracking-wide">Tipe Transaksi</label>
                <select name="filter_tipe" id="filter_tipe" class="mt-1 block w-full py-3 px-3 border border-gray-200 bg-white rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm">
                    <option value="">Semua Tipe</option>
                    <option value="setor" <?php echo ($filter_tipe == 'setor') ? 'selected' : ''; ?>>Setor Sampah</option>
                    <option value="tarik_saldo" <?php echo ($filter_tipe == 'tarik_saldo') ? 'selected' : ''; ?>>Tarik Saldo</option>
                </select>
            </div>
            <div class="space-y-1">
                <label for="filter_tanggal_mulai" class="block text-xs font-semibold text-gray-600 tracking-wide">Dari Tanggal</label>
                <input type="date" name="filter_tanggal_mulai" id="filter_tanggal_mulai" value="<?php echo htmlspecialchars($filter_tanggal_mulai); ?>" class="mt-1 block w-full py-3 px-3 border border-gray-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm">
            </div>
            <div class="space-y-1">
                <label for="filter_tanggal_akhir" class="block text-xs font-semibold text-gray-600 tracking-wide">Sampai Tanggal</label>
                <input type="date" name="filter_tanggal_akhir" id="filter_tanggal_akhir" value="<?php echo htmlspecialchars($filter_tanggal_akhir); ?>" class="mt-1 block w-full py-3 px-3 border border-gray-200 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 text-sm">
            </div>
            <div class="lg:col-start-4">
                <button type="submit" class="w-full h-full bg-gradient-to-r from-sky-500 to-indigo-600 hover:shadow-lg hover:brightness-105 text-white font-semibold py-3 px-4 rounded-xl shadow-md transition duration-150 flex items-center justify-center gap-2">
                    <i class="fas fa-filter"></i> <span>Terapkan Filter</span>
                </button>
            </div>
        </div>
    </form>

    <div class="space-y-4">
        <div class="hidden md:block bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ID Trans.</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tanggal</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Warga</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Tipe</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Nilai (Rp)</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Dicatat Oleh</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Detail/Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($transaksi_data)): ?>
                            <?php foreach($transaksi_data as $trx): ?>
                            <tr class="hover:bg-sky-50/50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">#<?php echo $trx['id_transaksi']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo date('d M Y, H:i', strtotime($trx['tanggal_transaksi'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($trx['nama_warga']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <?php if ($trx['tipe_transaksi'] == 'setor'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-emerald-100 text-emerald-700">Setor Sampah</span>
                                    <?php elseif ($trx['tipe_transaksi'] == 'tarik_saldo'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-700">Tarik Saldo</span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800"><?php echo htmlspecialchars($trx['tipe_transaksi']); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-slate-800"><?php echo format_rupiah($trx['total_nilai']); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($trx['nama_petugas']); ?></td>
                                <td class="px-6 py-4 text-sm text-gray-500 max-w-sm">
                                    <?php
                                    if ($trx['tipe_transaksi'] == 'setor') {
                                        // Ambil detail item setoran
                                        $query_detail_items = "SELECT js.nama_sampah, ds.berat_kg, ds.harga_saat_setor, ds.subtotal_nilai
                                                               FROM detail_setoran ds
                                                               JOIN jenis_sampah js ON ds.id_jenis_sampah = js.id_jenis_sampah
                                                               WHERE ds.id_transaksi_setor = ?";
                                        $stmt_items = mysqli_prepare($koneksi, $query_detail_items);
                                        mysqli_stmt_bind_param($stmt_items, "i", $trx['id_transaksi']);
                                        mysqli_stmt_execute($stmt_items);
                                        $result_items = mysqli_stmt_get_result($stmt_items);
                                        if(mysqli_num_rows($result_items) > 0){
                                            echo "<ul class='list-disc list-inside text-xs'>";
                                            while($item = mysqli_fetch_assoc($result_items)){
                                                echo "<li>" . htmlspecialchars($item['nama_sampah']) . ": " . $item['berat_kg'] . "kg @ " . format_rupiah($item['harga_saat_setor']) . " = " . format_rupiah($item['subtotal_nilai']) . "</li>";
                                            }
                                            echo "</ul>";
                                        } else {
                                            echo "Detail item tidak ditemukan.";
                                        }
                                        mysqli_stmt_close($stmt_items);
                                        if(!empty($trx['keterangan_transaksi'])) echo "<p class='mt-1 text-xs italic'>Ket: " . htmlspecialchars($trx['keterangan_transaksi']) . "</p>";

                                    } else { // Untuk tarik_saldo atau tipe lain
                                        echo htmlspecialchars($trx['keterangan_transaksi'] ? $trx['keterangan_transaksi'] : '-');
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="px-6 py-6 text-center text-sm text-gray-500">
                                    Tidak ada data transaksi ditemukan dengan filter yang diterapkan.
                                    <br><a href="<?php echo BASE_URL; ?>index.php?page=transaksi/riwayat" class="text-sky-500 hover:underline">Reset filter dan tampilkan semua</a>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-3 md:hidden">
            <?php if (!empty($transaksi_data)): ?>
                <?php foreach($transaksi_data as $trx): ?>
                    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-lg">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <p class="text-xs text-gray-500">#<?php echo $trx['id_transaksi']; ?> • <?php echo date('d M Y, H:i', strtotime($trx['tanggal_transaksi'])); ?></p>
                                <p class="text-base font-semibold text-gray-900 leading-tight"><?php echo htmlspecialchars($trx['nama_warga']); ?></p>
                                <p class="text-xs text-gray-500">Petugas: <?php echo htmlspecialchars($trx['nama_petugas']); ?></p>
                            </div>
                            <?php if ($trx['tipe_transaksi'] == 'setor'): ?>
                                <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">Setor</span>
                            <?php elseif ($trx['tipe_transaksi'] == 'tarik_saldo'): ?>
                                <span class="px-3 py-1 rounded-full bg-orange-50 text-orange-700 text-xs font-semibold">Tarik</span>
                            <?php else: ?>
                                <span class="px-3 py-1 rounded-full bg-gray-50 text-gray-700 text-xs font-semibold"><?php echo htmlspecialchars($trx['tipe_transaksi']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-3 flex items-center justify-between">
                            <div class="text-sm text-gray-500 flex items-center gap-2">
                                <i class="fas fa-wallet text-sky-500"></i>
                                <span class="text-slate-800 font-semibold"><?php echo format_rupiah($trx['total_nilai']); ?></span>
                            </div>
                            <a href="#" class="text-xs text-sky-600 font-semibold flex items-center gap-1">
                                Detail
                                <i class="fas fa-chevron-right text-[10px]"></i>
                            </a>
                        </div>
                        <div class="mt-3 text-sm text-gray-600 border-t border-gray-100 pt-3 space-y-2">
                            <?php
                            if ($trx['tipe_transaksi'] == 'setor') {
                                $query_detail_items = "SELECT js.nama_sampah, ds.berat_kg, ds.harga_saat_setor, ds.subtotal_nilai
                                                       FROM detail_setoran ds
                                                       JOIN jenis_sampah js ON ds.id_jenis_sampah = js.id_jenis_sampah
                                                       WHERE ds.id_transaksi_setor = ?";
                                $stmt_items = mysqli_prepare($koneksi, $query_detail_items);
                                mysqli_stmt_bind_param($stmt_items, "i", $trx['id_transaksi']);
                                mysqli_stmt_execute($stmt_items);
                                $result_items = mysqli_stmt_get_result($stmt_items);
                                if(mysqli_num_rows($result_items) > 0){
                                    echo "<div class='flex flex-wrap gap-2 text-xs'>";
                                    while($item = mysqli_fetch_assoc($result_items)){
                                        echo "<span class='px-3 py-1 bg-sky-50 text-sky-700 rounded-full font-semibold'>" . htmlspecialchars($item['nama_sampah']) . " · " . $item['berat_kg'] . "kg</span>";
                                    }
                                    echo "</div>";
                                } else {
                                    echo "<p class='text-xs text-gray-500'>Detail item tidak ditemukan.</p>";
                                }
                                mysqli_stmt_close($stmt_items);
                                if(!empty($trx['keterangan_transaksi'])) echo "<p class='text-xs italic'>Ket: " . htmlspecialchars($trx['keterangan_transaksi']) . "</p>";
                            } else {
                                echo "<p class='text-xs'>" . htmlspecialchars($trx['keterangan_transaksi'] ? $trx['keterangan_transaksi'] : '-') . "</p>";
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white border border-gray-100 rounded-2xl p-6 text-center shadow-lg text-sm text-gray-500">
                    Tidak ada data transaksi ditemukan dengan filter yang diterapkan.
                    <a href="<?php echo BASE_URL; ?>index.php?page=transaksi/riwayat" class="text-sky-500 font-semibold block mt-2">Reset filter</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php mysqli_stmt_close($stmt_transaksi); ?>
