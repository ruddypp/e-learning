<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has principal role
checkAccess(['kepsek']);

// Get all classes
$query_classes = "SELECT k.*, 
                (SELECT COUNT(*) FROM pengguna WHERE kelas_id = k.id AND tipe_pengguna = 'siswa') as jumlah_siswa,
                (SELECT AVG(nt.nilai) FROM nilai_tugas nt 
                 JOIN tugas t ON nt.tugas_id = t.id 
                 WHERE t.kelas_id = k.id AND nt.nilai IS NOT NULL) as rata_nilai
                FROM kelas k
                ORDER BY k.tahun_ajaran DESC, k.nama ASC";
$result_classes = mysqli_query($conn, $query_classes);

// Get filter values
$class_filter = isset($_GET['class']) ? sanitizeInput($_GET['class']) : '';
$filter_applied = !empty($class_filter);

// Get class details if filter is applied
$class_name = '';
if ($filter_applied) {
    $query_class_name = "SELECT nama, tahun_ajaran FROM kelas WHERE id = '$class_filter'";
    $result_class_name = mysqli_query($conn, $query_class_name);
    if (mysqli_num_rows($result_class_name) > 0) {
        $class_data = mysqli_fetch_assoc($result_class_name);
        $class_name = $class_data['nama'] . ' (' . $class_data['tahun_ajaran'] . ')';
    }
}

// Get quizzes for the selected class
$quizzes = [];
if ($filter_applied) {
    $query_quizzes = "SELECT t.id, t.judul, t.tanggal_dibuat, m.judul as materi_judul, p.nama as guru_nama,
                     p.id as guru_id,
                     (SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id) as jumlah_pengerjaan,
                     (SELECT COUNT(*) FROM nilai_tugas WHERE tugas_id = t.id AND nilai IS NOT NULL) as jumlah_dinilai,
                     (SELECT AVG(nilai) FROM nilai_tugas WHERE tugas_id = t.id AND nilai IS NOT NULL) as rata_nilai
                     FROM tugas t
                     JOIN materi_coding m ON t.materi_id = m.id
                     JOIN pengguna p ON t.dibuat_oleh = p.id
                     WHERE t.kelas_id = '$class_filter'
                     ORDER BY t.tanggal_dibuat DESC";
    $result_quizzes = mysqli_query($conn, $query_quizzes);
    
    while ($row = mysqli_fetch_assoc($result_quizzes)) {
        $quizzes[$row['id']] = $row;
    }
}

// Get students in the selected class
$students = [];
if ($filter_applied) {
    $query_students = "SELECT id, nama, nisn FROM pengguna 
                      WHERE kelas_id = '$class_filter' AND tipe_pengguna = 'siswa' 
                      ORDER BY nama ASC";
    $result_students = mysqli_query($conn, $query_students);
    
    while ($row = mysqli_fetch_assoc($result_students)) {
        $students[$row['id']] = $row;
    }
}

// Get all grades for the selected class
$grades = [];
$student_averages = [];
$quiz_data = [];
$time_trend_data = [];

if ($filter_applied && !empty($students) && !empty($quizzes)) {
    // Get all grades at once
    $query_grades = "SELECT nt.*, t.judul as quiz_judul, t.tanggal_dibuat
                    FROM nilai_tugas nt
                    JOIN tugas t ON nt.tugas_id = t.id
                    WHERE t.kelas_id = '$class_filter'
                    ORDER BY t.tanggal_dibuat ASC";
    $result_grades = mysqli_query($conn, $query_grades);
    
    // Organize grades by student and quiz
    while ($grade = mysqli_fetch_assoc($result_grades)) {
        $student_id = $grade['siswa_id'];
        $quiz_id = $grade['tugas_id'];
        
        // Skip if student or quiz is not in our filtered lists
        if (!isset($students[$student_id]) || !isset($quizzes[$quiz_id])) {
            continue;
        }
        
        // Store grade
        $grades[$student_id][$quiz_id] = $grade;
        
        // Calculate student averages
        if ($grade['nilai'] !== null) {
            if (!isset($student_averages[$student_id])) {
                $student_averages[$student_id] = ['total' => 0, 'count' => 0];
            }
            $student_averages[$student_id]['total'] += $grade['nilai'];
            $student_averages[$student_id]['count']++;
        }
        
        // Collect quiz data for chart
        if ($grade['nilai'] !== null) {
            if (!isset($quiz_data[$quiz_id])) {
                $quiz_data[$quiz_id] = [
                    'label' => limitText($quizzes[$quiz_id]['judul'], 30),
                    'scores' => [],
                    'average' => 0,
                    'total' => 0,
                    'count' => 0
                ];
            }
            $quiz_data[$quiz_id]['scores'][] = $grade['nilai'];
            $quiz_data[$quiz_id]['total'] += $grade['nilai'];
            $quiz_data[$quiz_id]['count']++;
            
            // Store time trend data
            $date = date('Y-m-d', strtotime($grade['tanggal_dibuat']));
            if (!isset($time_trend_data[$date])) {
                $time_trend_data[$date] = ['count' => 0, 'total' => 0];
            }
            $time_trend_data[$date]['count']++;
            $time_trend_data[$date]['total'] += $grade['nilai'];
        }
    }
    
    // Calculate quiz averages
    foreach ($quiz_data as $quiz_id => &$data) {
        if ($data['count'] > 0) {
            $data['average'] = round($data['total'] / $data['count'], 1);
        } else {
            $data['average'] = 0; // Pastikan kunci 'average' selalu ada
        }
    }
    unset($data); // Hapus referensi terakhir
    
    // Calculate time trend averages
    $time_trend_processed = [];
    ksort($time_trend_data); // Sort by date
    
    foreach ($time_trend_data as $date => $data) {
        $time_trend_processed[] = [
            'date' => $date,
            'average' => round($data['total'] / $data['count'], 1)
        ];
    }

    // Get teacher performance data
    $teacher_performance = [];
    if (!empty($quizzes)) {
        // Group quizzes by teacher
        $teacher_quizzes = [];
        foreach ($quizzes as $quiz) {
            $teacher_id = $quiz['guru_id'];
            if (!isset($teacher_quizzes[$teacher_id])) {
                $teacher_quizzes[$teacher_id] = [
                    'nama' => $quiz['guru_nama'],
                    'quizzes' => [],
                    'total_nilai' => 0,
                    'count_nilai' => 0,
                    'total_pengerjaan' => 0,
                    'total_dinilai' => 0,
                    'avg_response_time' => 0
                ];
            }
            $teacher_quizzes[$teacher_id]['quizzes'][] = $quiz;
            if ($quiz['rata_nilai'] !== null) {
                $teacher_quizzes[$teacher_id]['total_nilai'] += $quiz['rata_nilai'] * $quiz['jumlah_dinilai'];
                $teacher_quizzes[$teacher_id]['count_nilai'] += $quiz['jumlah_dinilai'];
            }
            $teacher_quizzes[$teacher_id]['total_pengerjaan'] += $quiz['jumlah_pengerjaan'];
            $teacher_quizzes[$teacher_id]['total_dinilai'] += $quiz['jumlah_dinilai'];
        }
        
        // Calculate average response time (days between submission and grading)
        $query_response_time = "SELECT p.id as guru_id, p.nama as guru_nama, 
                              AVG(DATEDIFF(nt.tanggal_dinilai, nt.tanggal_pengumpulan)) as avg_days
                              FROM nilai_tugas nt
                              JOIN tugas t ON nt.tugas_id = t.id
                              JOIN pengguna p ON t.dibuat_oleh = p.id
                              WHERE t.kelas_id = '$class_filter' 
                              AND nt.tanggal_dinilai IS NOT NULL
                              AND nt.tanggal_pengumpulan IS NOT NULL
                              GROUP BY p.id";
        $result_response_time = mysqli_query($conn, $query_response_time);
        
        while ($row = mysqli_fetch_assoc($result_response_time)) {
            if (isset($teacher_quizzes[$row['guru_id']])) {
                $teacher_quizzes[$row['guru_id']]['avg_response_time'] = round($row['avg_days'], 1);
            }
        }
        
        // Calculate final stats for each teacher
        foreach ($teacher_quizzes as $teacher_id => $data) {
            $avg_nilai = ($data['count_nilai'] > 0) ? round($data['total_nilai'] / $data['count_nilai'], 1) : 0;
            $grading_rate = ($data['total_pengerjaan'] > 0) ? round(($data['total_dinilai'] / $data['total_pengerjaan']) * 100, 1) : 0;
            
            $teacher_performance[] = [
                'id' => $teacher_id,
                'nama' => $data['nama'],
                'jumlah_quiz' => count($data['quizzes']),
                'avg_nilai' => $avg_nilai,
                'response_time' => $data['avg_response_time'],
                'grading_rate' => $grading_rate
            ];
        }
    }

    // Log activity
    if ($filter_applied) {
        logActivity($_SESSION['user_id'], 'view_grade', "Kepala Sekolah melihat analisis nilai kelas: $class_name", $class_filter);
    }
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Analisis Nilai Kelas</h1>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Class Filter -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Pilih Kelas</h5>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($result_classes) > 0): ?>
                <form method="GET" action="grades.php" class="row g-3">
                    <div class="col-md-6">
                        <select class="form-select" name="class" id="class">
                            <option value="">Pilih Kelas</option>
                            <?php while ($class = mysqli_fetch_assoc($result_classes)): ?>
                                <option value="<?php echo $class['id']; ?>" <?php echo ($class_filter === $class['id']) ? 'selected' : ''; ?>>
                                    <?php echo $class['nama'] . ' (' . $class['tahun_ajaran'] . ') - ' . $class['jumlah_siswa'] . ' siswa'; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i> Terapkan
                        </button>
                    </div>
                    <?php if ($filter_applied): ?>
                        <div class="col-md-2">
                            <a href="grades.php" class="btn btn-outline-secondary w-100">
                                <i class="fas fa-times me-2"></i> Reset
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Belum ada kelas yang terdaftar.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if ($filter_applied): ?>
        <h4 class="mb-4">Analisis Nilai Kelas: <?php echo $class_name; ?></h4>
        
        <?php if (empty($students)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Tidak ada siswa yang terdaftar di kelas ini.
            </div>
        <?php elseif (empty($quizzes)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Belum ada quiz untuk kelas ini.
            </div>
        <?php else: ?>
            <!-- Grade Statistics -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Statistik Nilai Quiz</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="quizChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Tren Nilai Seiring Waktu</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($time_trend_processed)): ?>
                                <canvas id="trendChart" height="250"></canvas>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    Belum ada data nilai yang cukup untuk menampilkan tren.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Teacher Performance Analysis -->
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-chalkboard-teacher me-2"></i> Analisis Efektivitas Pengajaran</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($teacher_performance)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Tidak ada data yang cukup untuk menganalisis efektivitas pengajaran.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Guru</th>
                                        <th>Jumlah Quiz</th>
                                        <th>Rata-rata Nilai</th>
                                        <th>Tingkat Penilaian</th>
                                        <th>Waktu Respons (Hari)</th>
                                        <th>Efektivitas</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($teacher_performance as $teacher): ?>
                                        <tr>
                                            <td><?php echo $teacher['nama']; ?></td>
                                            <td><?php echo $teacher['jumlah_quiz']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo scoreColor($teacher['avg_nilai']); ?>">
                                                    <?php echo $teacher['avg_nilai']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-info" role="progressbar" 
                                                         style="width: <?php echo $teacher['grading_rate']; ?>%;" 
                                                         aria-valuenow="<?php echo $teacher['grading_rate']; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100">
                                                        <?php echo $teacher['grading_rate']; ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php 
                                                $response_color = 'success';
                                                if ($teacher['response_time'] > 7) {
                                                    $response_color = 'danger';
                                                } elseif ($teacher['response_time'] > 3) {
                                                    $response_color = 'warning';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $response_color; ?>">
                                                    <?php echo $teacher['response_time']; ?> hari
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                // Calculate effectiveness score (0-100)
                                                $score = 0;
                                                
                                                // 40% from average grade
                                                $grade_score = ($teacher['avg_nilai'] / 100) * 40;
                                                
                                                // 30% from grading rate
                                                $grading_score = ($teacher['grading_rate'] / 100) * 30;
                                                
                                                // 30% from response time (faster is better)
                                                $time_score = 0;
                                                if ($teacher['response_time'] <= 1) {
                                                    $time_score = 30;
                                                } elseif ($teacher['response_time'] <= 3) {
                                                    $time_score = 20;
                                                } elseif ($teacher['response_time'] <= 7) {
                                                    $time_score = 10;
                                                }
                                                
                                                $score = round($grade_score + $grading_score + $time_score);
                                                $effectiveness = 'Kurang';
                                                $score_color = 'danger';
                                                
                                                if ($score >= 80) {
                                                    $effectiveness = 'Sangat Baik';
                                                    $score_color = 'success';
                                                } elseif ($score >= 60) {
                                                    $effectiveness = 'Baik';
                                                    $score_color = 'primary';
                                                } elseif ($score >= 40) {
                                                    $effectiveness = 'Cukup';
                                                    $score_color = 'warning';
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $score_color; ?>">
                                                    <?php echo $effectiveness; ?> (<?php echo $score; ?>%)
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Tentang Analisis Efektivitas Pengajaran:</strong> 
                            <ul class="mb-0">
                                <li>Rata-rata Nilai: Nilai rata-rata yang diperoleh siswa pada quiz yang dibuat oleh guru.</li>
                                <li>Tingkat Penilaian: Persentase tugas yang telah dinilai oleh guru.</li>
                                <li>Waktu Respons: Rata-rata waktu (dalam hari) yang dibutuhkan guru untuk menilai quiz.</li>
                                <li>Efektivitas: Penilaian keseluruhan berdasarkan nilai, kecepatan menilai, dan tingkat penilaian.</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Distribusi Nilai Rata-rata</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Calculate distribution
                            $avg_distribution = [0, 0, 0, 0, 0]; // <60, 60-69, 70-79, 80-89, 90-100
                            
                            foreach ($student_averages as $student_id => $data) {
                                if ($data['count'] > 0) {
                                    $avg = $data['total'] / $data['count'];
                                    
                                    if ($avg < 60) {
                                        $avg_distribution[0]++;
                                    } elseif ($avg < 70) {
                                        $avg_distribution[1]++;
                                    } elseif ($avg < 80) {
                                        $avg_distribution[2]++;
                                    } elseif ($avg < 90) {
                                        $avg_distribution[3]++;
                                    } else {
                                        $avg_distribution[4]++;
                                    }
                                }
                            }
                            ?>
                            
                            <canvas id="distributionChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Statistik Kelas</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Calculate overall stats
                            $total_score = 0;
                            $total_graded = 0;
                            $highest_avg = 0;
                            $lowest_avg = 100;
                            $highest_student = '';
                            $lowest_student = '';
                            
                            foreach ($student_averages as $student_id => $data) {
                                if ($data['count'] > 0) {
                                    $avg = $data['total'] / $data['count'];
                                    $total_score += $avg;
                                    $total_graded++;
                                    
                                    if ($avg > $highest_avg) {
                                        $highest_avg = $avg;
                                        $highest_student = $students[$student_id]['nama'];
                                    }
                                    
                                    if ($avg < $lowest_avg) {
                                        $lowest_avg = $avg;
                                        $lowest_student = $students[$student_id]['nama'];
                                    }
                                }
                            }
                            
                            $class_avg = $total_graded > 0 ? round($total_score / $total_graded, 1) : 0;
                            ?>
                            
                            <div class="row text-center">
                                <div class="col-md-4 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body py-3">
                                            <h2 class="mb-0"><?php echo $class_avg; ?></h2>
                                            <p class="mb-0">Rata-rata Kelas</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body py-3">
                                            <h2 class="mb-0"><?php echo count($quizzes); ?></h2>
                                            <p class="mb-0">Jumlah Quiz</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="card bg-light">
                                        <div class="card-body py-3">
                                            <h2 class="mb-0"><?php echo count($students); ?></h2>
                                            <p class="mb-0">Jumlah Siswa</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <div class="mb-2">
                                    <strong>Nilai Tertinggi:</strong> 
                                    <span class="badge bg-success p-2"><?php echo round($highest_avg, 1); ?></span>
                                    <span class="ms-2"><?php echo $highest_student; ?></span>
                                </div>
                                <div>
                                    <strong>Nilai Terendah:</strong> 
                                    <span class="badge bg-danger p-2"><?php echo round($lowest_avg, 1); ?></span>
                                    <span class="ms-2"><?php echo $lowest_student; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Grade Table -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Tabel Nilai Siswa</h5>
                    <a href="classes.php?id=<?php echo $class_filter; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-info-circle me-1"></i> Detail Kelas
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="align-middle">No</th>
                                    <th rowspan="2" class="align-middle">Nama Siswa</th>
                                    <th rowspan="2" class="align-middle">NISN</th>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <th class="text-center"><?php echo limitText($quiz['judul'], 20); ?></th>
                                    <?php endforeach; ?>
                                    <th rowspan="2" class="align-middle text-center">Rata-rata</th>
                                </tr>
                                <tr>
                                    <?php foreach ($quizzes as $quiz): ?>
                                        <th class="text-center small"><?php echo formatDate($quiz['tanggal_dibuat']); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; ?>
                                <?php foreach ($students as $student_id => $student): ?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td><?php echo $student['nama']; ?></td>
                                        <td><?php echo $student['nisn']; ?></td>
                                        
                                        <?php foreach ($quizzes as $quiz_id => $quiz): ?>
                                            <td class="text-center">
                                                <?php if (isset($grades[$student_id][$quiz_id])): ?>
                                                    <?php if ($grades[$student_id][$quiz_id]['nilai'] !== null): ?>
                                                        <span class="badge bg-<?php echo scoreColor($grades[$student_id][$quiz_id]['nilai']); ?>">
                                                            <?php echo $grades[$student_id][$quiz_id]['nilai']; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">BN</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark">-</span>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                        
                                        <td class="text-center">
                                            <?php if (isset($student_averages[$student_id]) && $student_averages[$student_id]['count'] > 0): ?>
                                                <?php $avg = round($student_averages[$student_id]['total'] / $student_averages[$student_id]['count'], 1); ?>
                                                <span class="badge bg-<?php echo scoreColor($avg); ?> p-2">
                                                    <?php echo $avg; ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="table-primary">
                                    <th colspan="3" class="text-end">Rata-rata Quiz:</th>
                                    <?php foreach ($quizzes as $quiz_id => $quiz): ?>
                                        <th class="text-center">
                                            <?php if (isset($quiz_data[$quiz_id]) && $quiz_data[$quiz_id]['count'] > 0): ?>
                                                <?php echo isset($quiz_data[$quiz_id]['average']) ? $quiz_data[$quiz_id]['average'] : '-'; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-center"><?php echo $class_avg; ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Quiz Details -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Detail Quiz</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($quizzes as $quiz): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><?php echo $quiz['judul']; ?></h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">
                                            <small>
                                                <i class="fas fa-calendar me-1"></i> <?php echo formatDate($quiz['tanggal_dibuat']); ?><br>
                                                <i class="fas fa-book me-1"></i> Materi: <?php echo $quiz['materi_judul']; ?><br>
                                                <i class="fas fa-user me-1"></i> Guru: <?php echo $quiz['guru_nama']; ?>
                                            </small>
                                        </p>
                                        
                                        <div class="row text-center mb-3">
                                            <div class="col-4">
                                                <h6 class="mb-0"><?php echo $quiz['jumlah_pengerjaan']; ?></h6>
                                                <small class="text-muted">Mengerjakan</small>
                                            </div>
                                            <div class="col-4">
                                                <h6 class="mb-0"><?php echo $quiz['jumlah_dinilai']; ?></h6>
                                                <small class="text-muted">Dinilai</small>
                                            </div>
                                            <div class="col-4">
                                                <h6 class="mb-0"><?php echo $quiz['rata_nilai'] ? round($quiz['rata_nilai'], 1) : '-'; ?></h6>
                                                <small class="text-muted">Rata-rata</small>
                                            </div>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <a href="../guru/quiz_detail.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-chart-bar me-1"></i> Lihat Detail
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Silakan pilih kelas untuk melihat analisis nilai siswa.
        </div>
    <?php endif; ?>
</div>

<?php if ($filter_applied && !empty($students) && !empty($quizzes)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Quiz Chart
    var ctxQuiz = document.getElementById('quizChart').getContext('2d');
    var quizChart = new Chart(ctxQuiz, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($quiz_data, 'label')); ?>,
            datasets: [{
                label: 'Rata-rata Nilai',
                data: <?php 
                    $averages = [];
                    foreach ($quiz_data as $data) {
                        $averages[] = isset($data['average']) ? $data['average'] : 0;
                    }
                    echo json_encode($averages);
                ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    
    // Distribution Chart
    var ctxDistribution = document.getElementById('distributionChart').getContext('2d');
    var distributionChart = new Chart(ctxDistribution, {
        type: 'pie',
        data: {
            labels: ['<60', '60-69', '70-79', '80-89', '90-100'],
            datasets: [{
                data: <?php echo json_encode($avg_distribution); ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(255, 159, 64, 0.6)',
                    'rgba(255, 205, 86, 0.6)',
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(54, 162, 235, 0.6)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(255, 205, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'right'
                }
            }
        }
    });
    
    <?php if (!empty($time_trend_processed)): ?>
    // Trend Chart
    var ctxTrend = document.getElementById('trendChart').getContext('2d');
    var trendChart = new Chart(ctxTrend, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_column($time_trend_processed, 'date')); ?>,
            datasets: [{
                label: 'Rata-rata Nilai',
                data: <?php
                    $averages = [];
                    foreach ($time_trend_processed as $data) {
                        $averages[] = isset($data['average']) ? $data['average'] : 0;
                    }
                    echo json_encode($averages);
                ?>,
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 2,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php
// Helper function to determine score color
function scoreColor($score) {
    if ($score >= 90) {
        return 'success';
    } elseif ($score >= 80) {
        return 'info';
    } elseif ($score >= 70) {
        return 'primary';
    } elseif ($score >= 60) {
        return 'warning';
    } else {
        return 'danger';
    }
}

// Include footer
include_once '../../includes/footer.php';
?> 