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

// Get recent materials for this student's class
$query_materials = "SELECT m.*, p.nama as guru_nama 
                  FROM materi_coding m
                  JOIN pengguna p ON m.dibuat_oleh = p.id
                  WHERE m.kelas_id = '$class_id'
                  ORDER BY m.tanggal_dibuat DESC
                  LIMIT 5";
$result_materials = mysqli_query($conn, $query_materials);

// Get upcoming quizzes (with deadline in the future or no deadline)
$current_date = date('Y-m-d');
$query_upcoming = "SELECT t.*, m.judul as materi_judul, p.nama as guru_nama
                 FROM tugas t
                 JOIN materi_coding m ON t.materi_id = m.id
                 JOIN pengguna p ON t.dibuat_oleh = p.id
                 WHERE t.kelas_id = '$class_id' 
                 AND (t.tanggal_deadline IS NULL OR t.tanggal_deadline >= '$current_date')
                 AND t.id NOT IN (SELECT tugas_id FROM nilai_tugas WHERE siswa_id = '$student_id')
                 ORDER BY t.tanggal_deadline ASC, t.tanggal_dibuat DESC
                 LIMIT 5";
$result_upcoming = mysqli_query($conn, $query_upcoming);

// Get recent quizzes that the student has submitted
$query_submitted = "SELECT t.judul, t.tanggal_deadline, nt.tanggal_pengumpulan, nt.nilai, nt.feedback,
                  m.judul as materi_judul, p.nama as guru_nama
                  FROM nilai_tugas nt
                  JOIN tugas t ON nt.tugas_id = t.id
                  JOIN materi_coding m ON t.materi_id = m.id
                  JOIN pengguna p ON t.dibuat_oleh = p.id
                  WHERE nt.siswa_id = '$student_id'
                  ORDER BY nt.tanggal_pengumpulan DESC
                  LIMIT 5";
$result_submitted = mysqli_query($conn, $query_submitted);

// Get available questionnaires
$query_questionnaires = "SELECT k.*, p.nama as guru_nama,
                       (SELECT COUNT(*) FROM jawaban_kuesioner jk 
                        JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
                        WHERE pk.kuesioner_id = k.id AND jk.siswa_id = '$student_id') as sudah_isi
                       FROM kuesioner k
                       JOIN pengguna p ON k.dibuat_oleh = p.id
                       WHERE k.kelas_id = '$class_id'
                       ORDER BY k.tanggal_dibuat DESC";
$result_questionnaires = mysqli_query($conn, $query_questionnaires);

// Get student's performance statistics
$query_stats = "SELECT 
               COUNT(*) as total_quizzes,
               SUM(CASE WHEN nt.nilai IS NOT NULL THEN 1 ELSE 0 END) as graded_quizzes,
               AVG(nt.nilai) as average_score,
               MIN(nt.nilai) as min_score,
               MAX(nt.nilai) as max_score
               FROM nilai_tugas nt
               JOIN tugas t ON nt.tugas_id = t.id
               WHERE nt.siswa_id = '$student_id'";
$result_stats = mysqli_query($conn, $query_stats);
$stats = mysqli_fetch_assoc($result_stats);

// Get recent activity
$query_activity = "SELECT la.*, 
                 CASE 
                    WHEN la.referensi_id IS NOT NULL AND la.tipe_aktivitas = 'view_materi' THEN m.judul
                    WHEN la.referensi_id IS NOT NULL AND la.tipe_aktivitas = 'submit_tugas' THEN t.judul
                    ELSE NULL
                 END as referensi_judul
                 FROM laporan_aktivitas la
                 LEFT JOIN materi_coding m ON la.referensi_id = m.id AND la.tipe_aktivitas = 'view_materi'
                 LEFT JOIN tugas t ON la.referensi_id = t.id AND la.tipe_aktivitas = 'submit_tugas'
                 WHERE la.pengguna_id = '$student_id'
                 ORDER BY la.waktu DESC
                 LIMIT 10";
$result_activity = mysqli_query($conn, $query_activity);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3">Dashboard Siswa</h1>
            <p class="text-muted mb-0">
                Selamat datang, <strong><?php echo $student['nama']; ?></strong>
                <span class="badge bg-info ms-2"><?php echo $student['kelas_nama'] . ' (' . $student['tahun_ajaran'] . ')'; ?></span>
            </p>
        </div>
        <div>
            <a href="materials.php" class="btn btn-primary">
                <i class="fas fa-book me-2"></i> Lihat Semua Materi
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row">
        <div class="col-md-8">
            <!-- Upcoming Quizzes & Assignments -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Quiz & Tugas Mendatang</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_upcoming) > 0): ?>
                        <div class="list-group">
                            <?php while ($quiz = mysqli_fetch_assoc($result_upcoming)): ?>
                                <a href="quiz.php?id=<?php echo $quiz['id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                        <h6 class="mb-1"><?php echo $quiz['judul']; ?></h6>
                                        <?php if ($quiz['tanggal_deadline']): ?>
                                            <?php
                                            $days_left = (strtotime($quiz['tanggal_deadline']) - time()) / (60 * 60 * 24);
                                            $badge_class = $days_left < 2 ? 'bg-danger' : ($days_left < 5 ? 'bg-warning' : 'bg-info');
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                Deadline: <?php echo formatDate($quiz['tanggal_deadline']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Tidak ada deadline</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-1">Materi: <?php echo $quiz['materi_judul']; ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-user me-1"></i> <?php echo $quiz['guru_nama']; ?> |
                                        <i class="fas fa-calendar me-1"></i> Dibuat: <?php echo formatDate($quiz['tanggal_dibuat']); ?>
                                    </small>
                                </a>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Tidak ada quiz atau tugas mendatang saat ini.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Materials -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Materi Terbaru</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_materials) > 0): ?>
                        <div class="row">
                            <?php while ($material = mysqli_fetch_assoc($result_materials)): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h6 class="card-title"><?php echo $material['judul']; ?></h6>
                                            <p class="card-text small">
                                                <?php echo limitText(strip_tags($material['deskripsi']), 100); ?>
                                            </p>
                                        </div>
                                        <div class="card-footer bg-transparent">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i> <?php echo formatDate($material['tanggal_dibuat']); ?>
                                                </small>
                                                <a href="material_detail.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-book-reader me-1"></i> Baca
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada materi yang tersedia untuk kelas Anda.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Submitted Quizzes -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Quiz Terkini yang Telah Dikerjakan</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_submitted) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Quiz</th>
                                        <th>Materi</th>
                                        <th>Tanggal Pengumpulan</th>
                                        <th>Status</th>
                                        <th>Nilai</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($quiz = mysqli_fetch_assoc($result_submitted)): ?>
                                        <tr>
                                            <td><?php echo $quiz['judul']; ?></td>
                                            <td><?php echo $quiz['materi_judul']; ?></td>
                                            <td><?php echo formatDate($quiz['tanggal_pengumpulan']); ?></td>
                                            <td>
                                                <?php if ($quiz['nilai'] !== null): ?>
                                                    <span class="badge bg-success">Telah dinilai</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Menunggu penilaian</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($quiz['nilai'] !== null): ?>
                                                    <span class="badge bg-<?php echo scoreColor($quiz['nilai']); ?>">
                                                        <?php echo $quiz['nilai']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Anda belum mengerjakan quiz apapun.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Student Performance -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Performa Belajar</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h6 class="text-muted">Quiz Dikerjakan</h6>
                                <h3><?php echo $stats['total_quizzes']; ?></h3>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h6 class="text-muted">Rata-rata Nilai</h6>
                                <h3>
                                    <?php if ($stats['average_score']): ?>
                                        <?php echo round($stats['average_score'], 1); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </h3>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($stats['total_quizzes'] > 0 && $stats['graded_quizzes'] > 0): ?>
                        <div class="mt-3">
                            <canvas id="performanceChart" height="200"></canvas>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada data performa yang cukup untuk ditampilkan.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Available Questionnaires -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Kuesioner</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_questionnaires) > 0): ?>
                        <div class="list-group">
                            <?php while ($questionnaire = mysqli_fetch_assoc($result_questionnaires)): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="mb-1"><?php echo $questionnaire['judul']; ?></h6>
                                        <?php if ($questionnaire['sudah_isi'] > 0): ?>
                                            <span class="badge bg-success">Telah diisi</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Belum diisi</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-1 small">
                                        <?php echo limitText($questionnaire['deskripsi'], 100); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i> <?php echo formatDate($questionnaire['tanggal_dibuat']); ?>
                                        </small>
                                        <?php if ($questionnaire['sudah_isi'] === 0): ?>
                                            <a href="questionnaire.php?id=<?php echo $questionnaire['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-file-alt me-1"></i> Isi Kuesioner
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada kuesioner yang tersedia untuk kelas Anda.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Aktivitas Terbaru</h5>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_activity) > 0): ?>
                        <div class="timeline">
                            <?php while ($activity = mysqli_fetch_assoc($result_activity)): ?>
                                <div class="timeline-item">
                                    <div class="timeline-item-marker">
                                        <?php
                                        $icon_class = '';
                                        $activity_text = '';
                                        
                                        switch ($activity['tipe_aktivitas']) {
                                            case 'login':
                                                $icon_class = 'fa-sign-in-alt text-primary';
                                                $activity_text = 'Login ke sistem';
                                                break;
                                            case 'logout':
                                                $icon_class = 'fa-sign-out-alt text-danger';
                                                $activity_text = 'Logout dari sistem';
                                                break;
                                            case 'view_materi':
                                                $icon_class = 'fa-book text-info';
                                                $activity_text = 'Melihat materi: ' . $activity['referensi_judul'];
                                                break;
                                            case 'submit_tugas':
                                                $icon_class = 'fa-tasks text-success';
                                                $activity_text = 'Mengerjakan quiz: ' . $activity['referensi_judul'];
                                                break;
                                            default:
                                                $icon_class = 'fa-circle text-secondary';
                                                $activity_text = $activity['deskripsi'] ?? 'Aktivitas lainnya';
                                        }
                                        ?>
                                        <div class="timeline-icon">
                                            <i class="fas <?php echo $icon_class; ?>"></i>
                                        </div>
                                        <div class="timeline-date">
                                            <?php echo date('d/m/Y H:i', strtotime($activity['waktu'])); ?>
                                        </div>
                                    </div>
                                    <div class="timeline-content">
                                        <p class="mb-0"><?php echo $activity_text; ?></p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Belum ada aktivitas yang tercatat.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($stats['total_quizzes'] > 0 && $stats['graded_quizzes'] > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctxPerformance = document.getElementById('performanceChart').getContext('2d');
    var performanceChart = new Chart(ctxPerformance, {
        type: 'doughnut',
        data: {
            labels: ['<60', '60-69', '70-79', '80-89', '90-100'],
            datasets: [{
                data: [
                    <?php echo $stats['min_score'] < 60 ? 1 : 0; ?>,
                    <?php echo $stats['min_score'] >= 60 && $stats['max_score'] <= 69 ? 1 : 0; ?>,
                    <?php echo $stats['min_score'] >= 70 && $stats['max_score'] <= 79 ? 1 : 0; ?>,
                    <?php echo $stats['min_score'] >= 80 && $stats['max_score'] <= 89 ? 1 : 0; ?>,
                    <?php echo $stats['max_score'] >= 90 ? 1 : 0; ?>
                ],
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(255, 159, 64, 0.6)',
                    'rgba(255, 205, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(54, 162, 235, 0.6)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(255, 205, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<style>
.timeline {
    position: relative;
    padding-left: 10px;
}

.timeline:before {
    content: '';
    position: absolute;
    top: 0;
    bottom: 0;
    left: 14px;
    width: 2px;
    background-color: #e9ecef;
}

.timeline-item {
    position: relative;
    padding-left: 30px;
    padding-bottom: 15px;
}

.timeline-item-marker {
    display: flex;
    align-items: center;
}

.timeline-icon {
    position: absolute;
    left: 0;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background-color: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
    z-index: 1;
}

.timeline-date {
    font-size: 0.75rem;
    color: #6c757d;
    margin-bottom: 3px;
}

.timeline-content {
    padding-bottom: 5px;
}
</style>

<?php
// Helper function to determine score color
function scoreColor($score) {
    if ($score >= 90) {
        return 'success';
    } elseif ($score >= 80) {
        return 'info';
    } elseif ($score >= 70) {
        return 'primary';
    } elseif ($score >= 60) {
        return 'warning';
    } else {
        return 'danger';
    }
}

// Include footer
include_once '../../includes/footer.php';
?> 