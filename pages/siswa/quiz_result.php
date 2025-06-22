<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has student role
checkAccess(['siswa']);

// Check if quiz ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Quiz tidak ditemukan.');
    header('Location: dashboard.php');
    exit;
}

$quiz_id = sanitizeInput($_GET['id']);
$student_id = $_SESSION['user_id'];

// Get quiz details
$query_quiz = "SELECT t.*, m.judul as materi_judul, k.nama as kelas_nama, p.nama as guru_nama
              FROM tugas t 
              JOIN materi_coding m ON t.materi_id = m.id 
              JOIN kelas k ON t.kelas_id = k.id 
              JOIN pengguna p ON t.dibuat_oleh = p.id
              WHERE t.id = '$quiz_id'";
$result_quiz = mysqli_query($conn, $query_quiz);

if (mysqli_num_rows($result_quiz) === 0) {
    setFlashMessage('error', 'Quiz tidak ditemukan.');
    header('Location: dashboard.php');
    exit;
}

$quiz = mysqli_fetch_assoc($result_quiz);

// Check if student has taken this quiz
$query_attempt = "SELECT * FROM nilai_tugas WHERE tugas_id = '$quiz_id' AND siswa_id = '$student_id'";
$result_attempt = mysqli_query($conn, $query_attempt);

if (mysqli_num_rows($result_attempt) === 0) {
    setFlashMessage('error', 'Anda belum mengerjakan quiz ini.');
    header('Location: quiz.php?id=' . $quiz_id);
    exit;
}

$attempt = mysqli_fetch_assoc($result_attempt);

// Handle questionnaire submission after quiz
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_questionnaire') {
    $difficulty = sanitizeInput($_POST['difficulty']);
    $clarity = sanitizeInput($_POST['clarity']);
    $feedback = sanitizeInput($_POST['feedback'], false);
    
    // Generate IDs
    $kuesioner_id = generateUniqueId('KSN');
    $pertanyaan_id1 = generateUniqueId('PRT');
    $pertanyaan_id2 = generateUniqueId('PRT');
    $pertanyaan_id3 = generateUniqueId('PRT');
    $jawaban_id1 = generateUniqueId('JWK');
    $jawaban_id2 = generateUniqueId('JWK');
    $jawaban_id3 = generateUniqueId('JWK');
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert kuesioner
        $query_kuesioner = "INSERT INTO kuesioner (id, judul, deskripsi, kelas_id, dibuat_oleh, tanggal_dibuat) 
                          VALUES ('$kuesioner_id', 'Kuesioner Quiz {$quiz['judul']}', 
                          'Kuesioner evaluasi quiz', '{$quiz['kelas_id']}', '{$_SESSION['user_id']}', CURDATE())";
        $conn->query($query_kuesioner);
        
        // Insert questions
        $query_q1 = "INSERT INTO pertanyaan_kuesioner (id, kuesioner_id, pertanyaan, jenis) 
                   VALUES ('$pertanyaan_id1', '$kuesioner_id', 'Tingkat kesulitan quiz', 'skala')";
        $conn->query($query_q1);
        
        $query_q2 = "INSERT INTO pertanyaan_kuesioner (id, kuesioner_id, pertanyaan, jenis) 
                   VALUES ('$pertanyaan_id2', '$kuesioner_id', 'Kejelasan materi dan soal', 'skala')";
        $conn->query($query_q2);
        
        $query_q3 = "INSERT INTO pertanyaan_kuesioner (id, kuesioner_id, pertanyaan, jenis) 
                   VALUES ('$pertanyaan_id3', '$kuesioner_id', 'Saran dan masukan', 'text')";
        $conn->query($query_q3);
        
        // Insert answers
        $query_a1 = "INSERT INTO jawaban_kuesioner (id, pertanyaan_id, siswa_id, jawaban, tanggal_jawab) 
                   VALUES ('$jawaban_id1', '$pertanyaan_id1', '$student_id', '$difficulty', CURDATE())";
        $conn->query($query_a1);
        
        $query_a2 = "INSERT INTO jawaban_kuesioner (id, pertanyaan_id, siswa_id, jawaban, tanggal_jawab) 
                   VALUES ('$jawaban_id2', '$pertanyaan_id2', '$student_id', '$clarity', CURDATE())";
        $conn->query($query_a2);
        
        $query_a3 = "INSERT INTO jawaban_kuesioner (id, pertanyaan_id, siswa_id, jawaban, tanggal_jawab) 
                   VALUES ('$jawaban_id3', '$pertanyaan_id3', '$student_id', '$feedback', CURDATE())";
        $conn->query($query_a3);
        
        $conn->commit();
        
        // Log activity
        logActivity($student_id, 'isi_kuesioner', "Siswa mengisi kuesioner evaluasi quiz: {$quiz['judul']}", $quiz_id);
        
        setFlashMessage('success', 'Terima kasih atas masukan Anda!');
        header('Location: quiz_result.php?id=' . $quiz_id);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        setFlashMessage('error', 'Gagal menyimpan kuesioner: ' . $e->getMessage());
    }
}

// Get student's answers
$query_answers = "SELECT js.*, sq.pertanyaan, sq.jenis, sq.bobot, pj.teks as pilihan_teks, pj.is_benar
                FROM jawaban_siswa js
                JOIN soal_quiz sq ON js.soal_id = sq.id
                LEFT JOIN pilihan_jawaban pj ON js.pilihan_id = pj.id
                WHERE js.nilai_tugas_id = '{$attempt['id']}'
                ORDER BY sq.id ASC";
$result_answers = mysqli_query($conn, $query_answers);

$answers = [];
$total_questions = 0;
$total_bobot = 0;
$earned_bobot = 0;

while ($answer = mysqli_fetch_assoc($result_answers)) {
    $answers[] = $answer;
    $total_questions++;
    $total_bobot += $answer['bobot'];
    $earned_bobot += $answer['nilai_per_soal'] ?? 0;
}

// Check if student has already submitted a questionnaire for this quiz
$query_questionnaire = "SELECT jk.id 
                      FROM jawaban_kuesioner jk 
                      JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id
                      JOIN kuesioner k ON pk.kuesioner_id = k.id
                      WHERE jk.siswa_id = '$student_id' 
                      AND k.judul LIKE '%{$quiz['judul']}%'";
$result_questionnaire = mysqli_query($conn, $query_questionnaire);
$has_submitted_questionnaire = mysqli_num_rows($result_questionnaire) > 0;

// Get available questionnaires for this class
$query_available_questionnaires = "SELECT k.id, k.judul, k.deskripsi, k.tanggal_dibuat, p.nama as dibuat_oleh
                                 FROM kuesioner k
                                 JOIN pengguna p ON k.dibuat_oleh = p.id
                                 WHERE k.kelas_id = '{$quiz['kelas_id']}' 
                                 AND k.status = 'published'
                                 AND k.id NOT IN (
                                    SELECT DISTINCT pk.kuesioner_id 
                                    FROM jawaban_kuesioner jk
                                    JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id
                                    WHERE jk.siswa_id = '$student_id'
                                 )
                                 LIMIT 3";
$result_available_questionnaires = mysqli_query($conn, $query_available_questionnaires);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="quizzes.php" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Quiz
        </a>
        <h1 class="h3">Hasil Quiz: <?php echo $quiz['judul']; ?></h1>
        <p>
            <span class="badge bg-info me-2"><?php echo $quiz['kelas_nama']; ?></span>
            <span class="badge bg-secondary me-2">Materi: <?php echo $quiz['materi_judul']; ?></span>
            <span class="badge bg-success">Tanggal Pengerjaan: <?php echo formatDate($attempt['tanggal_pengumpulan']); ?></span>
            <?php if ($quiz['status'] === 'published'): ?>
                <span class="badge bg-success ms-2">Aktif</span>
            <?php elseif ($quiz['status'] === 'closed'): ?>
                <span class="badge bg-danger ms-2">Ditutup</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-2">Draft</span>
            <?php endif; ?>
        </p>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Quiz Result Overview -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Ringkasan Hasil</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="display-1 fw-bold text-primary mb-3"><?php echo $attempt['nilai']; ?></div>
                        <p class="lead">Nilai Akhir</p>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="h4 mb-0"><?php echo $total_questions; ?></div>
                            <div class="small text-muted">Total Soal</div>
                        </div>
                        <div class="col-4">
                            <div class="h4 mb-0"><?php echo $total_bobot; ?></div>
                            <div class="small text-muted">Total Bobot</div>
                        </div>
                        <div class="col-4">
                            <div class="h4 mb-0"><?php echo $earned_bobot; ?></div>
                            <div class="small text-muted">Poin Diperoleh</div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <h6>Informasi Quiz:</h6>
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-user me-2"></i> Dibuat oleh</span>
                                <span><?php echo $quiz['guru_nama']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-calendar me-2"></i> Tanggal Quiz</span>
                                <span><?php echo formatDate($quiz['tanggal_dibuat']); ?></span>
                            </li>
                            <?php if ($quiz['tanggal_deadline']): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="fas fa-clock me-2"></i> Deadline</span>
                                <span><?php echo formatDate($quiz['tanggal_deadline']); ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <?php if ($attempt['feedback']): ?>
                        <div class="mt-4">
                            <h6>Feedback dari Guru:</h6>
                            <div class="p-3 bg-light rounded">
                                <?php echo $attempt['feedback']; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <?php if (!$has_submitted_questionnaire): ?>
                <!-- Questionnaire Form -->
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Kuesioner Evaluasi</h5>
                    </div>
                    <div class="card-body">
                        <p>Silakan berikan masukan untuk meningkatkan kualitas materi dan quiz:</p>
                        
                        <form method="POST" action="quiz_result.php?id=<?php echo $quiz_id; ?>">
                            <input type="hidden" name="action" value="submit_questionnaire">
                            
                            <div class="mb-3">
                                <label class="form-label">Tingkat kesulitan quiz:</label>
                                <div class="d-flex">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="difficulty" id="difficulty1" value="1" required>
                                        <label class="form-check-label" for="difficulty1">1 (Sangat Mudah)</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="difficulty" id="difficulty2" value="2">
                                        <label class="form-check-label" for="difficulty2">2</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="difficulty" id="difficulty3" value="3">
                                        <label class="form-check-label" for="difficulty3">3</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="difficulty" id="difficulty4" value="4">
                                        <label class="form-check-label" for="difficulty4">4</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="difficulty" id="difficulty5" value="5">
                                        <label class="form-check-label" for="difficulty5">5 (Sangat Sulit)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Kejelasan materi dan soal:</label>
                                <div class="d-flex">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="clarity" id="clarity1" value="1" required>
                                        <label class="form-check-label" for="clarity1">1 (Tidak Jelas)</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="clarity" id="clarity2" value="2">
                                        <label class="form-check-label" for="clarity2">2</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="clarity" id="clarity3" value="3">
                                        <label class="form-check-label" for="clarity3">3</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="clarity" id="clarity4" value="4">
                                        <label class="form-check-label" for="clarity4">4</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="clarity" id="clarity5" value="5">
                                        <label class="form-check-label" for="clarity5">5 (Sangat Jelas)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="feedback" class="form-label">Saran dan masukan:</label>
                                <textarea class="form-control" id="feedback" name="feedback" rows="4" placeholder="Tulis saran atau masukan Anda tentang quiz ini..."></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-paper-plane me-2"></i> Kirim Evaluasi
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Available Questionnaires -->
                <div class="card h-100">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Kuesioner Lainnya</h5>
                    </div>
                    <div class="card-body">
                        <p class="text-success mb-3">
                            <i class="fas fa-check-circle me-2"></i>
                            Terima kasih! Anda telah mengisi kuesioner evaluasi untuk quiz ini.
                        </p>
                        
                        <?php if (mysqli_num_rows($result_available_questionnaires) > 0): ?>
                            <h6>Kuesioner lain yang tersedia:</h6>
                            <div class="list-group">
                                <?php while ($questionnaire = mysqli_fetch_assoc($result_available_questionnaires)): ?>
                                    <a href="questionnaire.php?id=<?php echo $questionnaire['id']; ?>" class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?php echo $questionnaire['judul']; ?></h6>
                                            <small><?php echo formatDate($questionnaire['tanggal_dibuat']); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo limitText($questionnaire['deskripsi'], 100); ?></p>
                                        <small class="text-muted">Dibuat oleh: <?php echo $questionnaire['dibuat_oleh']; ?></small>
                                    </a>
                                <?php endwhile; ?>
                            </div>
                            <div class="mt-3">
                                <a href="questionnaires.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-list me-2"></i> Lihat Semua Kuesioner
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Saat ini tidak ada kuesioner lain yang tersedia untuk Anda.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quiz Answers -->
    <div class="card mb-4">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0">Detail Jawaban</h5>
        </div>
        <div class="card-body">
            <?php if (empty($answers)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Tidak ada data jawaban yang tersedia.
                </div>
            <?php else: ?>
                <?php foreach ($answers as $index => $answer): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Soal <?php echo $index + 1; ?></h6>
                                <div>
                                    <span class="badge bg-secondary me-2">Bobot: <?php echo $answer['bobot']; ?></span>
                                    <?php if ($answer['nilai_per_soal'] !== null): ?>
                                        <span class="badge bg-<?php echo ($answer['nilai_per_soal'] > 0) ? 'success' : 'danger'; ?>">
                                            Nilai: <?php echo $answer['nilai_per_soal']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Belum dinilai</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <?php echo $answer['pertanyaan']; ?>
                            </div>
                            
                            <div class="mt-3">
                                <h6>Jawaban Anda:</h6>
                                <?php if ($answer['jenis'] === 'pilihan_ganda'): ?>
                                    <div class="p-3 bg-light rounded">
                                        <?php 
                                        if ($answer['pilihan_id']) {
                                            echo $answer['pilihan_teks'];
                                            
                                            if ($answer['is_benar']) {
                                                echo ' <span class="badge bg-success"><i class="fas fa-check"></i> Benar</span>';
                                            } else {
                                                echo ' <span class="badge bg-danger"><i class="fas fa-times"></i> Salah</span>';
                                            }
                                        } else {
                                            echo '<em>Tidak menjawab</em>';
                                        }
                                        ?>
                                    </div>
                                <?php else: ?>
                                    <div class="p-3 bg-light rounded">
                                        <?php 
                                        if ($answer['jawaban']) {
                                            echo nl2br($answer['jawaban']);
                                        } else {
                                            echo '<em>Tidak menjawab</em>';
                                        }
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 