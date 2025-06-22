<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has student role
checkAccess(['siswa']);

// Get student's information
$student_id = $_SESSION['user_id'];
$query_student = "SELECT kelas_id FROM pengguna WHERE id = '$student_id'";
$result_student = mysqli_query($conn, $query_student);
$student = mysqli_fetch_assoc($result_student);
$class_id = $student['kelas_id'];

// Check if questionnaire ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Kuesioner tidak ditemukan.');
    header('Location: dashboard.php');
    exit;
}

$questionnaire_id = sanitizeInput($_GET['id']);

// Get questionnaire details and verify it's for this student's class
$query_questionnaire = "SELECT k.*, c.nama as kelas_nama, c.tahun_ajaran 
                      FROM kuesioner k 
                      JOIN kelas c ON k.kelas_id = c.id 
                      WHERE k.id = '$questionnaire_id' AND k.kelas_id = '$class_id'";
$result_questionnaire = mysqli_query($conn, $query_questionnaire);

if (mysqli_num_rows($result_questionnaire) === 0) {
    setFlashMessage('error', 'Kuesioner tidak ditemukan atau tidak tersedia untuk kelas Anda.');
    header('Location: dashboard.php');
    exit;
}

$questionnaire = mysqli_fetch_assoc($result_questionnaire);

// Check if student has already completed this questionnaire
$query_check_completed = "SELECT COUNT(*) as completed FROM jawaban_kuesioner jk
                         JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id
                         WHERE pk.kuesioner_id = '$questionnaire_id' AND jk.siswa_id = '$student_id'";
$result_check_completed = mysqli_query($conn, $query_check_completed);
$completed = mysqli_fetch_assoc($result_check_completed)['completed'] > 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_questionnaire') {
    // Check if student has already completed this questionnaire
    if ($completed) {
        setFlashMessage('error', 'Anda sudah mengisi kuesioner ini sebelumnya.');
        header('Location: dashboard.php');
        exit;
    }
    
    // Get questions for this questionnaire
    $query_questions = "SELECT * FROM pertanyaan_kuesioner WHERE kuesioner_id = '$questionnaire_id'";
    $result_questions = mysqli_query($conn, $query_questions);
    
    // Process each question's answer
    $success = true;
    
    while ($question = mysqli_fetch_assoc($result_questions)) {
        $question_id = $question['id'];
        $answer_key = 'question_' . $question_id;
        
        // Skip if answer is not provided
        if (!isset($_POST[$answer_key]) || empty($_POST[$answer_key])) {
            continue;
        }
        
        $answer = sanitizeInput($_POST[$answer_key], false);
        
        // Generate ID for answer
        $answer_id = generateID('JK', 'jawaban_kuesioner', 'id');
        
        // Insert answer
        $query_insert = "INSERT INTO jawaban_kuesioner (id, pertanyaan_id, siswa_id, jawaban, tanggal_jawab)
                        VALUES ('$answer_id', '$question_id', '$student_id', '$answer', CURDATE())";
        
        if (!mysqli_query($conn, $query_insert)) {
            $success = false;
            break;
        }
    }
    
    if ($success) {
        // Log activity
        logActivity($student_id, 'submit_tugas', "Siswa mengisi kuesioner: {$questionnaire['judul']}");
        
        setFlashMessage('success', 'Terima kasih! Jawaban kuesioner Anda telah berhasil disimpan.');
        header('Location: dashboard.php');
    } else {
        setFlashMessage('error', 'Terjadi kesalahan saat menyimpan jawaban kuesioner: ' . mysqli_error($conn));
    }
    
    exit;
}

// Get questions for this questionnaire
$query_questions = "SELECT * FROM pertanyaan_kuesioner 
                   WHERE kuesioner_id = '$questionnaire_id' 
                   ORDER BY id ASC";
$result_questions = mysqli_query($conn, $query_questions);

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="dashboard.php" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
        </a>
        
        <h1 class="h3">Kuesioner: <?php echo $questionnaire['judul']; ?></h1>
        <p>
            <span class="badge bg-info me-2">Kelas: <?php echo $questionnaire['kelas_nama'] . ' (' . $questionnaire['tahun_ajaran'] . ')'; ?></span>
        </p>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <?php if ($completed): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle me-2"></i>
            Anda telah mengisi kuesioner ini. Terima kasih atas partisipasi Anda!
        </div>
    <?php else: ?>
        <?php if (mysqli_num_rows($result_questions) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Formulir Kuesioner</h5>
                </div>
                <div class="card-body">
                    <?php if ($questionnaire['deskripsi']): ?>
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <?php echo $questionnaire['deskripsi']; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="questionnaire.php?id=<?php echo $questionnaire_id; ?>">
                        <input type="hidden" name="action" value="submit_questionnaire">
                        
                        <?php $question_number = 1; ?>
                        <?php mysqli_data_seek($result_questions, 0); ?>
                        <?php while ($question = mysqli_fetch_assoc($result_questions)): ?>
                            <div class="mb-4 p-3 border rounded">
                                <label class="form-label">
                                    <strong><?php echo $question_number++; ?>. <?php echo $question['pertanyaan']; ?></strong>
                                    <?php
                                    $badge_class = '';
                                    $badge_text = '';
                                    
                                    switch ($question['jenis']) {
                                        case 'text':
                                            $badge_class = 'bg-primary';
                                            $badge_text = 'Jawaban Terbuka';
                                            break;
                                        case 'skala':
                                            $badge_class = 'bg-info';
                                            $badge_text = 'Skala 1-5';
                                            break;
                                        case 'pilihan_ganda':
                                            $badge_class = 'bg-warning';
                                            $badge_text = 'Ya/Tidak';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?> ms-2"><?php echo $badge_text; ?></span>
                                </label>
                                
                                <?php if ($question['jenis'] === 'text'): ?>
                                    <textarea class="form-control" name="question_<?php echo $question['id']; ?>" rows="3" required></textarea>
                                <?php elseif ($question['jenis'] === 'skala'): ?>
                                    <div class="rating-container d-flex justify-content-between align-items-center py-2">
                                        <div class="text-muted small">Sangat Tidak Setuju</div>
                                        <div class="btn-group" role="group">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <input type="radio" class="btn-check" name="question_<?php echo $question['id']; ?>" id="q<?php echo $question['id']; ?>_<?php echo $i; ?>" value="<?php echo $i; ?>" required>
                                                <label class="btn btn-outline-primary" for="q<?php echo $question['id']; ?>_<?php echo $i; ?>"><?php echo $i; ?></label>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="text-muted small">Sangat Setuju</div>
                                    </div>
                                <?php elseif ($question['jenis'] === 'pilihan_ganda'): ?>
                                    <div class="mt-2">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="question_<?php echo $question['id']; ?>" id="q<?php echo $question['id']; ?>_yes" value="Ya" required>
                                            <label class="form-check-label" for="q<?php echo $question['id']; ?>_yes">Ya</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="question_<?php echo $question['id']; ?>" id="q<?php echo $question['id']; ?>_no" value="Tidak" required>
                                            <label class="form-check-label" for="q<?php echo $question['id']; ?>_no">Tidak</label>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Kirim Jawaban
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Kuesioner ini belum memiliki pertanyaan.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.rating-container {
    background-color: #f8f9fa;
    border-radius: 5px;
    padding: 10px;
    margin-top: 10px;
}

.btn-check:checked + .btn-outline-primary {
    background-color: #0d6efd;
    color: #fff;
}
</style>

<?php include_once '../../includes/footer.php'; ?> 