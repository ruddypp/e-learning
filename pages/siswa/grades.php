<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has student role
checkAccess(['siswa']);

// Get student's data
$student_id = $_SESSION['user_id'];
$query_student = "SELECT p.*, k.nama AS kelas_nama, k.tahun_ajaran 
                  FROM pengguna p 
                  LEFT JOIN kelas k ON p.kelas_id = k.id 
                  WHERE p.id = '$student_id'";
$result_student = mysqli_query($conn, $query_student);
$student = mysqli_fetch_assoc($result_student);

// Get all quizzes for the student's class with scores
$query_quizzes = "SELECT t.id, t.judul, t.tanggal_dibuat, t.tanggal_deadline, 
                  m.judul AS materi_judul, m.id AS materi_id,
                  p.nama AS guru_nama, nt.nilai, nt.tanggal_dinilai, nt.id AS nilai_id
                  FROM tugas t
                  JOIN materi_coding m ON t.materi_id = m.id
                  JOIN pengguna p ON t.dibuat_oleh = p.id
                  LEFT JOIN nilai_tugas nt ON t.id = nt.tugas_id AND nt.siswa_id = '$student_id'
                  WHERE t.kelas_id = '{$student['kelas_id']}'
                  ORDER BY t.tanggal_dibuat DESC";
$result_quizzes = mysqli_query($conn, $query_quizzes);

// Get average score and statistics
$total_quizzes = 0;
$completed_quizzes = 0;
$total_score = 0;
$quiz_stats = [];

while ($quiz = mysqli_fetch_assoc($result_quizzes)) {
    $total_quizzes++;
    
    if ($quiz['nilai'] !== null) {
        $completed_quizzes++;
        $total_score += $quiz['nilai'];
        
        // Group by score range
        if ($quiz['nilai'] >= 85) {
            $range = '85-100';
        } elseif ($quiz['nilai'] >= 70) {
            $range = '70-84';
        } elseif ($quiz['nilai'] >= 60) {
            $range = '60-69';
        } else {
            $range = '<60';
        }
        
        if (!isset($quiz_stats[$range])) {
            $quiz_stats[$range] = 0;
        }
        $quiz_stats[$range]++;
    }
}

$average_score = $completed_quizzes > 0 ? round($total_score / $completed_quizzes, 1) : 0;

// Reset result pointer
mysqli_data_seek($result_quizzes, 0);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="h3 mb-0">Nilai Saya</h1>
            <p class="text-muted">Lihat nilai dan hasil belajar Anda</p>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card dashboard-card bg-primary text-white mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Rata-rata Nilai</h6>
                            <h2 class="mb-0"><?php echo $average_score; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-star fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card bg-success text-white mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Quiz Selesai</h6>
                            <h2 class="mb-0"><?php echo $completed_quizzes; ?> / <?php echo $total_quizzes; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-tasks fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card bg-warning text-white mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Nilai Tertinggi</h6>
                            <h2 class="mb-0">
                                <?php
                                $highest_score = 0;
                                mysqli_data_seek($result_quizzes, 0);
                                while ($quiz = mysqli_fetch_assoc($result_quizzes)) {
                                    if ($quiz['nilai'] !== null && $quiz['nilai'] > $highest_score) {
                                        $highest_score = $quiz['nilai'];
                                    }
                                }
                                echo $highest_score;
                                mysqli_data_seek($result_quizzes, 0);
                                ?>
                            </h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-trophy fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card dashboard-card bg-info text-white mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-title">Quiz Belum Selesai</h6>
                            <h2 class="mb-0"><?php echo $total_quizzes - $completed_quizzes; ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-hourglass-half fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quiz Scores Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Nilai Quiz dan Tugas</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Quiz/Tugas</th>
                            <th>Materi</th>
                            <th>Dibuat Oleh</th>
                            <th>Tenggat Waktu</th>
                            <th>Status</th>
                            <th>Nilai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_quizzes) > 0): ?>
                            <?php while ($quiz = mysqli_fetch_assoc($result_quizzes)): ?>
                                <tr>
                                    <td><?php echo $quiz['judul']; ?></td>
                                    <td>
                                        <a href="material_detail.php?id=<?php echo $quiz['materi_id']; ?>">
                                            <?php echo $quiz['materi_judul']; ?>
                                        </a>
                                    </td>
                                    <td><?php echo $quiz['guru_nama']; ?></td>
                                    <td><?php echo formatDate($quiz['tanggal_deadline']); ?></td>
                                    <td>
                                        <?php
                                        if ($quiz['nilai'] !== null) {
                                            echo '<span class="badge bg-success">Selesai</span>';
                                        } else {
                                            if (strtotime($quiz['tanggal_deadline']) < time()) {
                                                echo '<span class="badge bg-danger">Terlewat</span>';
                                            } else {
                                                echo '<span class="badge bg-warning">Belum Selesai</span>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($quiz['nilai'] !== null) {
                                            echo '<span class="fw-bold ' . ($quiz['nilai'] >= 70 ? 'text-success' : 'text-danger') . '">' . $quiz['nilai'] . '</span>';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($quiz['nilai'] !== null): ?>
                                            <a href="quiz_result.php?id=<?php echo $quiz['nilai_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> Lihat Hasil
                                            </a>
                                        <?php else: ?>
                                            <a href="quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-edit"></i> Kerjakan
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Belum ada quiz atau tugas yang tersedia.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 