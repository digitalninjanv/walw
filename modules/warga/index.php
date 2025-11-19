<?php
// modules/warga/index.php
// File ini untuk menampilkan daftar warga
check_user_level(['admin', 'petugas']); // Hanya admin dan petugas yang bisa akses

$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$query_condition = "";
$params_type = "";
$params_value = [];

// Hanya mencari warga
$base_condition = "level = 'warga'";

if (!empty($search)) {
    $search_term = "%" . $search . "%";
    // Pencarian tetap bisa mencakup username meskipun tidak ditampilkan, karena username = no_telepon bersih
    $query_condition = " AND (nama_lengkap LIKE ? OR username LIKE ? OR alamat LIKE ? OR no_telepon LIKE ?)";
    $params_type = "ssss"; 
    for ($i = 0; $i < substr_count($params_type, 's'); $i++) {
        $params_value[] = $search_term;
    }
}

// Kolom yang dipilih disesuaikan, username tetap dipilih untuk logika internal jika diperlukan, tapi tidak ditampilkan
$query_string = "SELECT id_pengguna, nama_lengkap, username, alamat, no_telepon, saldo, tanggal_daftar
                 FROM pengguna
                 WHERE $base_condition $query_condition
                 ORDER BY nama_lengkap ASC";

$stmt = mysqli_prepare($koneksi, $query_string);

if (!empty($search) && $stmt) {
    mysqli_stmt_bind_param($stmt, $params_type, ...$params_value);
}

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $data_warga = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data_warga[] = $row;
        }
    }
} else {
    error_log("MySQLi prepare error in warga/index.php: " . mysqli_error($koneksi));
    $result = false;
    $data_warga = [];
}

?>

<div class="container mx-auto px-4 py-8">
    <div class="bg-gradient-to-br from-sky-500/10 via-indigo-500/10 to-cyan-500/10 border border-sky-100 rounded-2xl p-5 sm:p-6 shadow-xl mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.2em] text-sky-500 font-semibold">Kelola Warga</p>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Data Warga Terdaftar</h1>
            <p class="text-sm text-gray-600 mt-1">Pantau dan kelola akun warga dengan tampilan yang nyaman di layar mobile.</p>
        </div>
        <a href="<?php echo BASE_URL; ?>index.php?page=warga/tambah" class="inline-flex items-center justify-center gap-2 bg-gradient-to-r from-emerald-500 to-sky-500 text-white font-semibold py-2.5 px-4 rounded-xl shadow-lg hover:shadow-xl transition w-full sm:w-auto">
            <i class="fas fa-user-plus"></i> Tambah Warga Baru
        </a>
    </div>

    <form method="GET" action="<?php echo BASE_URL; ?>index.php" class="mb-6">
        <input type="hidden" name="page" value="warga/data">
        <div class="flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fas fa-search text-sky-500 absolute left-4 top-1/2 -translate-y-1/2"></i>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Cari berdasarkan nama, no. telepon, atau alamat..." class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500 shadow-sm bg-white">
            </div>
            <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white font-semibold px-6 py-3 rounded-xl transition shadow-md">
                Cari
            </button>
        </div>
    </form>

    <div class="space-y-4">
        <div class="hidden md:block bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No</th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nama Lengkap</th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">No. Telepon</th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Alamat</th>
                            <th scope="col" class="px-4 sm:px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (!empty($data_warga)): ?>
                            <?php foreach($data_warga as $index => $row): ?>
                            <tr class="hover:bg-sky-50/50 transition-colors duration-150">
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $index + 1; ?></td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-semibold flex items-center gap-2">
                                    <span class="w-9 h-9 rounded-full bg-sky-100 text-sky-600 flex items-center justify-center font-bold text-xs">
                                        <?php echo strtoupper(substr($row['nama_lengkap'], 0, 2)); ?>
                                    </span>
                                    <div class="leading-tight">
                                        <p><?php echo htmlspecialchars($row['nama_lengkap']); ?></p>
                                        <p class="text-xs text-gray-500">ID: <?php echo htmlspecialchars($row['id_pengguna']); ?></p>
                                    </div>
                                </td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    <div class="flex items-center gap-2"><i class="fas fa-phone-alt text-sky-500"></i><span><?php echo htmlspecialchars($row['no_telepon']); ?></span></div>
                                </td>
                                <td class="px-4 sm:px-6 py-4 text-sm text-gray-600 max-w-xs truncate" title="<?php echo htmlspecialchars($row['alamat']); ?>"><?php echo htmlspecialchars($row['alamat'] ? $row['alamat'] : '-'); ?></td>
                                <td class="px-4 sm:px-6 py-4 whitespace-nowrap text-center text-sm font-medium space-x-2">
                                    <a href="<?php echo BASE_URL; ?>index.php?page=warga/edit&id=<?php echo $row['id_pengguna']; ?>" class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-sky-50 text-sky-700 hover:bg-sky-100 transition">
                                        <i class="fas fa-edit"></i><span class="hidden lg:inline">Edit</span>
                                    </a>
                                    <a href="<?php echo BASE_URL; ?>index.php?page=warga/hapus&id=<?php echo $row['id_pengguna']; ?>"
                                       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg bg-rose-50 text-rose-600 hover:bg-rose-100 transition"
                                       onclick="return confirm('Apakah Anda yakin ingin menghapus warga ini? Semua data transaksi terkait juga akan terhapus.');">
                                       <i class="fas fa-trash"></i><span class="hidden lg:inline">Hapus</span>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">
                                    <div class="flex flex-col items-center gap-2">
                                        <i class="fas fa-users-slash fa-3x text-gray-400"></i>
                                        <?php if(!empty($search)): ?>
                                            Tidak ada data warga ditemukan dengan kata kunci "<strong><?php echo htmlspecialchars($search); ?></strong>".
                                            <br>Coba kata kunci lain atau <a href="<?php echo BASE_URL; ?>index.php?page=warga/data" class="text-sky-500 hover:underline mt-2">tampilkan semua warga</a>.
                                        <?php else: ?>
                                            Belum ada data warga terdaftar.
                                            <br><a href="<?php echo BASE_URL; ?>index.php?page=warga/tambah" class="text-sky-500 hover:underline mt-2">Tambahkan warga baru sekarang.</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-4 md:hidden">
            <?php if (!empty($data_warga)): ?>
                <?php foreach($data_warga as $index => $row): ?>
                    <div class="bg-white border border-gray-100 rounded-2xl p-4 shadow-md">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl bg-sky-100 text-sky-600 flex items-center justify-center font-bold text-base">
                                    <?php echo strtoupper(substr($row['nama_lengkap'], 0, 2)); ?>
                                </div>
                                <div>
                                    <p class="text-lg font-semibold text-gray-900 leading-tight"><?php echo htmlspecialchars($row['nama_lengkap']); ?></p>
                                    <p class="text-xs text-gray-500">ID: <?php echo htmlspecialchars($row['id_pengguna']); ?></p>
                                </div>
                            </div>
                            <span class="text-xs px-3 py-1 bg-sky-50 text-sky-600 rounded-full font-semibold">#<?php echo $index + 1; ?></span>
                        </div>

                        <div class="mt-3 space-y-2 text-sm text-gray-600">
                            <div class="flex items-center gap-2">
                                <i class="fas fa-phone text-sky-500"></i>
                                <span><?php echo htmlspecialchars($row['no_telepon'] ?: '-'); ?></span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-map-marker-alt text-sky-500 mt-0.5"></i>
                                <span class="leading-relaxed"><?php echo htmlspecialchars($row['alamat'] ?: '-'); ?></span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <a href="<?php echo BASE_URL; ?>index.php?page=warga/edit&id=<?php echo $row['id_pengguna']; ?>" class="flex-1 min-w-[48%] inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-sky-50 text-sky-700 hover:bg-sky-100 font-semibold text-sm transition">
                                <i class="fas fa-edit"></i>Edit
                            </a>
                            <a href="<?php echo BASE_URL; ?>index.php?page=warga/hapus&id=<?php echo $row['id_pengguna']; ?>"
                               class="flex-1 min-w-[48%] inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-100 font-semibold text-sm transition"
                               onclick="return confirm('Apakah Anda yakin ingin menghapus warga ini? Semua data transaksi terkait juga akan terhapus.');">
                               <i class="fas fa-trash"></i>Hapus
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="bg-white border border-gray-100 rounded-2xl p-6 text-center shadow-md">
                    <i class="fas fa-users-slash fa-2x text-gray-400 mb-2"></i>
                    <p class="text-gray-600 text-sm">Belum ada data warga terdaftar.</p>
                    <a href="<?php echo BASE_URL; ?>index.php?page=warga/tambah" class="mt-3 inline-flex items-center gap-2 text-sky-600 font-semibold">Tambah sekarang <i class="fas fa-arrow-right"></i></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
if ($stmt) {
    mysqli_stmt_close($stmt);
}
?>
