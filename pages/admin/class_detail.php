<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Get class ID from URL
$class_id = isset($_GET['id']) ? sanitizeInput($_GET['id']) : '';

if (empty($class_id)) {
    setFlashMessage('error', 'ID kelas tidak valid.');
    header('Location: manage_classes.php');
    exit;
}

// Get class details
$query = "SELECT k.*, p.nama as wali_kelas_nama 
         FROM kelas k 
         LEFT JOIN pengguna p ON k.wali_kelas_id = p.id 
         WHERE k.id = '$class_id'";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    setFlashMessage('error', 'Kelas tidak ditemukan.');
    header('Location: manage_classes.php');
    exit;
}

$class = mysqli_fetch_assoc($result);

// Get students in class
$query_students = "SELECT id, nama, nisn, email, last_login 
                  FROM pengguna 
                  WHERE kelas_id = '$class_id' AND tipe_pengguna = 'siswa' 
                  ORDER BY nama ASC";
$result_students = mysqli_query($conn, $query_students);

// Get materials for class
$query_materials = "SELECT m.id, m.judul, m.tingkat, m.tanggal_dibuat, p.nama as pembuat_nama, 
                   (SELECT COUNT(*) FROM tugas WHERE materi_id = m.id) as jumlah_tugas
                   FROM materi_coding m
                   JOIN pengguna p ON m.dibuat_oleh = p.id
                   WHERE m.kelas_id = '$class_id'
                   ORDER BY m.tanggal_dibuat DESC";
$result_materials = mysqli_query($conn, $query_materials);

// Get assignments for class
$query_assignments = "SELECT t.id, t.judul, t.tanggal_dibuat, t.tanggal_deadline, 
                     p.nama as pembuat_nama, m.judul as materi_judul,
                     (SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id) as jumlah_pengumpulan
                     FROM tugas t
                     JOIN pengguna p ON t.dibuat_oleh = p.id
                     JOIN materi_coding m ON t.materi_id = m.id
                     WHERE t.kelas_id = '$class_id'
                     ORDER BY t.tanggal_deadline IS NULL, t.tanggal_deadline ASC, t.tanggal_dibuat DESC";
$result_assignments = mysqli_query($conn, $query_assignments);

// Get questionnaires for class
$query_questionnaires = "SELECT k.id, k.judul, k.tanggal_dibuat, p.nama as pembuat_nama,
                        (SELECT COUNT(*) FROM pertanyaan_kuesioner WHERE kuesioner_id = k.id) as jumlah_pertanyaan,
                        (SELECT COUNT(DISTINCT siswa_id) FROM jawaban_kuesioner jk 
                         JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
                         WHERE pk.kuesioner_id = k.id) as jumlah_responden
                        FROM kuesioner k
                        JOIN pengguna p ON k.dibuat_oleh = p.id
                        WHERE k.kelas_id = '$class_id'
                        ORDER BY k.tanggal_dibuat DESC";
$result_questionnaires = mysqli_query($conn, $query_questionnaires);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Detail Kelas: <?php echo $class['nama']; ?></h1>
        <div>
            <a href="manage_classes.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Kelas
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Class Info Card -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Informasi Kelas</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table">
                        <tr>
                            <th width="30%">ID Kelas</th>
                            <td><?php echo $class['id']; ?></td>
                        </tr>
                        <tr>
                            <th>Nama Kelas</th>
                            <td><?php echo $class['nama']; ?></td>
                        </tr>
                        <tr>
                            <th>Tahun Ajaran</th>
                            <td><?php echo $class['tahun_ajaran']; ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table">
                        <tr>
                            <th width="30%">Wali Kelas</th>
                            <td>
                                <?php if ($class['wali_kelas_id']): ?>
                                    <a href="user_detail.php?id=<?php echo $class['wali_kelas_id']; ?>">
                                        <?php echo $class['wali_kelas_nama']; ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">Belum ditentukan</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Status</th>
                            <td>
                                <span class="badge bg-<?php echo $class['status'] === 'aktif' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($class['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Tanggal Dibuat</th>
                            <td><?php echo formatDate($class['tanggal_dibuat']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editClassModal">
                    <i class="fas fa-edit me-2"></i> Edit Kelas
                </button>
                
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteClassModal">
                    <i class="fas fa-trash me-2"></i> Hapus Kelas
                </button>
            </div>
        </div>
    </div>
    
    <!-- Nav tabs for class details -->
    <ul class="nav nav-tabs mb-4" id="classTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="students-tab" data-bs-toggle="tab" data-bs-target="#students" type="button" role="tab" aria-controls="students" aria-selected="true">
                <i class="fas fa-users me-2"></i> Siswa
                <span class="badge bg-primary ms-1"><?php echo mysqli_num_rows($result_students); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="materials-tab" data-bs-toggle="tab" data-bs-target="#materials" type="button" role="tab" aria-controls="materials" aria-selected="false">
                <i class="fas fa-book me-2"></i> Materi
                <span class="badge bg-primary ms-1"><?php echo mysqli_num_rows($result_materials); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="assignments-tab" data-bs-toggle="tab" data-bs-target="#assignments" type="button" role="tab" aria-controls="assignments" aria-selected="false">
                <i class="fas fa-tasks me-2"></i> Tugas
                <span class="badge bg-primary ms-1"><?php echo mysqli_num_rows($result_assignments); ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="questionnaires-tab" data-bs-toggle="tab" data-bs-target="#questionnaires" type="button" role="tab" aria-controls="questionnaires" aria-selected="false">
                <i class="fas fa-clipboard-list me-2"></i> Kuesioner
                <span class="badge bg-primary ms-1"><?php echo mysqli_num_rows($result_questionnaires); ?></span>
            </button>
        </li>
    </ul>
    
    <!-- Tab content -->
    <div class="tab-content">
        <!-- Students Tab -->
        <div class="tab-pane fade show active" id="students" role="tabpanel" aria-labelledby="students-tab">
            <div class="card">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Daftar Siswa</h5>
                        <a href="manage_users.php?role=siswa&class=<?php echo $class_id; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-user-plus me-2"></i> Tambah Siswa
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_students) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nama</th>
                                        <th>NISN</th>
                                        <th>Email</th>
                                        <th>Login Terakhir</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($student = mysqli_fetch_assoc($result_students)): ?>
                                        <tr>
                                            <td><?php echo $student['id']; ?></td>
                                            <td><?php echo $student['nama']; ?></td>
                                            <td><?php echo $student['nisn']; ?></td>
                                            <td><?php echo $student['email']; ?></td>
                                            <td>
                                                <?php echo $student['last_login'] ? formatDate($student['last_login'], true) : '<span class="text-muted">Belum pernah login</span>'; ?>
                                            </td>
                                            <td>
                                                <a href="user_detail.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Belum ada siswa yang terdaftar di kelas ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Materials Tab -->
        <div class="tab-pane fade" id="materials" role="tabpanel" aria-labelledby="materials-tab">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Materi Pembelajaran</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_materials) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Judul</th>
                                        <th>Tingkat</th>
                                        <th>Dibuat Oleh</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Jumlah Tugas</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($material = mysqli_fetch_assoc($result_materials)): ?>
                                        <tr>
                                            <td><?php echo $material['id']; ?></td>
                                            <td><?php echo $material['judul']; ?></td>
                                            <td><?php echo $material['tingkat']; ?></td>
                                            <td><?php echo $material['pembuat_nama']; ?></td>
                                            <td><?php echo formatDate($material['tanggal_dibuat']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $material['jumlah_tugas']; ?></span>
                                            </td>
                                            <td>
                                                <a href="../siswa/material_detail.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Belum ada materi yang ditambahkan untuk kelas ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Assignments Tab -->
        <div class="tab-pane fade" id="assignments" role="tabpanel" aria-labelledby="assignments-tab">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Daftar Tugas</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_assignments) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Judul</th>
                                        <th>Materi</th>
                                        <th>Dibuat Oleh</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Deadline</th>
                                        <th>Pengumpulan</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($assignment = mysqli_fetch_assoc($result_assignments)): ?>
                                        <tr>
                                            <td><?php echo $assignment['id']; ?></td>
                                            <td><?php echo $assignment['judul']; ?></td>
                                            <td><?php echo $assignment['materi_judul']; ?></td>
                                            <td><?php echo $assignment['pembuat_nama']; ?></td>
                                            <td><?php echo formatDate($assignment['tanggal_dibuat']); ?></td>
                                            <td>
                                                <?php if ($assignment['tanggal_deadline']): ?>
                                                    <?php 
                                                    $deadline = strtotime($assignment['tanggal_deadline']);
                                                    $now = time();
                                                    $is_expired = $deadline < $now;
                                                    ?>
                                                    <span class="badge bg-<?php echo $is_expired ? 'danger' : 'warning'; ?>">
                                                        <?php echo formatDate($assignment['tanggal_deadline']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Tidak ada</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $assignment['jumlah_pengumpulan']; ?></span>
                                            </td>
                                            <td>
                                                <a href="../guru/assignment_detail.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Belum ada tugas yang ditambahkan untuk kelas ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Questionnaires Tab -->
        <div class="tab-pane fade" id="questionnaires" role="tabpanel" aria-labelledby="questionnaires-tab">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Daftar Kuesioner</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_questionnaires) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Judul</th>
                                        <th>Dibuat Oleh</th>
                                        <th>Tanggal Dibuat</th>
                                        <th>Jumlah Pertanyaan</th>
                                        <th>Responden</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($questionnaire = mysqli_fetch_assoc($result_questionnaires)): ?>
                                        <tr>
                                            <td><?php echo $questionnaire['id']; ?></td>
                                            <td><?php echo $questionnaire['judul']; ?></td>
                                            <td><?php echo $questionnaire['pembuat_nama']; ?></td>
                                            <td><?php echo formatDate($questionnaire['tanggal_dibuat']); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $questionnaire['jumlah_pertanyaan']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $questionnaire['jumlah_responden']; ?></span>
                                            </td>
                                            <td>
                                                <a href="../guru/questionnaire_results.php?id=<?php echo $questionnaire['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-chart-bar"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Belum ada kuesioner yang ditambahkan untuk kelas ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Class Modal -->
<div class="modal fade" id="editClassModal" tabindex="-1" aria-labelledby="editClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editClassModalLabel">Edit Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="manage_classes.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_class">
                    <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                    
                    <div class="mb-3">
                        <label for="class_name" class="form-label">Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="class_name" name="class_name" value="<?php echo $class['nama']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="academic_year" class="form-label">Tahun Ajaran <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="academic_year" name="academic_year" value="<?php echo $class['tahun_ajaran']; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="wali_kelas" class="form-label">Wali Kelas</label>
                        <select class="form-select" id="wali_kelas" name="wali_kelas">
                            <option value="">-- Pilih Wali Kelas --</option>
                            <?php 
                            // Reset pointer to beginning of result set
                            mysqli_data_seek($result_teachers, 0);
                            while ($teacher = mysqli_fetch_assoc($result_teachers)):
                            ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo ($teacher['id'] === $class['wali_kelas_id']) ? 'selected' : ''; ?>>
                                    <?php echo $teacher['nama']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="aktif" <?php echo ($class['status'] === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="nonaktif" <?php echo ($class['status'] === 'nonaktif') ? 'selected' : ''; ?>>Nonaktif</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Class Modal -->
<div class="modal fade" id="deleteClassModal" tabindex="-1" aria-labelledby="deleteClassModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteClassModalLabel">Hapus Kelas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus kelas "<?php echo $class['nama']; ?>"?</p>
                
                <?php if (mysqli_num_rows($result_students) > 0): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i> Kelas ini memiliki <?php echo mysqli_num_rows($result_students); ?> siswa terdaftar. Tidak dapat dihapus.
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                
                <?php if (mysqli_num_rows($result_students) === 0): ?>
                    <form method="POST" action="manage_classes.php">
                        <input type="hidden" name="action" value="delete_class">
                        <input type="hidden" name="class_id" value="<?php echo $class['id']; ?>">
                        <button type="submit" class="btn btn-danger">Hapus</button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn btn-danger" disabled>Hapus</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?> 