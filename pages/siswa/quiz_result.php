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
$query_quiz = "SELECT t.*, m.judul as materi_judul, k.nama as kelas_nama 
              FROM tugas t 
              JOIN materi_coding m ON t.materi_id = m.id 
              JOIN kelas k ON t.kelas_id = k.id 
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

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
        </a>
        <h1 class="h3">Hasil Quiz: <?php echo $quiz['judul']; ?></h1>
        <p>
            <span class="badge bg-info me-2"><?php echo $quiz['kelas_nama']; ?></span>
            <span class="badge bg-secondary me-2">Materi: <?php echo $quiz['materi_judul']; ?></span>
            <span class="badge bg-success">Tanggal Pengerjaan: <?php echo formatDate($attempt['tanggal_pengumpulan']); ?></span>
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
                        <?php if ($attempt['nilai']): ?>
                            <div class="display-1 fw-bold text-primary mb-3"><?php echo $attempt['nilai']; ?></div>
                            <p class="lead">Nilai Akhir</p>
                        <?php else: ?>
                            <div class="display-1 fw-bold text-warning mb-3">
                                <i class="fas fa-hourglass-half"></i>
                            </div>
                            <p class="lead">Menunggu Penilaian Guru</p>
                        <?php endif; ?>
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
                                <textarea class="form-control" id="feedback" name="feedback" rows="4"></textarea>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-success">Kirim Kuesioner</button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <!-- Thank you message -->
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Kuesioner Evaluasi</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="py-5">
                            <i class="fas fa-check-circle text-success fa-5x mb-3"></i>
                            <h5>Terima Kasih!</h5>
                            <p>Anda telah mengisi kuesioner evaluasi untuk quiz ini.</p>
                            <p class="text-muted">Masukan Anda sangat berharga untuk meningkatkan kualitas pembelajaran.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Detailed Answers -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Detail Jawaban</h5>
            <span class="badge bg-primary"><?php echo count($answers); ?> Soal</span>
        </div>
        <div class="card-body">
            <?php if (empty($answers)): ?>
                <p class="text-center text-muted">Tidak ada data jawaban.</p>
            <?php else: ?>
                <div class="accordion" id="accordionAnswers">
                    <?php $question_number = 1; ?>
                    <?php foreach ($answers as $answer): ?>
                        <div class="accordion-item mb-3 border">
                            <h2 class="accordion-header" id="heading<?php echo $answer['id']; ?>">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                        data-bs-target="#collapse<?php echo $answer['id']; ?>" 
                                        aria-expanded="false" aria-controls="collapse<?php echo $answer['id']; ?>">
                                    <div class="d-flex align-items-center w-100">
                                        <span class="badge bg-secondary me-2"><?php echo $question_number++; ?></span>
                                        <div class="me-auto">
                                            <?php echo limitText(strip_tags($answer['pertanyaan']), 70); ?>
                                        </div>
                                        <div class="d-flex align-items-center ms-3">
                                            <?php if ($answer['nilai_per_soal'] !== null): ?>
                                                <span class="badge <?php echo ($answer['nilai_per_soal'] > 0) ? 'bg-success' : 'bg-danger'; ?> me-2">
                                                    <?php echo $answer['nilai_per_soal']; ?>/<?php echo $answer['bobot']; ?> poin
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning me-2">Belum dinilai</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $answer['id']; ?>" class="accordion-collapse collapse" 
                                 aria-labelledby="heading<?php echo $answer['id']; ?>" 
                                 data-bs-parent="#accordionAnswers">
                                <div class="accordion-body">
                                    <div class="mb-3">
                                        <h6>Pertanyaan:</h6>
                                        <div class="question-content p-3 bg-light rounded mb-3">
                                            <?php echo $answer['pertanyaan']; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <h6>Jawaban Anda:</h6>
                                        <?php if ($answer['jenis'] === 'pilihan_ganda'): ?>
                                            <div class="p-3 <?php echo $answer['is_benar'] ? 'bg-success text-white' : 'bg-danger text-white'; ?> rounded">
                                                <?php echo $answer['pilihan_teks'] ?? 'Tidak menjawab'; ?>
                                                <?php if ($answer['is_benar']): ?>
                                                    <i class="fas fa-check-circle float-end"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-times-circle float-end"></i>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="p-3 bg-light rounded <?php echo $answer['jenis'] === 'coding' ? 'font-monospace' : ''; ?>">
                                                <?php echo $answer['jawaban'] ?? 'Tidak menjawab'; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
    .question-content {
        line-height: 1.6;
    }
    
    .question-content img {
        max-width: 100%;
        height: auto;
    }
    
    .question-content pre {
        background-color: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
        overflow-x: auto;
    }
    
    .question-content code {
        background-color: #f1f1f1;
        padding: 2px 4px;
        border-radius: 4px;
    }
</style>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 