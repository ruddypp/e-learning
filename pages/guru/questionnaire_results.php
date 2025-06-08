<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has teacher role
checkAccess(['guru']);

// Get teacher ID
$teacher_id = $_SESSION['user_id'];

// Process questionnaire selection
$questionnaire_id = isset($_GET['id']) ? sanitizeInput($_GET['id']) : null;

// Check if questionnaire exists and was created by this teacher
if ($questionnaire_id) {
    $query_check = "SELECT k.*, c.nama as kelas_nama FROM kuesioner k 
                      JOIN kelas c ON k.kelas_id = c.id 
                   WHERE k.id = '$questionnaire_id' AND k.dibuat_oleh = '$teacher_id'";
    $result_check = mysqli_query($conn, $query_check);
    
    if (mysqli_num_rows($result_check) === 0) {
        setFlashMessage('error', 'Kuesioner tidak ditemukan atau Anda tidak memiliki akses.');
        header('Location: questionnaires.php');
    exit;
}

    $questionnaire = mysqli_fetch_assoc($result_check);
}

// Get list of questionnaires created by the teacher
$query_questionnaires = "SELECT k.*, c.nama as kelas_nama 
                        FROM kuesioner k 
                        JOIN kelas c ON k.kelas_id = c.id 
                        WHERE k.dibuat_oleh = '$teacher_id' 
                        ORDER BY k.tanggal_dibuat DESC";
$result_questionnaires = mysqli_query($conn, $query_questionnaires);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Hasil Kuesioner</h1>
        <div>
            <a href="questionnaires.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Kuesioner
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <?php if (!$questionnaire_id): ?>
        <!-- Questionnaire Selection -->
    <div class="card mb-4">
        <div class="card-header">
                <h5 class="mb-0">Pilih Kuesioner</h5>
        </div>
        <div class="card-body">
                <?php if (mysqli_num_rows($result_questionnaires) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Judul Kuesioner</th>
                                    <th>Kelas</th>
                                    <th>Tanggal Dibuat</th>
                                    <th>Responden</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($questionnaire = mysqli_fetch_assoc($result_questionnaires)): ?>
                                    <?php
                                    // Get number of respondents
                                    $questionnaire_id = $questionnaire['id'];
                                    $query_count = "SELECT COUNT(DISTINCT siswa_id) as total 
                                                  FROM jawaban_kuesioner jk 
                                                  JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
                                                  WHERE pk.kuesioner_id = '$questionnaire_id'";
                                    $result_count = mysqli_query($conn, $query_count);
                                    $respondents = mysqli_fetch_assoc($result_count)['total'];
                                    
                                    // Get total possible respondents (students in class)
                                    $kelas_id = $questionnaire['kelas_id'];
                                    $query_students = "SELECT COUNT(*) as total FROM pengguna WHERE kelas_id = '$kelas_id' AND tipe_pengguna = 'siswa'";
                                    $result_students = mysqli_query($conn, $query_students);
                                    $total_students = mysqli_fetch_assoc($result_students)['total'];
                                    
                                    $response_rate = $total_students > 0 ? round(($respondents / $total_students) * 100) : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo $questionnaire['judul']; ?></td>
                                        <td><?php echo $questionnaire['kelas_nama']; ?></td>
                                        <td><?php echo formatDate($questionnaire['tanggal_dibuat']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2"><?php echo $respondents; ?> dari <?php echo $total_students; ?></div>
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $response_rate; ?>%;" 
                                                         aria-valuenow="<?php echo $response_rate; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <div class="ms-2"><?php echo $response_rate; ?>%</div>
                        </div>
                                        </td>
                                        <td>
                                            <a href="questionnaire_results.php?id=<?php echo $questionnaire['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-chart-bar me-1"></i> Lihat Hasil
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i> Anda belum membuat kuesioner. 
                        <a href="questionnaire_edit.php" class="alert-link">Buat kuesioner baru</a>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Questionnaire Results -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Hasil Kuesioner: <?php echo $questionnaire['judul']; ?></h5>
                <p class="mb-0 text-muted">Kelas: <?php echo $questionnaire['kelas_nama']; ?> | Tanggal Dibuat: <?php echo formatDate($questionnaire['tanggal_dibuat']); ?></p>
                </div>
            <div class="card-body">
                            <?php
                // Get questions
                $query_questions = "SELECT * FROM pertanyaan_kuesioner WHERE kuesioner_id = '$questionnaire_id' ORDER BY id ASC";
                $result_questions = mysqli_query($conn, $query_questions);
                
                // Get number of respondents
                $query_count = "SELECT COUNT(DISTINCT siswa_id) as total 
                              FROM jawaban_kuesioner jk 
                              JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
                              WHERE pk.kuesioner_id = '$questionnaire_id'";
                $result_count = mysqli_query($conn, $query_count);
                $respondents = mysqli_fetch_assoc($result_count)['total'];
                
                // Get total possible respondents (students in class)
                $kelas_id = $questionnaire['kelas_id'];
                $query_students = "SELECT COUNT(*) as total FROM pengguna WHERE kelas_id = '$kelas_id' AND tipe_pengguna = 'siswa'";
                $result_students = mysqli_query($conn, $query_students);
                $total_students = mysqli_fetch_assoc($result_students)['total'];
                
                $response_rate = $total_students > 0 ? round(($respondents / $total_students) * 100) : 0;
                ?>
                
                <!-- Response Rate -->
                <div class="alert alert-info">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>Tingkat Respons:</strong> <?php echo $respondents; ?> dari <?php echo $total_students; ?> siswa (<?php echo $response_rate; ?>%)
                        </div>
                        <div>
                            <a href="questionnaire_results.php?id=<?php echo $questionnaire_id; ?>&export=excel" class="btn btn-sm btn-success">
                                <i class="fas fa-file-excel me-1"></i> Export Excel
                            </a>
                            <a href="questionnaire_results.php?id=<?php echo $questionnaire_id; ?>&print=true" class="btn btn-sm btn-secondary ms-2" target="_blank">
                                <i class="fas fa-print me-1"></i> Print
                            </a>
                    </div>
                </div>
            </div>
            
                <!-- Questions and Results -->
                <?php if (mysqli_num_rows($result_questions) > 0): ?>
                    <?php $question_number = 1; ?>
                    <?php while ($question = mysqli_fetch_assoc($result_questions)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Pertanyaan <?php echo $question_number; ?>: <?php echo $question['pertanyaan']; ?></h6>
                                <small class="text-muted">Tipe: <?php echo ucfirst(str_replace('_', ' ', $question['jenis'])); ?></small>
                    </div>
                            <div class="card-body">
                                <?php
                                $question_id = $question['id'];
                                
                                if ($question['jenis'] === 'pilihan_ganda' || $question['jenis'] === 'skala') {
                                    // For multiple choice or scale questions, count responses by answer value
                                    $query_answers = "SELECT jawaban, COUNT(*) as total 
                                                    FROM jawaban_kuesioner 
                                                    WHERE pertanyaan_id = '$question_id' 
                                                    GROUP BY jawaban 
                                                    ORDER BY jawaban ASC";
                                    $result_answers = mysqli_query($conn, $query_answers);
                                    
                                    // Prepare data for chart
                                    $labels = [];
                                    $data = [];
                                    $colors = ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#5a5c69', '#858796'];
                                    $color_index = 0;
                                    $background_colors = [];
                                    
                                    while ($answer = mysqli_fetch_assoc($result_answers)) {
                                        $labels[] = $answer['jawaban'];
                                        $data[] = $answer['total'];
                                        $background_colors[] = $colors[$color_index % count($colors)];
                                        $color_index++;
                                    }
                                    
                                    // Calculate statistics
                                    $query_stats = "SELECT 
                                                  COUNT(*) as count, 
                                                  AVG(jawaban) as average, 
                                                  MIN(jawaban) as minimum,
                                                  MAX(jawaban) as maximum
                                                  FROM jawaban_kuesioner 
                                                  WHERE pertanyaan_id = '$question_id'";
                                    $result_stats = mysqli_query($conn, $query_stats);
                                    $stats = mysqli_fetch_assoc($result_stats);
                                    
                                    // Generate chart container
                                    echo '<div class="row">';
                                    echo '<div class="col-md-8">';
                                    echo '<canvas id="chart_' . $question_id . '" width="400" height="200"></canvas>';
                                    echo '</div>';
                                    echo '<div class="col-md-4">';
                                    echo '<div class="card bg-light">';
                                    echo '<div class="card-body">';
                                    echo '<h6 class="card-title">Statistik</h6>';
                                    echo '<ul class="list-group list-group-flush">';
                                    echo '<li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                          <span>Jumlah Respons:</span><span class="fw-bold">' . $stats['count'] . '</span></li>';
                                    echo '<li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                          <span>Rata-rata:</span><span class="fw-bold">' . number_format($stats['average'], 2) . '</span></li>';
                                    echo '<li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                          <span>Minimum:</span><span class="fw-bold">' . $stats['minimum'] . '</span></li>';
                                    echo '<li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                          <span>Maksimum:</span><span class="fw-bold">' . $stats['maximum'] . '</span></li>';
                                    echo '</ul>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                    
                                    // Generate chart script
                                    echo '<script>';
                                    echo 'document.addEventListener("DOMContentLoaded", function() {';
                                    echo 'var ctx = document.getElementById("chart_' . $question_id . '").getContext("2d");';
                                    echo 'var myChart = new Chart(ctx, {';
                                    echo 'type: "' . ($question['jenis'] === 'skala' ? 'bar' : 'pie') . '",';
                                    echo 'data: {';
                                    echo 'labels: ' . json_encode($labels) . ',';
                                    echo 'datasets: [{';
                                    echo 'label: "Jumlah Respons",';
                                    echo 'data: ' . json_encode($data) . ',';
                                    
                                    if ($question['jenis'] === 'pilihan_ganda') {
                                        echo 'backgroundColor: ' . json_encode($background_colors) . ',';
                                    } else {
                                        echo 'backgroundColor: "rgba(78, 115, 223, 0.7)",';
                                        echo 'borderColor: "rgba(78, 115, 223, 1)",';
                                        echo 'borderWidth: 1';
                                    }
                                    
                                    echo '}]';
                                    echo '},';
                                    echo 'options: {';
                                    
                                    if ($question['jenis'] === 'skala') {
                                        echo 'scales: {';
                                        echo 'y: {';
                                        echo 'beginAtZero: true,';
                                        echo 'ticks: {';
                                        echo 'precision: 0';
                                        echo '}';
                                        echo '}';
                                        echo '},';
                                    }
                                    
                                    echo 'responsive: true,';
                                    echo 'plugins: {';
                                    echo 'legend: {';
                                    echo 'position: "' . ($question['jenis'] === 'skala' ? 'top' : 'right') . '"';
                                    echo '}';
                                    echo '}';
                                    echo '}';
                                    echo '});';
                                    echo '});';
                                    echo '</script>';
                                    
                                } else { // Text responses
                                    // Get text responses
                                    $query_text = "SELECT jk.jawaban, p.nama 
                                                 FROM jawaban_kuesioner jk 
                                                 JOIN pengguna p ON jk.siswa_id = p.id 
                                                 WHERE jk.pertanyaan_id = '$question_id' 
                                                 ORDER BY jk.tanggal_jawab DESC";
                                    $result_text = mysqli_query($conn, $query_text);
                                    
                                    echo '<div class="table-responsive">';
                                    echo '<table class="table table-hover">';
                                    echo '<thead>';
                                    echo '<tr>';
                                    echo '<th style="width: 30%;">Siswa</th>';
                                    echo '<th>Jawaban</th>';
                                    echo '</tr>';
                                    echo '</thead>';
                                    echo '<tbody>';
                                    
                                    if (mysqli_num_rows($result_text) > 0) {
                                        while ($text = mysqli_fetch_assoc($result_text)) {
                                            echo '<tr>';
                                            echo '<td>' . $text['nama'] . '</td>';
                                            echo '<td>' . nl2br(htmlspecialchars($text['jawaban'])) . '</td>';
                                            echo '</tr>';
                                        }
                                    } else {
                                        echo '<tr><td colspan="2" class="text-center">Belum ada jawaban</td></tr>';
                                    }
                                    
                                    echo '</tbody>';
                                    echo '</table>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                        </div>
                        <?php $question_number++; ?>
                    <?php endwhile; ?>
                <?php else: ?>
                                <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Belum ada pertanyaan dalam kuesioner ini.
                    </div>
                <?php endif; ?>
        </div>
        </div>
    <?php endif; ?>
</div>

<!-- Include Chart.js for visualization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Include footer -->
<?php include_once '../../includes/footer.php'; ?> 