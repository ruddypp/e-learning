<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has teacher role
checkAccess(['guru']);

// Check if quiz ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('error', 'ID Quiz tidak ditemukan.');
    header('Location: quizzes.php');
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
    header('Location: quizzes.php');
    exit;
}

$quiz = mysqli_fetch_assoc($result_quiz);

// Verify the quiz belongs to this teacher
if ($quiz['dibuat_oleh'] !== $_SESSION['user_id']) {
    setFlashMessage('error', 'Anda tidak memiliki izin untuk mengedit quiz ini.');
    header('Location: quizzes.php');
    exit;
}

// Process form submission for questions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_question' || $_POST['action'] === 'edit_question') {
            $soal_id = isset($_POST['soal_id']) ? sanitizeInput($_POST['soal_id']) : generateUniqueId('QST');
            $pertanyaan = sanitizeInput($_POST['pertanyaan'], false); // Allow HTML for rich content
            $jenis = sanitizeInput($_POST['jenis']);
            $bobot = (int)sanitizeInput($_POST['bobot']);
            
            if ($_POST['action'] === 'add_question') {
                $query = "INSERT INTO soal_quiz (id, tugas_id, pertanyaan, jenis, bobot) 
                          VALUES ('$soal_id', '$quiz_id', '$pertanyaan', '$jenis', $bobot)";
                
                if (mysqli_query($conn, $query)) {
                    $success = true;
                    
                    // If it's a multiple choice question, add the options
                    if ($jenis === 'pilihan_ganda') {
                        $success = processMultipleChoiceOptions($soal_id, $_POST);
                    }
                    
                    if ($success) {
                        setFlashMessage('success', 'Soal berhasil ditambahkan.');
                        
                        // Log activity
                        logActivity($_SESSION['user_id'], 'tambah_soal', "Guru menambahkan soal baru pada quiz: {$quiz['judul']}");
                    } else {
                        setFlashMessage('error', 'Gagal menambahkan pilihan jawaban.');
                    }
                } else {
                    setFlashMessage('error', 'Gagal menambahkan soal: ' . mysqli_error($conn));
                }
            } else { // Edit action
                $query = "UPDATE soal_quiz SET 
                          pertanyaan = '$pertanyaan',
                          jenis = '$jenis', 
                          bobot = $bobot
                          WHERE id = '$soal_id' AND tugas_id = '$quiz_id'";
                
                if (mysqli_query($conn, $query)) {
                    $success = true;
                    
                    // If it's a multiple choice question, update the options
                    if ($jenis === 'pilihan_ganda') {
                        // Delete existing options
                        $delete_options = "DELETE FROM pilihan_jawaban WHERE soal_id = '$soal_id'";
                        mysqli_query($conn, $delete_options);
                        
                        // Add new options
                        $success = processMultipleChoiceOptions($soal_id, $_POST);
                    }
                    
                    if ($success) {
                        setFlashMessage('success', 'Soal berhasil diperbarui.');
                        
                        // Log activity
                        logActivity($_SESSION['user_id'], 'edit_soal', "Guru mengedit soal pada quiz: {$quiz['judul']}");
                    } else {
                        setFlashMessage('error', 'Gagal memperbarui pilihan jawaban.');
                    }
                } else {
                    setFlashMessage('error', 'Gagal memperbarui soal: ' . mysqli_error($conn));
                }
            }
            
            // Redirect to refresh the page
            header('Location: quiz_edit.php?id=' . $quiz_id);
            exit;
        } elseif ($_POST['action'] === 'delete_question' && isset($_POST['soal_id'])) {
            $soal_id = sanitizeInput($_POST['soal_id']);
            
            // Check if the question exists and belongs to this quiz
            $check_query = "SELECT id FROM soal_quiz WHERE id = '$soal_id' AND tugas_id = '$quiz_id'";
            $check_result = mysqli_query($conn, $check_query);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Begin transaction
                $conn->begin_transaction();
                
                try {
                    // Delete multiple choice options if any
                    $delete_options = "DELETE FROM pilihan_jawaban WHERE soal_id = '$soal_id'";
                    $conn->query($delete_options);
                    
                    // Delete student answers for this question
                    $delete_answers = "DELETE ja FROM jawaban_siswa ja
                                    JOIN nilai_tugas nt ON ja.nilai_tugas_id = nt.id
                                    WHERE ja.soal_id = '$soal_id' AND nt.tugas_id = '$quiz_id'";
                    $conn->query($delete_answers);
                    
                    // Delete the question
                    $delete_question = "DELETE FROM soal_quiz WHERE id = '$soal_id'";
                    $conn->query($delete_question);
                    
                    $conn->commit();
                    
                    setFlashMessage('success', 'Soal berhasil dihapus.');
                    
                    // Log activity
                    logActivity($_SESSION['user_id'], 'hapus_soal', "Guru menghapus soal dari quiz: {$quiz['judul']}");
                } catch (Exception $e) {
                    $conn->rollback();
                    setFlashMessage('error', 'Gagal menghapus soal: ' . $e->getMessage());
                }
            } else {
                setFlashMessage('error', 'Soal tidak ditemukan atau tidak terkait dengan quiz ini.');
            }
            
            // Redirect to refresh the page
            header('Location: quiz_edit.php?id=' . $quiz_id);
            exit;
        }
    }
}

// Get all questions for this quiz
$query_questions = "SELECT * FROM soal_quiz WHERE tugas_id = '$quiz_id' ORDER BY id ASC";
$result_questions = mysqli_query($conn, $query_questions);

// Get question data if edit action is requested
$edit_question = null;
$question_options = [];
if (isset($_GET['action']) && $_GET['action'] === 'edit_question' && isset($_GET['soal_id'])) {
    $soal_id = sanitizeInput($_GET['soal_id']);
    $query = "SELECT * FROM soal_quiz WHERE id = '$soal_id' AND tugas_id = '$quiz_id'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) > 0) {
        $edit_question = mysqli_fetch_assoc($result);
        
        // If it's a multiple choice question, get the options
        if ($edit_question['jenis'] === 'pilihan_ganda') {
            $query_options = "SELECT * FROM pilihan_jawaban WHERE soal_id = '$soal_id' ORDER BY id ASC";
            $result_options = mysqli_query($conn, $query_options);
            
            while ($option = mysqli_fetch_assoc($result_options)) {
                $question_options[] = $option;
            }
        }
    }
}

// Include header
include_once '../../includes/header.php';

// Helper function to process multiple choice options
function processMultipleChoiceOptions($soal_id, $post_data) {
    global $conn;
    
    $options_count = isset($post_data['option_count']) ? (int)$post_data['option_count'] : 0;
    $correct_option = isset($post_data['correct_option']) ? (int)$post_data['correct_option'] : -1;
    
    if ($options_count < 2) {
        return false;
    }
    
    for ($i = 0; $i < $options_count; $i++) {
        $option_text = sanitizeInput($post_data['option_' . $i], false);
        $is_correct = ($i === $correct_option) ? 1 : 0;
        $option_id = generateUniqueId('OPT');
        
        $query = "INSERT INTO pilihan_jawaban (id, soal_id, teks, is_benar) 
                 VALUES ('$option_id', '$soal_id', '$option_text', $is_correct)";
        
        if (!mysqli_query($conn, $query)) {
            return false;
        }
    }
    
    return true;
}
?>

<div class="container-fluid py-4">
    <div class="mb-4">
        <a href="quizzes.php" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="fas fa-arrow-left me-2"></i> Kembali ke Daftar Quiz
        </a>
        <div class="d-flex justify-content-between align-items-center">
            <h1 class="h3">Edit Soal Quiz: <?php echo $quiz['judul']; ?></h1>
            <a href="quiz_detail.php?id=<?php echo $quiz_id; ?>" class="btn btn-primary">
                <i class="fas fa-eye me-2"></i> Lihat Preview Quiz
            </a>
        </div>
        <p>
            <span class="badge bg-info me-2"><?php echo $quiz['kelas_nama']; ?></span>
            <span class="badge bg-secondary me-2">Materi: <?php echo $quiz['materi_judul']; ?></span>
            <?php if ($quiz['tanggal_deadline']): ?>
                <span class="badge bg-warning">Deadline: <?php echo formatDate($quiz['tanggal_deadline']); ?></span>
            <?php endif; ?>
        </p>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <div class="row">
        <div class="col-md-4">
            <!-- Add/Edit Question Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><?php echo $edit_question ? 'Edit Soal' : 'Tambah Soal Baru'; ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="quiz_edit.php?id=<?php echo $quiz_id; ?>" id="questionForm">
                        <input type="hidden" name="action" value="<?php echo $edit_question ? 'edit_question' : 'add_question'; ?>">
                        <?php if ($edit_question): ?>
                            <input type="hidden" name="soal_id" value="<?php echo $edit_question['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="jenis" class="form-label">Jenis Soal <span class="text-danger">*</span></label>
                            <select class="form-select" id="jenis" name="jenis" required>
                                <option value="pilihan_ganda" <?php echo ($edit_question && $edit_question['jenis'] === 'pilihan_ganda') ? 'selected' : ''; ?>>Pilihan Ganda</option>
                                <option value="essay" <?php echo ($edit_question && $edit_question['jenis'] === 'essay') ? 'selected' : ''; ?>>Essay</option>
                                <option value="coding" <?php echo ($edit_question && $edit_question['jenis'] === 'coding') ? 'selected' : ''; ?>>Coding</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="bobot" class="form-label">Bobot Nilai <span class="text-danger">*</span></label>
                            <select class="form-select" id="bobot" name="bobot" required>
                                <option value="1" <?php echo (!$edit_question || $edit_question['bobot'] == 1) ? 'selected' : ''; ?>>1</option>
                                <option value="2" <?php echo ($edit_question && $edit_question['bobot'] == 2) ? 'selected' : ''; ?>>2</option>
                                <option value="3" <?php echo ($edit_question && $edit_question['bobot'] == 3) ? 'selected' : ''; ?>>3</option>
                                <option value="4" <?php echo ($edit_question && $edit_question['bobot'] == 4) ? 'selected' : ''; ?>>4</option>
                                <option value="5" <?php echo ($edit_question && $edit_question['bobot'] == 5) ? 'selected' : ''; ?>>5</option>
                            </select>
                            <small class="form-text text-muted">Semakin tinggi bobot, semakin besar pengaruhnya terhadap nilai.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="pertanyaan" class="form-label">Pertanyaan <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="pertanyaan" name="pertanyaan" rows="5" required><?php echo $edit_question ? $edit_question['pertanyaan'] : ''; ?></textarea>
                            <small class="form-text text-muted">
                                Untuk soal coding, Anda dapat menambahkan kode template yang akan ditampilkan kepada siswa.
                            </small>
                        </div>
                        
                        <!-- Multiple Choice Options (Dynamic) -->
                        <div id="multipleChoiceOptions" class="<?php echo ($edit_question && $edit_question['jenis'] !== 'pilihan_ganda') ? 'd-none' : ''; ?>">
                            <h6 class="mb-3">Pilihan Jawaban</h6>
                            
                            <div id="optionsContainer">
                                <?php if (!empty($question_options)): ?>
                                    <?php foreach ($question_options as $index => $option): ?>
                                        <div class="mb-3 option-item">
                                            <div class="input-group">
                                                <div class="input-group-text">
                                                    <input type="radio" name="correct_option" value="<?php echo $index; ?>" 
                                                           <?php echo $option['is_benar'] ? 'checked' : ''; ?> required>
                                                </div>
                                                <input type="text" class="form-control option-text" 
                                                       name="option_<?php echo $index; ?>" 
                                                       value="<?php echo $option['teks']; ?>" required>
                                                <button type="button" class="btn btn-outline-danger remove-option">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="mb-3 option-item">
                                        <div class="input-group">
                                            <div class="input-group-text">
                                                <input type="radio" name="correct_option" value="0" required>
                                            </div>
                                            <input type="text" class="form-control option-text" name="option_0" required>
                                            <button type="button" class="btn btn-outline-danger remove-option">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="mb-3 option-item">
                                        <div class="input-group">
                                            <div class="input-group-text">
                                                <input type="radio" name="correct_option" value="1" required>
                                            </div>
                                            <input type="text" class="form-control option-text" name="option_1" required>
                                            <button type="button" class="btn btn-outline-danger remove-option">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <input type="hidden" name="option_count" id="optionCount" 
                                   value="<?php echo !empty($question_options) ? count($question_options) : 2; ?>">
                            
                            <button type="button" class="btn btn-sm btn-outline-primary mb-3" id="addOption">
                                <i class="fas fa-plus me-1"></i> Tambah Pilihan
                            </button>
                            
                            <div class="alert alert-info">
                                <small>
                                    <i class="fas fa-info-circle me-1"></i> Pilih radio button di samping pilihan yang benar.
                                </small>
                            </div>
                        </div>
                        
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary">
                                <?php echo $edit_question ? 'Perbarui Soal' : 'Tambah Soal'; ?>
                            </button>
                            <?php if ($edit_question): ?>
                                <a href="quiz_edit.php?id=<?php echo $quiz_id; ?>" class="btn btn-outline-secondary mt-2">
                                    Batal Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <!-- Questions List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Soal</h5>
                    <span class="badge bg-primary"><?php echo mysqli_num_rows($result_questions); ?> Soal</span>
                </div>
                <div class="card-body">
                    <?php if (mysqli_num_rows($result_questions) > 0): ?>
                        <div class="accordion" id="accordionQuestions">
                            <?php $question_number = 1; ?>
                            <?php while ($question = mysqli_fetch_assoc($result_questions)): ?>
                                <div class="accordion-item mb-3 border">
                                    <h2 class="accordion-header" id="heading<?php echo $question['id']; ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                                data-bs-target="#collapse<?php echo $question['id']; ?>" 
                                                aria-expanded="false" aria-controls="collapse<?php echo $question['id']; ?>">
                                            <div class="d-flex align-items-center w-100">
                                                <span class="badge bg-secondary me-2"><?php echo $question_number++; ?></span>
                                                <div class="me-auto">
                                                    <?php echo limitText(strip_tags($question['pertanyaan']), 70); ?>
                                                </div>
                                                <div class="d-flex align-items-center ms-3">
                                                    <span class="badge bg-info me-2">
                                                        <?php 
                                                        switch ($question['jenis']) {
                                                            case 'pilihan_ganda':
                                                                echo 'Pilihan Ganda';
                                                                break;
                                                            case 'essay':
                                                                echo 'Essay';
                                                                break;
                                                            case 'coding':
                                                                echo 'Coding';
                                                                break;
                                                        }
                                                        ?>
                                                    </span>
                                                    <span class="badge bg-warning">Bobot: <?php echo $question['bobot']; ?></span>
                                                </div>
                                            </div>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $question['id']; ?>" class="accordion-collapse collapse" 
                                         aria-labelledby="heading<?php echo $question['id']; ?>" 
                                         data-bs-parent="#accordionQuestions">
                                        <div class="accordion-body">
                                            <div class="mb-3">
                                                <h6>Pertanyaan:</h6>
                                                <div class="question-content p-3 bg-light rounded mb-3">
                                                    <?php echo $question['pertanyaan']; ?>
                                                </div>
                                            </div>
                                            
                                            <?php if ($question['jenis'] === 'pilihan_ganda'): ?>
                                                <?php
                                                $options_query = "SELECT * FROM pilihan_jawaban WHERE soal_id = '{$question['id']}' ORDER BY id ASC";
                                                $options_result = mysqli_query($conn, $options_query);
                                                ?>
                                                <div class="mb-3">
                                                    <h6>Pilihan Jawaban:</h6>
                                                    <ul class="list-group">
                                                        <?php while ($option = mysqli_fetch_assoc($options_result)): ?>
                                                            <li class="list-group-item <?php echo $option['is_benar'] ? 'list-group-item-success' : ''; ?>">
                                                                <?php echo $option['teks']; ?>
                                                                <?php if ($option['is_benar']): ?>
                                                                    <span class="badge bg-success float-end">Jawaban Benar</span>
                                                                <?php endif; ?>
                                                            </li>
                                                        <?php endwhile; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-end">
                                                <a href="quiz_edit.php?id=<?php echo $quiz_id; ?>&action=edit_question&soal_id=<?php echo $question['id']; ?>" 
                                                   class="btn btn-sm btn-info me-2">
                                                    <i class="fas fa-edit me-1"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="confirmDeleteQuestion('<?php echo $question['id']; ?>')">
                                                    <i class="fas fa-trash me-1"></i> Hapus
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> 
                            Belum ada soal untuk quiz ini. Tambahkan soal menggunakan form di samping.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Question Modal -->
<div class="modal fade" id="deleteQuestionModal" tabindex="-1" aria-labelledby="deleteQuestionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteQuestionModalLabel">Konfirmasi Hapus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Anda yakin ingin menghapus soal ini?</p>
                <p class="text-danger">Tindakan ini tidak dapat dibatalkan dan akan menghapus semua jawaban siswa terkait soal ini.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <form method="POST" action="quiz_edit.php?id=<?php echo $quiz_id; ?>">
                    <input type="hidden" name="action" value="delete_question">
                    <input type="hidden" name="soal_id" id="delete-question-id">
                    <button type="submit" class="btn btn-danger">Hapus</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const jenisSelect = document.getElementById('jenis');
        const multipleChoiceOptions = document.getElementById('multipleChoiceOptions');
        const optionsContainer = document.getElementById('optionsContainer');
        const addOptionBtn = document.getElementById('addOption');
        const optionCountInput = document.getElementById('optionCount');
        
        // Show/hide multiple choice options based on question type
        jenisSelect.addEventListener('change', function() {
            if (this.value === 'pilihan_ganda') {
                multipleChoiceOptions.classList.remove('d-none');
            } else {
                multipleChoiceOptions.classList.add('d-none');
            }
        });
        
        // Add new option
        addOptionBtn.addEventListener('click', function() {
            const optionCount = parseInt(optionCountInput.value);
            
            const newOption = document.createElement('div');
            newOption.className = 'mb-3 option-item';
            newOption.innerHTML = `
                <div class="input-group">
                    <div class="input-group-text">
                        <input type="radio" name="correct_option" value="${optionCount}" required>
                    </div>
                    <input type="text" class="form-control option-text" name="option_${optionCount}" required>
                    <button type="button" class="btn btn-outline-danger remove-option">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            
            optionsContainer.appendChild(newOption);
            optionCountInput.value = optionCount + 1;
            
            // Add event listener to the new remove button
            const removeBtn = newOption.querySelector('.remove-option');
            removeBtn.addEventListener('click', removeOption);
        });
        
        // Remove option
        function removeOption() {
            const options = document.querySelectorAll('.option-item');
            
            // Don't allow removing if there are only 2 options
            if (options.length <= 2) {
                alert('Soal pilihan ganda harus memiliki minimal 2 pilihan.');
                return;
            }
            
            // Remove the option
            this.closest('.option-item').remove();
            
            // Renumber options
            const optionInputs = document.querySelectorAll('.option-text');
            const radioInputs = document.querySelectorAll('input[name="correct_option"]');
            
            optionInputs.forEach((input, index) => {
                input.name = `option_${index}`;
            });
            
            radioInputs.forEach((radio, index) => {
                radio.value = index;
            });
            
            optionCountInput.value = optionInputs.length;
        }
        
        // Add event listeners to existing remove buttons
        document.querySelectorAll('.remove-option').forEach(btn => {
            btn.addEventListener('click', removeOption);
        });
        
        // Form validation before submit
        document.getElementById('questionForm').addEventListener('submit', function(e) {
            if (jenisSelect.value === 'pilihan_ganda') {
                const checkedRadio = document.querySelector('input[name="correct_option"]:checked');
                
                if (!checkedRadio) {
                    e.preventDefault();
                    alert('Silakan pilih jawaban yang benar untuk soal pilihan ganda.');
                    return;
                }
            }
        });
    });
    
    // Confirm delete question
    function confirmDeleteQuestion(questionId) {
        document.getElementById('delete-question-id').value = questionId;
        
        var deleteModal = new bootstrap.Modal(document.getElementById('deleteQuestionModal'));
        deleteModal.show();
    }
</script>

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