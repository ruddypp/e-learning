<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Check if questionnaire ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Kuesioner tidak ditemukan.');
    header('Location: questionnaires.php');
    exit;
}

$questionnaire_id = sanitizeInput($_GET['id']);

// Check if questionnaire exists
$query_check = "SELECT k.*, c.nama as kelas_nama, p.nama as created_by_name 
               FROM kuesioner k 
               JOIN kelas c ON k.kelas_id = c.id 
               JOIN pengguna p ON k.dibuat_oleh = p.id
               WHERE k.id = '$questionnaire_id'";
$result_check = mysqli_query($conn, $query_check);

if (mysqli_num_rows($result_check) === 0) {
    setFlashMessage('error', 'Kuesioner tidak ditemukan.');
    header('Location: questionnaires.php');
    exit;
}

$questionnaire = mysqli_fetch_assoc($result_check);

// Get questionnaire questions
$query_questions = "SELECT * FROM pertanyaan_kuesioner WHERE kuesioner_id = '$questionnaire_id' ORDER BY id ASC";
$result_questions = mysqli_query($conn, $query_questions);

// Get respondent statistics
$kelas_id = $questionnaire['kelas_id'];
$query_students = "SELECT COUNT(*) as total FROM pengguna WHERE kelas_id = '$kelas_id' AND tipe_pengguna = 'siswa'";
$result_students = mysqli_query($conn, $query_students);
$total_students = mysqli_fetch_assoc($result_students)['total'];

$query_respondents = "SELECT COUNT(DISTINCT siswa_id) as total 
                     FROM jawaban_kuesioner jk 
                     JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
                     WHERE pk.kuesioner_id = '$questionnaire_id'";
$result_respondents = mysqli_query($conn, $query_respondents);
$total_respondents = mysqli_fetch_assoc($result_respondents)['total'];

$response_rate = $total_students > 0 ? round(($total_respondents / $total_students) * 100) : 0;

// Export to Excel if requested
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    // Set headers for Excel download
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment;filename="kuesioner_' . $questionnaire_id . '.xls"');
    header('Cache-Control: max-age=0');
    
    // Output Excel content
    echo '<table border="1">';
    echo '<tr><th colspan="3">Hasil Kuesioner: ' . $questionnaire['judul'] . '</th></tr>';
    echo '<tr><th colspan="3">Kelas: ' . $questionnaire['kelas_nama'] . '</th></tr>';
    echo '<tr><th colspan="3">Tanggal: ' . formatDate($questionnaire['tanggal_dibuat']) . '</th></tr>';
    echo '<tr><th colspan="3">Dibuat oleh: ' . $questionnaire['created_by_name'] . '</th></tr>';
    echo '<tr><th colspan="3">Responden: ' . $total_respondents . ' dari ' . $total_students . ' (' . $response_rate . '%)</th></tr>';
    echo '<tr><td colspan="3"></td></tr>';
    
    $question_number = 1;
    while ($question = mysqli_fetch_assoc($result_questions)) {
        $question_id = $question['id'];
        
        echo '<tr>';
        echo '<th colspan="3">Pertanyaan ' . $question_number . ': ' . $question['pertanyaan'] . '</th>';
        echo '</tr>';
        echo '<tr>';
        echo '<th>No</th>';
        
        if ($question['jenis'] === 'pilihan_ganda' || $question['jenis'] === 'skala') {
            echo '<th>Jawaban</th>';
            echo '<th>Jumlah</th>';
            
            // Get answers for this question
            $query_answers = "SELECT jawaban, COUNT(*) as total 
                             FROM jawaban_kuesioner 
                             WHERE pertanyaan_id = '$question_id' 
                             GROUP BY jawaban 
                             ORDER BY jawaban ASC";
            $result_answers = mysqli_query($conn, $query_answers);
            
            $answer_number = 1;
            while ($answer = mysqli_fetch_assoc($result_answers)) {
                echo '<tr>';
                echo '<td>' . $answer_number . '</td>';
                echo '<td>' . $answer['jawaban'] . '</td>';
                echo '<td>' . $answer['total'] . '</td>';
                echo '</tr>';
                $answer_number++;
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
            
            // Add statistics
            echo '<tr><td colspan="3"></td></tr>';
            echo '<tr><th colspan="2">Rata-rata:</th><td>' . round($stats['average'], 2) . '</td></tr>';
            echo '<tr><th colspan="2">Minimum:</th><td>' . $stats['minimum'] . '</td></tr>';
            echo '<tr><th colspan="2">Maksimum:</th><td>' . $stats['maximum'] . '</td></tr>';
        } else {
            echo '<th colspan="2">Jawaban</th>';
            
            // Get text responses
            $query_text = "SELECT jk.jawaban, p.nama 
                         FROM jawaban_kuesioner jk 
                         JOIN pengguna p ON jk.siswa_id = p.id 
                         WHERE jk.pertanyaan_id = '$question_id' 
                         ORDER BY p.nama ASC";
            $result_text = mysqli_query($conn, $query_text);
            
            $answer_number = 1;
            while ($text = mysqli_fetch_assoc($result_text)) {
                echo '<tr>';
                echo '<td>' . $answer_number . '</td>';
                echo '<td colspan="2">' . nl2br(htmlspecialchars($text['jawaban'])) . ' (Oleh: ' . $text['nama'] . ')</td>';
                echo '</tr>';
                $answer_number++;
            }
        }
        
        echo '<tr><td colspan="3"></td></tr>';
        $question_number++;
    }
    
    echo '</table>';
    exit;
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Hasil Kuesioner: <?php echo $questionnaire['judul']; ?></h1>
        <div>
            <a href="questionnaires.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Kuesioner
            </a>
            <a href="view_questionnaire_results.php?id=<?php echo $questionnaire_id; ?>&export=excel" class="btn btn-success ms-2">
                <i class="fas fa-file-excel me-2"></i> Export Excel
            </a>
            <button onclick="window.print()" class="btn btn-primary ms-2">
                <i class="fas fa-print me-2"></i> Cetak
            </button>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Informasi Kuesioner</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <tr>
                            <th style="width: 30%;">Judul</th>
                            <td><?php echo $questionnaire['judul']; ?></td>
                        </tr>
                        <tr>
                            <th>Kelas</th>
                            <td><?php echo $questionnaire['kelas_nama']; ?></td>
                        </tr>
                        <tr>
                            <th>Dibuat Oleh</th>
                            <td><?php echo $questionnaire['created_by_name']; ?></td>
                        </tr>
                        <tr>
                            <th>Tanggal Dibuat</th>
                            <td><?php echo formatDate($questionnaire['tanggal_dibuat']); ?></td>
                        </tr>
                        <tr>
                            <th>Deskripsi</th>
                            <td><?php echo nl2br(htmlspecialchars($questionnaire['deskripsi'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Ringkasan Respons</h5>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <h6>Tingkat Respons</h6>
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-<?php echo $response_rate < 30 ? 'danger' : ($response_rate < 70 ? 'warning' : 'success'); ?>" 
                                         role="progressbar" 
                                         style="width: <?php echo $response_rate; ?>%;" 
                                         aria-valuenow="<?php echo $response_rate; ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100"></div>
                                </div>
                            </div>
                            <div class="ms-3"><?php echo $response_rate; ?>%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Questions and Results -->
    <?php if (mysqli_num_rows($result_questions) > 0): ?>
        <?php 
        mysqli_data_seek($result_questions, 0); 
        $question_number = 1;
        ?>
        <?php while ($question = mysqli_fetch_assoc($result_questions)): ?>
            <?php $question_id = $question['id']; ?>
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Pertanyaan <?php echo $question_number; ?>: <?php echo $question['pertanyaan']; ?></h5>
                    <small class="text-muted">Tipe: <?php echo ucfirst(str_replace('_', ' ', $question['jenis'])); ?></small>
                </div>
                <div class="card-body">
                    <?php if ($question['jenis'] === 'skala'): ?>
                        <?php
                        // Get scale responses
                        $query_scale = "SELECT jawaban, COUNT(*) as total 
                                       FROM jawaban_kuesioner 
                                       WHERE pertanyaan_id = '$question_id' 
                                       GROUP BY jawaban 
                                       ORDER BY jawaban ASC";
                        $result_scale = mysqli_query($conn, $query_scale);
                        
                        // Prepare data for chart
                        $scale_labels = ['1', '2', '3', '4', '5'];
                        $scale_data = array_fill(0, 5, 0);
                        
                        while ($scale = mysqli_fetch_assoc($result_scale)) {
                            $index = (int)$scale['jawaban'] - 1;
                            if ($index >= 0 && $index < 5) {
                                $scale_data[$index] = (int)$scale['total'];
                            }
                        }
                        
                        // Calculate statistics
                        $query_stats = "SELECT 
                                      COUNT(*) as count, 
                                      ROUND(AVG(jawaban), 2) as average, 
                                      MIN(jawaban) as minimum,
                                      MAX(jawaban) as maximum
                                      FROM jawaban_kuesioner 
                                      WHERE pertanyaan_id = '$question_id'";
                        $result_stats = mysqli_query($conn, $query_stats);
                        $stats = mysqli_fetch_assoc($result_stats);
                        ?>
                        
                        <div class="row">
                            <div class="col-md-8">
                                <canvas id="chart-<?php echo $question_id; ?>" height="250"></canvas>
                            </div>
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0">Statistik</h6>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-sm">
                                            <tr>
                                                <th>Jumlah Responden</th>
                                                <td><?php echo $stats['count']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Rata-rata</th>
                                                <td><?php echo $stats['average']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Minimum</th>
                                                <td><?php echo $stats['minimum']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Maksimum</th>
                                                <td><?php echo $stats['maximum']; ?></td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var ctx = document.getElementById('chart-<?php echo $question_id; ?>').getContext('2d');
                            var chart = new Chart(ctx, {
                                type: 'bar',
                                data: {
                                    labels: <?php echo json_encode($scale_labels); ?>,
                                    datasets: [{
                                        label: 'Jumlah Responden',
                                        data: <?php echo json_encode($scale_data); ?>,
                                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                                        borderColor: 'rgba(54, 162, 235, 1)',
                                        borderWidth: 1
                                    }]
                                },
                                options: {
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            ticks: {
                                                precision: 0
                                            }
                                        }
                                    }
                                }
                            });
                        });
                        </script>
                    <?php else: ?>
                        <?php
                        // Get text responses
                        $query_text = "SELECT jk.jawaban, jk.tanggal_jawab, p.nama 
                                     FROM jawaban_kuesioner jk 
                                     JOIN pengguna p ON jk.siswa_id = p.id 
                                     WHERE jk.pertanyaan_id = '$question_id' 
                                     ORDER BY jk.tanggal_jawab DESC";
                        $result_text = mysqli_query($conn, $query_text);
                        
                        $total_responses = mysqli_num_rows($result_text);
                        ?>
                        
                        <?php if ($total_responses > 0): ?>
                            <div class="list-group">
                                <?php while ($text = mysqli_fetch_assoc($result_text)): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $text['nama']; ?></h6>
                                            <small><?php echo formatDate($text['tanggal_jawab'], true); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($text['jawaban'])); ?></p>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Belum ada jawaban untuk pertanyaan ini.
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php $question_number++; ?>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Kuesioner ini belum memiliki pertanyaan.
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include_once '../../includes/footer.php'; ?> 