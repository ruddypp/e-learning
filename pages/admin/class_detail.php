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

// Get teachers for dropdown
$query_teachers = "SELECT id, nama FROM pengguna WHERE tipe_pengguna = 'guru' AND status = 'aktif' ORDER BY nama ASC";
$result_teachers = mysqli_query($conn, $query_teachers);

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
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                            <i class="fas fa-user-plus me-2"></i> Tambah Siswa
                        </button>
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
                                                <button type="button" class="btn btn-sm btn-info edit-student-btn" 
                                                        data-id="<?php echo $student['id']; ?>"
                                                        data-nama="<?php echo $student['nama']; ?>"
                                                        data-nisn="<?php echo $student['nisn']; ?>"
                                                        data-email="<?php echo $student['email']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#editStudentModal">
                                                    <i class="fas fa-edit"></i>
                                                </button>
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
                                                <button type="button" class="btn btn-sm btn-info view-material-btn" 
                                                        data-id="<?php echo $material['id']; ?>"
                                                        data-judul="<?php echo $material['judul']; ?>"
                                                        data-tingkat="<?php echo $material['tingkat']; ?>"
                                                        data-pembuat="<?php echo $material['pembuat_nama']; ?>"
                                                        data-tanggal="<?php echo formatDate($material['tanggal_dibuat']); ?>"
                                                        data-bs-toggle="modal" data-bs-target="#viewMaterialModal">
                                                    <i class="fas fa-eye"></i>
                                                </button>
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
                                                <button type="button" class="btn btn-sm btn-info view-assignment-btn" 
                                                        data-id="<?php echo $assignment['id']; ?>"
                                                        data-judul="<?php echo $assignment['judul']; ?>"
                                                        data-materi="<?php echo $assignment['materi_judul']; ?>"
                                                        data-pembuat="<?php echo $assignment['pembuat_nama']; ?>"
                                                        data-tanggal="<?php echo formatDate($assignment['tanggal_dibuat']); ?>"
                                                        data-deadline="<?php echo $assignment['tanggal_deadline'] ? formatDate($assignment['tanggal_deadline']) : 'Tidak ada'; ?>"
                                                        data-pengumpulan="<?php echo $assignment['jumlah_pengumpulan']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#viewAssignmentModal">
                                                    <i class="fas fa-eye"></i>
                                                </button>
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
                                                <button type="button" class="btn btn-sm btn-info view-questionnaire-btn" 
                                                        data-id="<?php echo $questionnaire['id']; ?>"
                                                        data-judul="<?php echo $questionnaire['judul']; ?>"
                                                        data-pembuat="<?php echo $questionnaire['pembuat_nama']; ?>"
                                                        data-tanggal="<?php echo formatDate($questionnaire['tanggal_dibuat']); ?>"
                                                        data-pertanyaan="<?php echo $questionnaire['jumlah_pertanyaan']; ?>"
                                                        data-responden="<?php echo $questionnaire['jumlah_responden']; ?>"
                                                        data-bs-toggle="modal" data-bs-target="#viewQuestionnaireModal">
                                                    <i class="fas fa-chart-bar"></i>
                                                </button>
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
                            // Reset the result pointer to be safe
                            if ($result_teachers) {
                                mysqli_data_seek($result_teachers, 0);
                                while ($teacher = mysqli_fetch_assoc($result_teachers)):
                                ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo ($teacher['id'] === $class['wali_kelas_id']) ? 'selected' : ''; ?>>
                                        <?php echo $teacher['nama']; ?>
                                    </option>
                                <?php endwhile;
                            }
                            ?>
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

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStudentModalLabel">Tambah Siswa Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="process_student.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_student">
                    <input type="hidden" name="kelas_id" value="<?php echo $class_id; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nama" name="nama" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nisn" class="form-label">NISN <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nisn" name="nisn" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editStudentModalLabel">Edit Siswa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="process_student.php">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_student">
                    <input type="hidden" name="student_id" id="edit_student_id">
                    <input type="hidden" name="kelas_id" value="<?php echo $class_id; ?>">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_nama" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_nama" name="nama" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_nisn" class="form-label">NISN <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_nisn" name="nisn" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="edit_email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="edit_password" name="password">
                            <small class="form-text text-muted">Biarkan kosong jika tidak ingin mengubah password.</small>
                        </div>
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

<!-- View Material Modal -->
<div class="modal fade" id="viewMaterialModal" tabindex="-1" aria-labelledby="viewMaterialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewMaterialModalLabel">Detail Materi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h4 id="material-judul"></h4>
                        <p class="text-muted">ID: <span id="material-id"></span></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Tingkat:</strong> <span id="material-tingkat"></span></p>
                        <p><strong>Dibuat Oleh:</strong> <span id="material-pembuat"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Tanggal Dibuat:</strong> <span id="material-tanggal"></span></p>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Untuk melihat konten materi lengkap, silakan masuk sebagai siswa atau guru.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- View Assignment Modal -->
<div class="modal fade" id="viewAssignmentModal" tabindex="-1" aria-labelledby="viewAssignmentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewAssignmentModalLabel">Detail Tugas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h4 id="assignment-judul"></h4>
                        <p class="text-muted">ID: <span id="assignment-id"></span></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Materi:</strong> <span id="assignment-materi"></span></p>
                        <p><strong>Dibuat Oleh:</strong> <span id="assignment-pembuat"></span></p>
                        <p><strong>Tanggal Dibuat:</strong> <span id="assignment-tanggal"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Deadline:</strong> <span id="assignment-deadline"></span></p>
                        <p><strong>Jumlah Pengumpulan:</strong> <span id="assignment-pengumpulan"></span></p>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Untuk melihat detail tugas lengkap dan pengumpulan siswa, silakan masuk sebagai guru.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- View Questionnaire Modal -->
<div class="modal fade" id="viewQuestionnaireModal" tabindex="-1" aria-labelledby="viewQuestionnaireModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewQuestionnaireModalLabel">Detail Kuesioner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-4">
                    <div class="col-md-12">
                        <h4 id="questionnaire-judul"></h4>
                        <p class="text-muted">ID: <span id="questionnaire-id"></span></p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Dibuat Oleh:</strong> <span id="questionnaire-pembuat"></span></p>
                        <p><strong>Tanggal Dibuat:</strong> <span id="questionnaire-tanggal"></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Jumlah Pertanyaan:</strong> <span id="questionnaire-pertanyaan"></span></p>
                        <p><strong>Jumlah Responden:</strong> <span id="questionnaire-responden"></span></p>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Untuk melihat hasil kuesioner lengkap, silakan masuk sebagai guru.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Modals -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle edit student button clicks
    const editButtons = document.querySelectorAll('.edit-student-btn');
    editButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Get data from button attributes
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const nisn = this.getAttribute('data-nisn');
            const email = this.getAttribute('data-email');
            
            // Set values in the edit form
            document.getElementById('edit_student_id').value = id;
            document.getElementById('edit_nama').value = nama;
            document.getElementById('edit_nisn').value = nisn;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_password').value = '';
        });
    });
    
    // Handle view material button clicks
    const viewMaterialButtons = document.querySelectorAll('.view-material-btn');
    viewMaterialButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Get data from button attributes
            const id = this.getAttribute('data-id');
            const judul = this.getAttribute('data-judul');
            const tingkat = this.getAttribute('data-tingkat');
            const pembuat = this.getAttribute('data-pembuat');
            const tanggal = this.getAttribute('data-tanggal');
            
            // Set values in the modal
            document.getElementById('material-id').textContent = id;
            document.getElementById('material-judul').textContent = judul;
            document.getElementById('material-tingkat').textContent = tingkat;
            document.getElementById('material-pembuat').textContent = pembuat;
            document.getElementById('material-tanggal').textContent = tanggal;
        });
    });
    
    // Handle view assignment button clicks
    const viewAssignmentButtons = document.querySelectorAll('.view-assignment-btn');
    viewAssignmentButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Get data from button attributes
            const id = this.getAttribute('data-id');
            const judul = this.getAttribute('data-judul');
            const materi = this.getAttribute('data-materi');
            const pembuat = this.getAttribute('data-pembuat');
            const tanggal = this.getAttribute('data-tanggal');
            const deadline = this.getAttribute('data-deadline');
            const pengumpulan = this.getAttribute('data-pengumpulan');
            
            // Set values in the modal
            document.getElementById('assignment-id').textContent = id;
            document.getElementById('assignment-judul').textContent = judul;
            document.getElementById('assignment-materi').textContent = materi;
            document.getElementById('assignment-pembuat').textContent = pembuat;
            document.getElementById('assignment-tanggal').textContent = tanggal;
            document.getElementById('assignment-deadline').textContent = deadline;
            document.getElementById('assignment-pengumpulan').textContent = pengumpulan;
        });
    });
    
    // Handle view questionnaire button clicks
    const viewQuestionnaireButtons = document.querySelectorAll('.view-questionnaire-btn');
    viewQuestionnaireButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Get data from button attributes
            const id = this.getAttribute('data-id');
            const judul = this.getAttribute('data-judul');
            const pembuat = this.getAttribute('data-pembuat');
            const tanggal = this.getAttribute('data-tanggal');
            const pertanyaan = this.getAttribute('data-pertanyaan');
            const responden = this.getAttribute('data-responden');
            
            // Set values in the modal
            document.getElementById('questionnaire-id').textContent = id;
            document.getElementById('questionnaire-judul').textContent = judul;
            document.getElementById('questionnaire-pembuat').textContent = pembuat;
            document.getElementById('questionnaire-tanggal').textContent = tanggal;
            document.getElementById('questionnaire-pertanyaan').textContent = pertanyaan;
            document.getElementById('questionnaire-responden').textContent = responden;
        });
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?> 