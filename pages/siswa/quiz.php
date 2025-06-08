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

// Check if deadline has passed
if ($quiz['tanggal_deadline'] && strtotime($quiz['tanggal_deadline']) < time()) {
    setFlashMessage('error', 'Batas waktu pengumpulan quiz ini telah berakhir.');
    header('Location: dashboard.php');
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
        logActivity($student_id, 'submit_tugas', "Siswa mengumpulkan jawaban quiz: {$quiz['judul']}");
        
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

// Helper function to auto-grade multiple choice questions
function autoGradeMultipleChoice($nilai_tugas_id, $conn) {
    // Get all multiple choice answers
    $query = "SELECT js.id, js.soal_id, js.pilihan_id, sq.bobot, pj.is_benar
             FROM jawaban_siswa js
             JOIN soal_quiz sq ON js.soal_id = sq.id
             LEFT JOIN pilihan_jawaban pj ON js.pilihan_id = pj.id
             WHERE js.nilai_tugas_id = '$nilai_tugas_id'
             AND sq.jenis = 'pilihan_ganda'";
    $result = $conn->query($query);
    
    $total_bobot = 0;
    $earned_bobot = 0;
    
    while ($answer = $result->fetch_assoc()) {
        $jawaban_id = $answer['id'];
        $bobot = $answer['bobot'];
        $is_benar = $answer['is_benar'] ?? 0;
        $nilai_per_soal = $is_benar ? $bobot : 0;
        
        // Update nilai_per_soal in jawaban_siswa
        $update = "UPDATE jawaban_siswa SET nilai_per_soal = $nilai_per_soal WHERE id = '$jawaban_id'";
        $conn->query($update);
        
        $total_bobot += $bobot;
        $earned_bobot += $nilai_per_soal;
    }
    
    // Calculate total quiz score (only from multiple choice for now)
    if ($total_bobot > 0) {
        $nilai = round(($earned_bobot / $total_bobot) * 100);
        
        // Update nilai in nilai_tugas (only partial - teacher will complete grading)
        $update_nilai = "UPDATE nilai_tugas SET nilai = $nilai WHERE id = '$nilai_tugas_id'";
        $conn->query($update_nilai);
    }
}
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
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
            Quiz sedang dinilai oleh guru.
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
                                <i class="fas fa-tasks text-primary"></i>
                            </div>
                            <div>
                                <strong>Jumlah Soal:</strong> <?php echo count($questions); ?> soal
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="d-flex align-items-center mb-2">
                            <div class="me-2">
                                <i class="fas fa-weight-hanging text-warning"></i>
                            </div>
                            <div>
                                <strong>Total Bobot:</strong> <?php echo $total_bobot; ?> poin
                            </div>
                        </div>
                    </div>
                    <?php if ($quiz['tanggal_deadline']): ?>
                        <div class="col-md-4">
                            <div class="d-flex align-items-center mb-2">
                                <div class="me-2">
                                    <i class="fas fa-clock text-danger"></i>
                                </div>
                                <div>
                                    <strong>Deadline:</strong> <?php echo formatDate($quiz['tanggal_deadline']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Quiz Form -->
        <form method="POST" action="quiz.php?id=<?php echo $quiz_id; ?>" id="quizForm">
            <input type="hidden" name="action" value="submit_quiz">
            
            <?php $question_number = 1; ?>
            <?php foreach ($questions as $question): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Soal <?php echo $question_number++; ?></h5>
                            <span class="badge bg-warning">Bobot: <?php echo $question['bobot']; ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="question-content mb-4">
                            <?php echo $question['pertanyaan']; ?>
                        </div>
                        
                        <?php if ($question['jenis'] === 'pilihan_ganda'): ?>
                            <?php
                            $options_query = "SELECT * FROM pilihan_jawaban WHERE soal_id = '{$question['id']}'";
                            $options_result = mysqli_query($conn, $options_query);
                            ?>
                            <div class="options-list">
                                <?php while ($option = mysqli_fetch_assoc($options_result)): ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" 
                                               name="answer_<?php echo $question['id']; ?>" 
                                               id="option_<?php echo $option['id']; ?>" 
                                               value="<?php echo $option['id']; ?>"
                                               <?php echo (isset($previous_answers[$question['id']]) && $previous_answers[$question['id']]['pilihan_id'] === $option['id']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="option_<?php echo $option['id']; ?>">
                                            <?php echo $option['teks']; ?>
                                        </label>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php elseif ($question['jenis'] === 'essay'): ?>
                            <div class="mb-3">
                                <textarea class="form-control" 
                                          name="answer_<?php echo $question['id']; ?>" 
                                          rows="5"><?php echo isset($previous_answers[$question['id']]) ? $previous_answers[$question['id']]['jawaban'] : ''; ?></textarea>
                            </div>
                        <?php elseif ($question['jenis'] === 'coding'): ?>
                            <div class="mb-3">
                                <textarea class="form-control code-editor" 
                                          name="answer_<?php echo $question['id']; ?>" 
                                          rows="10"><?php echo isset($previous_answers[$question['id']]) ? $previous_answers[$question['id']]['jawaban'] : ''; ?></textarea>
                                <small class="form-text text-muted">
                                    Tulis kode program Anda di sini. Gunakan indentasi yang benar.
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="d-grid gap-2 col-md-6 mx-auto mb-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-paper-plane me-2"></i> Kirim Jawaban
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='dashboard.php'">
                    Batal
                </button>
            </div>
        </form>
    <?php endif; ?>
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
    
    .code-editor {
        font-family: monospace;
        tab-size: 4;
    }
</style>

<script>
    // Prevent accidental leaving page
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('quizForm');
        
        window.addEventListener('beforeunload', function(e) {
            // If form has been changed
            if (isFormChanged()) {
                e.preventDefault();
                e.returnValue = 'Anda memiliki jawaban yang belum disimpan. Yakin ingin meninggalkan halaman?';
            }
        });
        
        form.addEventListener('submit', function() {
            window.removeEventListener('beforeunload');
        });
        
        function isFormChanged() {
            const radios = document.querySelectorAll('input[type="radio"]:checked');
            const textareas = document.querySelectorAll('textarea');
            
            if (radios.length > 0) return true;
            
            for (let i = 0; i < textareas.length; i++) {
                if (textareas[i].value.trim() !== '') return true;
            }
            
            return false;
        }
    });
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 