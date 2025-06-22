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

// Get filter values
$filter_status = isset($_GET['status']) ? sanitizeInput($_GET['status']) : 'all';
$filter_materi = isset($_GET['materi']) ? sanitizeInput($_GET['materi']) : '';

// Get available materials for filter
$query_materials = "SELECT id, judul FROM materi_coding WHERE kelas_id = '$class_id' ORDER BY judul";
$result_materials = mysqli_query($conn, $query_materials);

// Build query for quizzes
$query_quizzes = "SELECT t.*, m.judul as materi_judul, p.nama as guru_nama,
                (SELECT COUNT(*) FROM soal_quiz WHERE tugas_id = t.id) as jumlah_soal,
                (SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id AND siswa_id = '$student_id') as sudah_dikerjakan,
                (SELECT nilai FROM nilai_tugas WHERE tugas_id = t.id AND siswa_id = '$student_id') as nilai
                FROM tugas t
                JOIN materi_coding m ON t.materi_id = m.id
                JOIN pengguna p ON t.dibuat_oleh = p.id
                WHERE t.kelas_id = '$class_id' AND t.status = 'published'";

// Apply material filter
if (!empty($filter_materi)) {
    $query_quizzes .= " AND t.materi_id = '$filter_materi'";
}

// Apply status filter
if ($filter_status === 'pending') {
    // Not yet attempted or not yet graded
    $query_quizzes .= " AND ((SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id AND siswa_id = '$student_id') = 0 
                        OR (SELECT nilai FROM nilai_tugas WHERE tugas_id = t.id AND siswa_id = '$student_id') IS NULL)";
    
    // Only show those that are not past due date
    $current_date = date('Y-m-d');
    $query_quizzes .= " AND (t.tanggal_deadline IS NULL OR t.tanggal_deadline >= '$current_date')";
} elseif ($filter_status === 'completed') {
    // Completed and graded
    $query_quizzes .= " AND (SELECT nilai FROM nilai_tugas WHERE tugas_id = t.id AND siswa_id = '$student_id') IS NOT NULL";
} elseif ($filter_status === 'overdue') {
    // Past due date and not attempted
    $current_date = date('Y-m-d');
    $query_quizzes .= " AND t.tanggal_deadline < '$current_date' 
                        AND (SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id AND siswa_id = '$student_id') = 0";
}

// Order by newest
$query_quizzes .= " ORDER BY t.tanggal_dibuat DESC";

// Execute query
$result_quizzes = mysqli_query($conn, $query_quizzes);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3">Quiz & Tugas</h1>
            <p class="text-muted mb-0">Daftar quiz dan tugas untuk kelas <?php echo $student['kelas_nama'] . ' (' . $student['tahun_ajaran'] . ')'; ?></p>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="quizzes.php" class="row g-3">
                <div class="col-md-5">
                    <label for="materi" class="form-label">Materi</label>
                    <select class="form-select" id="materi" name="materi">
                        <option value="">Semua Materi</option>
                        <?php while ($materi = mysqli_fetch_assoc($result_materials)): ?>
                            <option value="<?php echo $materi['id']; ?>" <?php echo ($filter_materi == $materi['id']) ? 'selected' : ''; ?>>
                                <?php echo $materi['judul']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>Semua</option>
                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Belum Dikerjakan</option>
                        <option value="completed" <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>Sudah Dinilai</option>
                        <option value="overdue" <?php echo ($filter_status == 'overdue') ? 'selected' : ''; ?>>Lewat Deadline</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter me-2"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Quizzes List -->
    <div class="row">
        <?php if (mysqli_num_rows($result_quizzes) > 0): ?>
            <?php while ($quiz = mysqli_fetch_assoc($result_quizzes)): ?>
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <?php
                        $card_header_class = 'bg-primary';
                        $status_badge = '';
                        
                        if ($quiz['sudah_dikerjakan'] > 0) {
                            if ($quiz['nilai'] !== null) {
                                $card_header_class = 'bg-success';
                                $status_badge = '<span class="badge bg-success ms-2">Sudah Dinilai</span>';
                            } else {
                                $card_header_class = 'bg-info';
                                $status_badge = '<span class="badge bg-info ms-2">Menunggu Penilaian</span>';
                            }
                        } else {
                            if ($quiz['tanggal_deadline'] && strtotime($quiz['tanggal_deadline']) < time()) {
                                $card_header_class = 'bg-danger';
                                $status_badge = '<span class="badge bg-danger ms-2">Lewat Deadline</span>';
                            } else {
                                $status_badge = '<span class="badge bg-warning ms-2">Belum Dikerjakan</span>';
                            }
                        }
                        ?>
                        <div class="card-header <?php echo $card_header_class; ?> text-white">
                            <h5 class="mb-0 d-flex justify-content-between align-items-center">
                                <span><?php echo $quiz['judul']; ?></span>
                                <?php echo $status_badge; ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Materi: <?php echo $quiz['materi_judul']; ?></h6>
                            
                            <p class="card-text"><?php echo limitText($quiz['deskripsi'], 150); ?></p>
                            
                            <div class="d-flex justify-content-between mt-3">
                                <div>
                                    <small class="d-block text-muted">
                                        <i class="fas fa-user me-1"></i> Dibuat oleh: <?php echo $quiz['guru_nama']; ?>
                                    </small>
                                    <small class="d-block text-muted">
                                        <i class="fas fa-calendar me-1"></i> Tanggal: <?php echo formatDate($quiz['tanggal_dibuat']); ?>
                                    </small>
                                    <small class="d-block text-muted">
                                        <i class="fas fa-question-circle me-1"></i> Jumlah soal: <?php echo $quiz['jumlah_soal']; ?>
                                    </small>
                                    <?php if ($quiz['tanggal_deadline']): ?>
                                        <small class="d-block <?php echo (strtotime($quiz['tanggal_deadline']) < time()) ? 'text-danger' : 'text-muted'; ?>">
                                            <i class="fas fa-clock me-1"></i> Deadline: <?php echo formatDate($quiz['tanggal_deadline']); ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="d-block text-muted">
                                            <i class="fas fa-clock me-1"></i> Deadline: Tidak ada
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($quiz['nilai'] !== null): ?>
                                    <div class="text-center">
                                        <div class="display-4"><?php echo $quiz['nilai']; ?></div>
                                        <small class="text-muted">Nilai</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <?php if ($quiz['sudah_dikerjakan'] > 0): ?>
                                <a href="quiz_result.php?id=<?php echo $quiz['id']; ?>" class="btn btn-info w-100">
                                    <i class="fas fa-eye me-2"></i> Lihat Hasil
                                </a>
                            <?php else: ?>
                                <?php if ($quiz['tanggal_deadline'] && strtotime($quiz['tanggal_deadline']) < time()): ?>
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="fas fa-clock me-2"></i> Lewat Deadline
                                    </button>
                                <?php elseif ($quiz['status'] === 'closed'): ?>
                                    <button class="btn btn-secondary w-100" disabled>
                                        <i class="fas fa-lock me-2"></i> Quiz Ditutup
                                    </button>
                                <?php else: ?>
                                    <a href="quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-primary w-100">
                                        <i class="fas fa-pen me-2"></i> Kerjakan Quiz
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Tidak ada quiz atau tugas yang tersedia dengan filter yang dipilih.
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 