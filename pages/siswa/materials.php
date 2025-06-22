<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has student role
checkAccess(['siswa']);

// Get student's information
$student_id = $_SESSION['user_id'];
$query_student = "SELECT p.*, k.nama as kelas_nama, k.tahun_ajaran 
                FROM pengguna p
                LEFT JOIN kelas k ON p.kelas_id = k.id
                WHERE p.id = '$student_id'";
$result_student = mysqli_query($conn, $query_student);
$student = mysqli_fetch_assoc($result_student);
$class_id = $student['kelas_id'];

// Get search query
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$tingkat = isset($_GET['tingkat']) ? sanitizeInput($_GET['tingkat']) : '';

// Build query
$where_clause = "WHERE m.kelas_id = '$class_id'";

if (!empty($search)) {
    $where_clause .= " AND (m.judul LIKE '%$search%' OR m.deskripsi LIKE '%$search%')";
}

if (!empty($tingkat)) {
    $where_clause .= " AND m.tingkat = '$tingkat'";
}

// Get all materials for this student's class
$query_materials = "SELECT m.*, p.nama as guru_nama,
                  (SELECT COUNT(*) FROM tugas WHERE materi_id = m.id) as jumlah_quiz
                  FROM materi_coding m
                  JOIN pengguna p ON m.dibuat_oleh = p.id
                  $where_clause
                  ORDER BY m.tanggal_dibuat DESC";
$result_materials = mysqli_query($conn, $query_materials);

// Get distinct difficulty levels for filter
$query_tingkat = "SELECT DISTINCT tingkat FROM materi_coding WHERE kelas_id = '$class_id' ORDER BY 
                 CASE 
                    WHEN tingkat = 'Pemula' THEN 1
                    WHEN tingkat = 'Menengah' THEN 2
                    WHEN tingkat = 'Lanjutan' THEN 3
                    ELSE 4
                 END";
$result_tingkat = mysqli_query($conn, $query_tingkat);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Materi Pembelajaran</h1>
        <div>
            <span class="badge bg-info"><?php echo $student['kelas_nama'] . ' (' . $student['tahun_ajaran'] . ')'; ?></span>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Cari & Filter Materi</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="materials.php" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" id="search" name="search" 
                               placeholder="Cari materi..." value="<?php echo $search; ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <select class="form-select" id="tingkat" name="tingkat" onchange="this.form.submit()">
                        <option value="">Semua Tingkat</option>
                        <?php while ($row = mysqli_fetch_assoc($result_tingkat)): ?>
                            <option value="<?php echo $row['tingkat']; ?>" <?php echo ($tingkat === $row['tingkat']) ? 'selected' : ''; ?>>
                                <?php echo $row['tingkat']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <?php if (!empty($search) || !empty($tingkat)): ?>
                    <div class="col-md-2">
                        <a href="materials.php" class="btn btn-outline-secondary w-100">Reset</a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
    
    <!-- Materials List -->
    <div class="row">
        <?php if (mysqli_num_rows($result_materials) > 0): ?>
            <?php while ($material = mysqli_fetch_assoc($result_materials)): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header bg-outline-secondary text-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?php echo $material['judul']; ?></h5>
                                <span class="badge bg-info"><?php echo $material['tingkat']; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="card-text">
                                <?php echo limitText(strip_tags($material['deskripsi']), 150); ?>
                            </p>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i> <?php echo $material['guru_nama']; ?>
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i> <?php echo formatDate($material['tanggal_dibuat']); ?>
                                    </small>
                                </div>
                                <div class="text-center">
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-tasks me-1"></i> <?php echo $material['jumlah_quiz']; ?> Quiz
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <a href="material_detail.php?id=<?php echo $material['id']; ?>" class="btn btn-primary w-100">
                                <i class="fas fa-book-reader me-2"></i> Baca Materi
                            </a>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-md-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <?php if (!empty($search) || !empty($tingkat)): ?>
                        Tidak ada materi yang sesuai dengan kriteria pencarian Anda.
                    <?php else: ?>
                        Belum ada materi yang tersedia untuk kelas Anda.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.card-header h5 {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<?php include_once '../../includes/footer.php'; ?> 