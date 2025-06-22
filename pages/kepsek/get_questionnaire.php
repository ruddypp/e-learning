<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has principal or admin role
checkAccess(['kepsek', 'admin', 'guru']);

// Set content type to JSON
header('Content-Type: application/json');

// Check if questionnaire ID is provided
if (!isset($_GET['id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Questionnaire ID is required'
    ]);
    exit;
}

$questionnaire_id = sanitizeInput($_GET['id']);

// Get questionnaire details
$query_questionnaire = "SELECT k.*, p.nama as dibuat_oleh_nama, kl.nama as kelas_nama,
                       (SELECT COUNT(DISTINCT jk.siswa_id) FROM jawaban_kuesioner jk 
                        JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
                        WHERE pk.kuesioner_id = k.id) as jumlah_responden
                       FROM kuesioner k
                       JOIN pengguna p ON k.dibuat_oleh = p.id
                       JOIN kelas kl ON k.kelas_id = kl.id
                       WHERE k.id = '$questionnaire_id'";
$result_questionnaire = mysqli_query($conn, $query_questionnaire);

if (mysqli_num_rows($result_questionnaire) === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Questionnaire not found'
    ]);
    exit;
}

$questionnaire = mysqli_fetch_assoc($result_questionnaire);
$questionnaire['tanggal_dibuat'] = formatDate($questionnaire['tanggal_dibuat']);

// Get questions
$query_questions = "SELECT * FROM pertanyaan_kuesioner WHERE kuesioner_id = '$questionnaire_id'";
$result_questions = mysqli_query($conn, $query_questions);

$questions = [];
while ($question = mysqli_fetch_assoc($result_questions)) {
    // Get answers for each question
    $question_id = $question['id'];
    $query_answers = "SELECT jk.*, p.nama as siswa_nama
                    FROM jawaban_kuesioner jk
                    JOIN pengguna p ON jk.siswa_id = p.id
                    WHERE jk.pertanyaan_id = '$question_id'
                    ORDER BY jk.tanggal_jawab DESC";
    $result_answers = mysqli_query($conn, $query_answers);
    
    $answers = [];
    while ($answer = mysqli_fetch_assoc($result_answers)) {
        $answer['tanggal_jawab'] = formatDate($answer['tanggal_jawab']);
        $answers[] = $answer;
    }
    
    $question['answers'] = $answers;
    $questions[] = $question;
}

// Return data
echo json_encode([
    'success' => true,
    'questionnaire' => $questionnaire,
    'questions' => $questions
]);
?> 