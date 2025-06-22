<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has student role
checkAccess(['siswa']);

// Get student ID and class
$student_id = $_SESSION['user_id'];
$query_student = "SELECT kelas_id FROM pengguna WHERE id = '$student_id'";
$result_student = mysqli_query($conn, $query_student);
$student = mysqli_fetch_assoc($result_student);

// Check if student is assigned to a class
if (!$student['kelas_id']) {
    setFlashMessage('error', 'Anda belum terdaftar di kelas manapun.');
    header('Location: dashboard.php');
    exit;
}

// Check if quiz ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Quiz tidak ditemukan.');
    header('Location: dashboard.php');
    exit;
}

$quiz_id = sanitizeInput($_GET['id']);

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

// Check if quiz belongs to student's class
if ($quiz['kelas_id'] !== $student['kelas_id']) {
    setFlashMessage('error', 'Anda tidak memiliki akses untuk mengerjakan quiz ini.');
    header('Location: dashboard.php');
    exit;
}

// Check if quiz is published
if ($quiz['status'] !== 'published') {
    if ($quiz['status'] === 'draft') {
        setFlashMessage('error', 'Quiz ini masih dalam tahap persiapan dan belum dipublikasikan.');
    } else {
        setFlashMessage('error', 'Quiz ini telah ditutup dan tidak tersedia untuk dikerjakan.');
    }
    header('Location: quizzes.php');
    exit;
}

// Check if deadline has passed
if ($quiz['tanggal_deadline'] && strtotime($quiz['tanggal_deadline']) < time()) {
    setFlashMessage('error', 'Batas waktu pengumpulan quiz ini telah berakhir.');
    header('Location: quizzes.php');
    exit;
}

// Check if student has already taken this quiz
$query_attempt = "SELECT id, tanggal_pengumpulan, nilai FROM nilai_tugas 
                 WHERE tugas_id = '$quiz_id' AND siswa_id = '$student_id'";
$result_attempt = mysqli_query($conn, $query_attempt);
$has_attempted = mysqli_num_rows($result_attempt) > 0;
$attempt = mysqli_fetch_assoc($result_attempt);

// Process quiz submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_quiz') {
    // Check again if quiz is still published to prevent submitting to closed quizzes
    $status_check = "SELECT status FROM tugas WHERE id = '$quiz_id'";
    $status_result = mysqli_query($conn, $status_check);
    $current_status = mysqli_fetch_assoc($status_result)['status'];
    
    if ($current_status !== 'published') {
        setFlashMessage('error', 'Quiz ini tidak lagi tersedia untuk dikerjakan.');
        header('Location: quizzes.php');
        exit;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Create or update nilai_tugas record
        $nilai_tugas_id = isset($attempt['id']) ? $attempt['id'] : generateUniqueId('NT');
        
        if (!$has_attempted) {
            $query_nilai = "INSERT INTO nilai_tugas (id, tugas_id, siswa_id, tanggal_pengumpulan) 
                           VALUES ('$nilai_tugas_id', '$quiz_id', '$student_id', CURDATE())";
        } else {
            $query_nilai = "UPDATE nilai_tugas SET tanggal_pengumpulan = CURDATE() WHERE id = '$nilai_tugas_id'";
        }
        
        $conn->query($query_nilai);
        
        // Get all questions for this quiz
        $query_questions = "SELECT * FROM soal_quiz WHERE tugas_id = '$quiz_id'";
        $result_questions = mysqli_query($conn, $query_questions);
        
        // Remove previous answers if any
        if ($has_attempted) {
            $delete_answers = "DELETE FROM jawaban_siswa WHERE nilai_tugas_id = '$nilai_tugas_id'";
            $conn->query($delete_answers);
        }
        
        // Process each question's answer
        while ($question = mysqli_fetch_assoc($result_questions)) {
            $soal_id = $question['id'];
            $jawaban_id = generateUniqueId('JWB');
            
            if ($question['jenis'] === 'pilihan_ganda') {
                $pilihan_id = isset($_POST['answer_' . $soal_id]) ? sanitizeInput($_POST['answer_' . $soal_id]) : null;
                $jawaban = null;
                
                $query_jawaban = "INSERT INTO jawaban_siswa (id, nilai_tugas_id, soal_id, jawaban, pilihan_id) 
                                 VALUES ('$jawaban_id', '$nilai_tugas_id', '$soal_id', NULL, " . 
                                 ($pilihan_id ? "'$pilihan_id'" : "NULL") . ")";
            } else { // Essay or coding
                $jawaban = isset($_POST['answer_' . $soal_id]) ? sanitizeInput($_POST['answer_' . $soal_id], false) : null;
                $pilihan_id = null;
                
                $query_jawaban = "INSERT INTO jawaban_siswa (id, nilai_tugas_id, soal_id, jawaban, pilihan_id) 
                                 VALUES ('$jawaban_id', '$nilai_tugas_id', '$soal_id', " . 
                                 ($jawaban ? "'$jawaban'" : "NULL") . ", NULL)";
            }
            
            $conn->query($query_jawaban);
        }
        
        // Auto-grade multiple choice questions
        autoGradeMultipleChoice($nilai_tugas_id, $conn);
        
        $conn->commit();
        
        // Log activity
        logActivity($student_id, 'submit_tugas', "Siswa mengumpulkan jawaban quiz: {$quiz['judul']}", $quiz_id);
        
        setFlashMessage('success', 'Jawaban quiz berhasil disimpan.');
        header('Location: quiz_result.php?id=' . $quiz_id);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        setFlashMessage('error', 'Gagal menyimpan jawaban: ' . $e->getMessage());
    }
}

// Get all questions for this quiz
$query_questions = "SELECT * FROM soal_quiz WHERE tugas_id = '$quiz_id' ORDER BY id ASC";
$result_questions = mysqli_query($conn, $query_questions);
$questions = [];
$total_bobot = 0;

while ($row = mysqli_fetch_assoc($result_questions)) {
    $questions[] = $row;
    $total_bobot += $row['bobot'];
}

// Get student's previous answers if any
$previous_answers = [];
if ($has_attempted) {
    $query_answers = "SELECT * FROM jawaban_siswa WHERE nilai_tugas_id = '{$attempt['id']}'";
    $result_answers = mysqli_query($conn, $query_answers);
    
    while ($answer = mysqli_fetch_assoc($result_answers)) {
        $previous_answers[$answer['soal_id']] = $answer;
    }
}

// Include header
include_once '../../includes/header.php';

// Helper function to auto-grade all questions
function autoGradeMultipleChoice($nilai_tugas_id, $conn) {
    // Get all answers (both multiple choice and other types)
    $query = "SELECT js.id, js.soal_id, js.pilihan_id, js.jawaban, sq.jenis, sq.bobot, pj.is_benar
             FROM jawaban_siswa js
             JOIN soal_quiz sq ON js.soal_id = sq.id
             LEFT JOIN pilihan_jawaban pj ON js.pilihan_id = pj.id
             WHERE js.nilai_tugas_id = '$nilai_tugas_id'";
    $result = $conn->query($query);
    
    $total_bobot = 0;
    $earned_bobot = 0;
    
    while ($answer = $result->fetch_assoc()) {
        $jawaban_id = $answer['id'];
        $bobot = $answer['bobot'];
        $jenis = $answer['jenis'];
        $nilai_per_soal = 0;
        
        // For multiple choice questions, we can auto-grade
        if ($jenis === 'pilihan_ganda') {
            $is_benar = $answer['is_benar'] ?? 0;
            $nilai_per_soal = $is_benar ? $bobot : 0;
        } 
        // For essay and coding questions, give full points if they provided an answer
        else {
            if (!empty($answer['jawaban'])) {
                $nilai_per_soal = $bobot; // Give full points for non-empty answers
            }
        }
        
        // Update nilai_per_soal in jawaban_siswa
        $update = "UPDATE jawaban_siswa SET nilai_per_soal = $nilai_per_soal WHERE id = '$jawaban_id'";
        $conn->query($update);
        
        $total_bobot += $bobot;
        $earned_bobot += $nilai_per_soal;
    }
    
    // Calculate total quiz score for all questions
    if ($total_bobot > 0) {
        $nilai = round(($earned_bobot / $total_bobot) * 100);
        
        // Update nilai in nilai_tugas and mark as automatically graded
        $update_nilai = "UPDATE nilai_tugas SET 
                        nilai = $nilai, 
                        tanggal_dinilai = CURDATE(),
                        dinilai_oleh = 'AUTO' 
                        WHERE id = '$nilai_tugas_id'";
        $conn->query($update_nilai);
    }
}
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="quizzes.php" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Quiz
        </a>
        <h1 class="h3"><?php echo $quiz['judul']; ?></h1>
        <p>
            <span class="badge bg-info me-2"><?php echo $quiz['kelas_nama']; ?></span>
            <span class="badge bg-secondary me-2">Materi: <?php echo $quiz['materi_judul']; ?></span>
            <?php if ($quiz['tanggal_deadline']): ?>
                <span class="badge bg-warning">Deadline: <?php echo formatDate($quiz['tanggal_deadline']); ?></span>
            <?php endif; ?>
        </p>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <?php if ($has_attempted && $attempt['nilai']): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Anda telah mengerjakan quiz ini dan mendapatkan nilai <strong><?php echo $attempt['nilai']; ?></strong>.
            <a href="quiz_result.php?id=<?php echo $quiz_id; ?>" class="alert-link">Lihat hasil</a>.
        </div>
    <?php elseif ($has_attempted): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Anda telah mengerjakan quiz ini pada tanggal <?php echo formatDate($attempt['tanggal_pengumpulan']); ?>.
            <a href="quiz_result.php?id=<?php echo $quiz_id; ?>" class="alert-link">Lihat hasil</a>.
        </div>
    <?php endif; ?>
    
    <?php if (empty($questions)): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            Quiz ini belum memiliki soal. Silakan hubungi guru Anda.
        </div>
    <?php else: ?>
        <!-- Quiz Instructions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Petunjuk Quiz</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <?php echo $quiz['deskripsi']; ?>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2">
                                <i class="fas fa-question-circle fa-2x text-primary"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Jumlah Soal</h6>
                                <p class="mb-0"><?php echo count($questions); ?> soal</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2">
                                <i class="fas fa-star fa-2x text-warning"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Total Bobot</h6>
                                <p class="mb-0"><?php echo $total_bobot; ?> poin</p>
                            </div>
                        </div>
                    </div>
                    <?php if ($quiz['tanggal_deadline']): ?>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2">
                                <i class="fas fa-clock fa-2x text-danger"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Batas Waktu</h6>
                                <p class="mb-0"><?php echo formatDate($quiz['tanggal_deadline']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quiz Form -->
        <form method="POST" action="" id="quizForm">
            <input type="hidden" name="action" value="submit_quiz">
            
            <?php foreach ($questions as $index => $question): ?>
                <div class="card mb-4 question-card" id="question-<?php echo $index + 1; ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Soal <?php echo $index + 1; ?> <small class="text-muted">(<?php echo $question['bobot']; ?> poin)</small></h5>
                        <span class="badge bg-secondary"><?php echo $question['jenis'] === 'pilihan_ganda' ? 'Pilihan Ganda' : ($question['jenis'] === 'essay' ? 'Esai' : 'Coding'); ?></span>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <?php echo $question['pertanyaan']; ?>
                        </div>
                        
                        <?php if ($question['jenis'] === 'pilihan_ganda'): ?>
                            <?php
                            // Get options for multiple choice
                            $query_options = "SELECT * FROM pilihan_jawaban WHERE soal_id = '{$question['id']}'";
                            $result_options = mysqli_query($conn, $query_options);
                            $options = [];
                            
                            while ($option = mysqli_fetch_assoc($result_options)) {
                                $options[] = $option;
                            }
                            
                            // Shuffle options
                            shuffle($options);
                            
                            // Get previous selection if any
                            $selected = isset($previous_answers[$question['id']]) ? $previous_answers[$question['id']]['pilihan_id'] : null;
                            ?>
                            
                            <div class="list-group">
                                <?php foreach ($options as $option): ?>
                                    <label class="list-group-item list-group-item-action">
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <input type="radio" name="answer_<?php echo $question['id']; ?>" 
                                                      value="<?php echo $option['id']; ?>" class="form-check-input"
                                                      <?php echo ($selected === $option['id']) ? 'checked' : ''; ?>>
                                            </div>
                                            <div>
                                                <?php echo $option['teks']; ?>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($question['jenis'] === 'essay'): ?>
                            <?php
                            // Get previous answer if any
                            $previous_answer = isset($previous_answers[$question['id']]) ? $previous_answers[$question['id']]['jawaban'] : '';
                            ?>
                            
                            <div class="mb-3">
                                <textarea class="form-control" name="answer_<?php echo $question['id']; ?>" 
                                         rows="5" placeholder="Ketik jawaban Anda di sini..."><?php echo $previous_answer; ?></textarea>
                            </div>
                        <?php else: ?>
                            <?php
                            // Get previous answer if any
                            $previous_answer = isset($previous_answers[$question['id']]) ? $previous_answers[$question['id']]['jawaban'] : '';
                            ?>
                            
                            <div class="mb-3">
                                <textarea class="form-control code-editor" name="answer_<?php echo $question['id']; ?>" 
                                         rows="10" placeholder="Tulis kode Anda di sini..."><?php echo $previous_answer; ?></textarea>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="d-grid gap-2 col-md-6 mx-auto mb-4">
                <?php if (!$has_attempted || !$attempt['nilai']): ?>
                    <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Apakah Anda yakin ingin mengirimkan jawaban? Pastikan semua jawaban telah terisi.')">
                        <i class="fas fa-paper-plane me-2"></i> Kirim Jawaban
                    </button>
                <?php else: ?>
                    <a href="quiz_result.php?id=<?php echo $quiz_id; ?>" class="btn btn-info btn-lg">
                        <i class="fas fa-eye me-2"></i> Lihat Hasil Quiz
                    </a>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// Add handling for code editor if needed
document.addEventListener('DOMContentLoaded', function() {
    // Initialize code editors
    const codeEditors = document.querySelectorAll('.code-editor');
    if (codeEditors.length > 0) {
        // If you want to use a code editor library, initialize it here
    }
});
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 