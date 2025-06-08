<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has principal role
checkAccess(['kepsek']);

// Get statistics for the dashboard
// Total Students
$query_students = "SELECT COUNT(*) as total FROM pengguna WHERE tipe_pengguna = 'siswa'";
$result_students = mysqli_query($conn, $query_students);
$total_students = mysqli_fetch_assoc($result_students)['total'];

// Total Teachers
$query_teachers = "SELECT COUNT(*) as total FROM pengguna WHERE tipe_pengguna = 'guru'";
$result_teachers = mysqli_query($conn, $query_teachers);
$total_teachers = mysqli_fetch_assoc($result_teachers)['total'];

// Total Classes
$query_classes = "SELECT COUNT(*) as total FROM kelas";
$result_classes = mysqli_query($conn, $query_classes);
$total_classes = mysqli_fetch_assoc($result_classes)['total'];

// Total Materials
$query_materials = "SELECT COUNT(*) as total FROM materi_coding";
$result_materials = mysqli_query($conn, $query_materials);
$total_materials = mysqli_fetch_assoc($result_materials)['total'];

// Recent Activity Logs
$query_logs = "SELECT l.*, p.nama as pengguna_nama, p.tipe_pengguna 
              FROM laporan_aktivitas l 
              JOIN pengguna p ON l.pengguna_id = p.id 
              ORDER BY l.waktu DESC LIMIT 10";
$result_logs = mysqli_query($conn, $query_logs);

// Average Quiz Scores by Class
$query_scores = "SELECT k.nama as kelas_nama, AVG(nt.nilai) as rata_nilai,
                COUNT(DISTINCT nt.siswa_id) as jumlah_siswa
                FROM nilai_tugas nt
                JOIN tugas t ON nt.tugas_id = t.id
                JOIN kelas k ON t.kelas_id = k.id
                WHERE nt.nilai IS NOT NULL
                GROUP BY k.id
                ORDER BY rata_nilai DESC";
$result_scores = mysqli_query($conn, $query_scores);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <h1 class="h3 mb-4">Dashboard Kepala Sekolah</h1>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Jumlah Siswa</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_students; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-graduate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Jumlah Guru</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_teachers; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chalkboard-teacher fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Jumlah Kelas</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_classes; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-school fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Jumlah Materi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_materials; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-book fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Average Quiz Scores by Class -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h6 class="m-0 font-weight-bold">Rata-Rata Nilai Quiz per Kelas</h6>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_scores) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Kelas</th>
                                        <th>Rata-Rata Nilai</th>
                                        <th>Jumlah Siswa</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($score = mysqli_fetch_assoc($result_scores)): ?>
                                        <tr>
                                            <td><?php echo $score['kelas_nama']; ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo $score['rata_nilai']; ?>%;" 
                                                         aria-valuenow="<?php echo $score['rata_nilai']; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo number_format($score['rata_nilai'], 1); ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?php echo $score['jumlah_siswa']; ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">Belum ada data nilai quiz yang tersedia.</p>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="grades.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-chart-bar me-1"></i> Lihat Laporan Nilai Lengkap
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity Logs -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h6 class="m-0 font-weight-bold">Aktivitas Terbaru</h6>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_logs) > 0): ?>
                        <div class="timeline">
                            <?php while ($log = mysqli_fetch_assoc($result_logs)): ?>
                                <div class="timeline-item">
                                    <div class="timeline-item-marker">
                                        <?php 
                                        $icon_class = 'fa-circle';
                                        switch ($log['tipe_aktivitas']) {
                                            case 'login':
                                            case 'logout':
                                                $icon_class = 'fa-sign-in-alt';
                                                break;
                                            case 'view_materi':
                                                $icon_class = 'fa-book-reader';
                                                break;
                                            case 'submit_tugas':
                                                $icon_class = 'fa-paper-plane';
                                                break;
                                            case 'nilai_tugas':
                                                $icon_class = 'fa-check-circle';
                                                break;
                                            case 'tambah_materi':
                                            case 'edit_materi':
                                                $icon_class = 'fa-file-alt';
                                                break;
                                        }
                                        ?>
                                        <div class="timeline-item-marker-text">
                                            <?php echo date('H:i', strtotime($log['waktu'])); ?>
                                        </div>
                                        <div class="timeline-item-marker-indicator bg-info">
                                            <i class="fas <?php echo $icon_class; ?> text-white"></i>
                                        </div>
                                    </div>
                                    <div class="timeline-item-content">
                                        <p class="mb-0">
                                            <strong><?php echo $log['pengguna_nama']; ?></strong> 
                                            <span class="badge bg-secondary"><?php echo ucfirst($log['tipe_pengguna']); ?></span>
                                            <br>
                                            <?php echo $log['deskripsi']; ?>
                                        </p>
                                        <div class="text-muted small">
                                            <?php echo time_elapsed_string($log['waktu']); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center text-muted">Belum ada aktivitas terbaru.</p>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="activity.php" class="btn btn-info btn-sm">
                            <i class="fas fa-list me-1"></i> Lihat Semua Aktivitas
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header bg-secondary text-white">
                    <h6 class="m-0 font-weight-bold">Akses Cepat</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <a href="materials.php" class="btn btn-light btn-icon-split">
                                <span class="icon text-gray-600">
                                    <i class="fas fa-book"></i>
                                </span>
                                <span class="text">Lihat Semua Materi</span>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="classes.php" class="btn btn-light btn-icon-split">
                                <span class="icon text-gray-600">
                                    <i class="fas fa-school"></i>
                                </span>
                                <span class="text">Lihat Semua Kelas</span>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="teachers.php" class="btn btn-light btn-icon-split">
                                <span class="icon text-gray-600">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </span>
                                <span class="text">Lihat Semua Guru</span>
                            </a>
                        </div>
                        <div class="col-md-3 mb-3">
                            <a href="questionnaires.php" class="btn btn-light btn-icon-split">
                                <span class="icon text-gray-600">
                                    <i class="fas fa-poll"></i>
                                </span>
                                <span class="text">Lihat Hasil Kuesioner</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 1rem;
    border-left: 1px solid #e3e6f0;
}
.timeline-item {
    position: relative;
    padding-bottom: 1rem;
}
.timeline-item:last-child {
    padding-bottom: 0;
}
.timeline-item-marker {
    position: absolute;
    left: -1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.timeline-item-marker-text {
    font-size: 0.75rem;
    color: #a2acba;
}
.timeline-item-marker-indicator {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 100%;
    background-color: #fff;
    margin-top: 0.25rem;
}
.timeline-item-content {
    padding-left: 1rem;
    padding-bottom: 1rem;
}

.border-left-primary {
    border-left: 0.25rem solid #4e73df !important;
}
.border-left-success {
    border-left: 0.25rem solid #1cc88a !important;
}
.border-left-info {
    border-left: 0.25rem solid #36b9cc !important;
}
.border-left-warning {
    border-left: 0.25rem solid #f6c23e !important;
}
</style>

<?php
// Helper function to format time elapsed
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'tahun',
        'm' => 'bulan',
        'w' => 'minggu',
        'd' => 'hari',
        'h' => 'jam',
        'i' => 'menit',
        's' => 'detik',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? '' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' yang lalu' : 'baru saja';
}

// Include footer
include_once '../../includes/footer.php';
?> 