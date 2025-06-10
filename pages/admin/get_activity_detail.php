<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Helper function to format activity type
function formatActivityType($type) {
    $types = [
        'login' => 'Login',
        'logout' => 'Logout',
        'view_materi' => 'Lihat Materi',
        'submit_tugas' => 'Submit Tugas',
        'nilai_tugas' => 'Nilai Tugas',
        'view_grade' => 'Lihat Nilai',
        'nilai_kelas' => 'Lihat Nilai Kelas',
        'tambah_materi' => 'Tambah Materi',
        'edit_materi' => 'Edit Materi',
        'hapus_materi' => 'Hapus Materi',
        'verifikasi' => 'Verifikasi',
        'backup' => 'Backup',
        'restore' => 'Restore'
    ];
    
    return isset($types[$type]) ? $types[$type] : ucfirst(str_replace('_', ' ', $type));
}

// Get parameters
$activity_id = isset($_GET['id']) ? sanitizeInput($_GET['id']) : '';

if (empty($activity_id)) {
    echo '<div class="alert alert-danger">Parameter yang diperlukan tidak lengkap.</div>';
    exit;
}

// Get activity details
$query_activity = "SELECT la.*, p.nama, p.tipe_pengguna, la.tipe_aktivitas, la.referensi_id
                  FROM laporan_aktivitas la
                  JOIN pengguna p ON la.pengguna_id = p.id
                  WHERE la.id = '$activity_id'";
$result_activity = mysqli_query($conn, $query_activity);

if (!$result_activity || mysqli_num_rows($result_activity) == 0) {
    echo '<div class="alert alert-danger">Aktivitas tidak ditemukan.</div>';
    exit;
}

$activity = mysqli_fetch_assoc($result_activity);
$activity_type = $activity['tipe_aktivitas'];
$reference_id = $activity['referensi_id'];

// Get user info
$user_id = $activity['pengguna_id'];
$user_query = "SELECT * FROM pengguna WHERE id = '$user_id'";
$user_result = mysqli_query($conn, $user_query);
$user = mysqli_fetch_assoc($user_result);

// Get activity details based on type
switch ($activity_type) {
    case 'view_grade':
        // Get grade details - perbaikan query untuk bisa mendapatkan data lebih baik
        $query = "SELECT nt.*, t.judul as tugas_judul, s.nama as siswa_nama, g.nama as guru_nama, k.nama as kelas_nama
                 FROM nilai_tugas nt
                 LEFT JOIN tugas t ON nt.tugas_id = t.id
                 LEFT JOIN pengguna s ON nt.siswa_id = s.id
                 LEFT JOIN pengguna g ON nt.dinilai_oleh = g.id
                 LEFT JOIN kelas k ON t.kelas_id = k.id
                 WHERE nt.id = '$reference_id'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $grade = mysqli_fetch_assoc($result);
            ?>
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Detail Nilai</h5>
                </div>
                <div class="card-body">
                    <h5><?php echo $grade['tugas_judul']; ?></h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Siswa:</strong> <?php echo $grade['siswa_nama']; ?></p>
                            <p><strong>Kelas:</strong> <?php echo $grade['kelas_nama']; ?></p>
                            <p><strong>Tanggal Pengumpulan:</strong> <?php echo formatDate($grade['tanggal_pengumpulan']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Nilai:</strong> 
                                <?php 
                                if ($grade['nilai'] !== null) {
                                    echo '<span class="badge bg-success">' . $grade['nilai'] . '</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">Belum Dinilai</span>';
                                }
                                ?>
                            </p>
                            <p><strong>Dinilai Oleh:</strong> 
                                <?php echo $grade['guru_nama'] ?? '<span class="text-muted">Belum dinilai</span>'; ?>
                            </p>
                            <p><strong>Tanggal Penilaian:</strong> 
                                <?php echo $grade['tanggal_dinilai'] ? formatDate($grade['tanggal_dinilai']) : '<span class="text-muted">-</span>'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($grade['feedback']): ?>
                        <div class="mb-3">
                            <h6>Feedback:</h6>
                            <div class="p-3 bg-light rounded">
                                <?php echo $grade['feedback']; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        } else {
            // Fallback jika tidak menemukan detail nilai langsung
            // Coba cari detail lain dari tabel tugas
            $tugas_query = "SELECT t.*, p.nama as guru_nama, k.nama as kelas_nama
                           FROM tugas t
                           LEFT JOIN pengguna p ON t.dibuat_oleh = p.id
                           LEFT JOIN kelas k ON t.kelas_id = k.id
                           WHERE t.id = '$reference_id'";
            $tugas_result = mysqli_query($conn, $tugas_query);
            
            if ($tugas_result && mysqli_num_rows($tugas_result) > 0) {
                $tugas = mysqli_fetch_assoc($tugas_result);
                ?>
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Detail Tugas/Quiz yang Dilihat</h5>
                    </div>
                    <div class="card-body">
                        <h5><?php echo $tugas['judul'] ?? 'Tugas Tanpa Judul'; ?></h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Pembuat:</strong> <?php echo $tugas['guru_nama'] ?? 'Tidak diketahui'; ?></p>
                                <p><strong>Kelas:</strong> <?php echo $tugas['kelas_nama'] ?? 'Tidak diketahui'; ?></p>
                                <p><strong>Jenis:</strong> <?php echo isset($tugas['jenis']) ? ucfirst($tugas['jenis']) : 'Tidak diketahui'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Tanggal Dibuat:</strong> <?php echo isset($tugas['tanggal_dibuat']) ? formatDate($tugas['tanggal_dibuat']) : 'Tidak diketahui'; ?></p>
                                <p><strong>Tenggat Waktu:</strong> <?php echo isset($tugas['tenggat_waktu']) ? formatDate($tugas['tenggat_waktu']) : 'Tidak diketahui'; ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Deskripsi:</h6>
                            <div class="p-3 bg-light rounded">
                                <?php echo isset($tugas['deskripsi']) ? $tugas['deskripsi'] : 'Tidak ada deskripsi'; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            } else {
                ?>
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Detail Aktivitas Melihat Nilai</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Pengguna:</strong> <?php echo $activity['nama']; ?></p>
                                <p><strong>Tipe Pengguna:</strong> <?php echo ucfirst($activity['tipe_pengguna']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Waktu:</strong> <?php echo formatDate($activity['waktu'], true); ?></p>
                                <p><strong>ID Referensi:</strong> <?php echo $reference_id; ?></p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h6>Deskripsi Aktivitas:</h6>
                            <div class="p-3 bg-light rounded">
                                <p><?php echo $activity['deskripsi']; ?></p>
                                <p class="text-muted">Catatan: Detail nilai tidak ditemukan di database, kemungkinan data telah dihapus.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        break;

    case 'nilai_kelas':
        // Menampilkan informasi tentang aktivitas melihat nilai kelas
        ?>
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Detail Aktivitas Melihat Nilai Kelas</h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Pengguna:</strong> <?php echo $activity['nama']; ?></p>
                        <p><strong>Tipe Pengguna:</strong> <?php echo ucfirst($activity['tipe_pengguna']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Waktu:</strong> <?php echo formatDate($activity['waktu'], true); ?></p>
                    </div>
                </div>
                <div class="mb-3">
                    <h6>Deskripsi Aktivitas:</h6>
                    <div class="p-3 bg-light rounded">
                        <?php echo $activity['deskripsi']; ?>
                    </div>
                </div>
                
                <?php
                // Mencoba mendapatkan informasi kelas jika tersedia
                if ($reference_id) {
                    $class_query = "SELECT * FROM kelas WHERE id = '$reference_id'";
                    $class_result = mysqli_query($conn, $class_query);
                    if ($class_result && mysqli_num_rows($class_result) > 0) {
                        $class = mysqli_fetch_assoc($class_result);
                        ?>
                        <div class="mb-3">
                            <h6>Informasi Kelas:</h6>
                            <p><strong>Nama Kelas:</strong> <?php echo $class['nama']; ?></p>
                            <p><strong>Tahun Ajaran:</strong> <?php echo $class['tahun_ajaran']; ?></p>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
        <?php
        break;
        
    case 'view_materi':
        // Get material details
        $query = "SELECT m.*, p.nama as pembuat, k.nama as kelas_nama
                 FROM materi_coding m
                 JOIN pengguna p ON m.dibuat_oleh = p.id
                 JOIN kelas k ON m.kelas_id = k.id
                 WHERE m.id = '$reference_id'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $material = mysqli_fetch_assoc($result);
            ?>
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Detail Materi</h5>
                </div>
                <div class="card-body">
                    <h5><?php echo $material['judul']; ?></h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Tingkat:</strong> <?php echo $material['tingkat']; ?></p>
                            <p><strong>Kelas:</strong> <?php echo $material['kelas_nama']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Dibuat Oleh:</strong> <?php echo $material['pembuat']; ?></p>
                            <p><strong>Tanggal Dibuat:</strong> <?php echo formatDate($material['tanggal_dibuat']); ?></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6>Deskripsi:</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo $material['deskripsi']; ?>
                        </div>
                    </div>
                    <a href="../siswa/material_detail.php?id=<?php echo $material['id']; ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-eye me-2"></i> Lihat Materi
                    </a>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-warning">Materi tidak ditemukan.</div>';
        }
        break;
        
    case 'submit_tugas':
        // Get assignment submission details - perbaikan query untuk mendapatkan data lebih baik
        $query = "SELECT nt.*, t.judul as tugas_judul, s.nama as siswa_nama, g.nama as guru_nama, k.nama as kelas_nama
                 FROM nilai_tugas nt
                 LEFT JOIN tugas t ON nt.tugas_id = t.id
                 LEFT JOIN pengguna s ON nt.siswa_id = s.id
                 LEFT JOIN pengguna g ON nt.dinilai_oleh = g.id
                 LEFT JOIN kelas k ON t.kelas_id = k.id
                 WHERE nt.id = '$reference_id'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $submission = mysqli_fetch_assoc($result);
            ?>
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">Detail Pengumpulan Tugas</h5>
                </div>
                <div class="card-body">
                    <h5><?php echo $submission['tugas_judul']; ?></h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Siswa:</strong> <?php echo $submission['siswa_nama']; ?></p>
                            <p><strong>Kelas:</strong> <?php echo $submission['kelas_nama']; ?></p>
                            <p><strong>Tanggal Pengumpulan:</strong> <?php echo formatDate($submission['tanggal_pengumpulan']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Nilai:</strong> 
                                <?php 
                                if ($submission['nilai'] !== null) {
                                    echo '<span class="badge bg-success">' . $submission['nilai'] . '</span>';
                                } else {
                                    echo '<span class="badge bg-secondary">Belum Dinilai</span>';
                                }
                                ?>
                            </p>
                            <p><strong>Dinilai Oleh:</strong> 
                                <?php echo $submission['guru_nama'] ?? '<span class="text-muted">Belum dinilai</span>'; ?>
                            </p>
                            <p><strong>Tanggal Penilaian:</strong> 
                                <?php echo $submission['tanggal_dinilai'] ? formatDate($submission['tanggal_dinilai']) : '<span class="text-muted">-</span>'; ?>
                            </p>
                        </div>
                    </div>
                    
                    <?php if ($submission['feedback']): ?>
                        <div class="mb-3">
                            <h6>Feedback:</h6>
                            <div class="p-3 bg-light rounded">
                                <?php echo $submission['feedback']; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($submission['file_jawaban']): ?>
                        <div class="mb-3">
                            <h6>File Jawaban:</h6>
                            <a href="../../uploads/tugas/<?php echo $submission['file_jawaban']; ?>" class="btn btn-sm btn-info" target="_blank">
                                <i class="fas fa-download me-2"></i> Download File Jawaban
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        } else {
            // Fallback jika tidak menemukan detail pengumpulan langsung
            // Coba cari detail tugas yang dikumpulkan
            $tugas_query = "SELECT t.*, p.nama as guru_nama, k.nama as kelas_nama
                           FROM tugas t
                           LEFT JOIN pengguna p ON t.dibuat_oleh = p.id
                           LEFT JOIN kelas k ON t.kelas_id = k.id
                           WHERE t.id = '$reference_id'";
            $tugas_result = mysqli_query($conn, $tugas_query);
            
            if ($tugas_result && mysqli_num_rows($tugas_result) > 0) {
                $tugas = mysqli_fetch_assoc($tugas_result);
                ?>
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Detail Tugas yang Dikumpulkan</h5>
                    </div>
                    <div class="card-body">
                        <h5><?php echo $tugas['judul'] ?? 'Tugas Tanpa Judul'; ?></h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Pembuat:</strong> <?php echo $tugas['guru_nama'] ?? 'Tidak diketahui'; ?></p>
                                <p><strong>Kelas:</strong> <?php echo $tugas['kelas_nama'] ?? 'Tidak diketahui'; ?></p>
                                <p><strong>Jenis:</strong> <?php echo isset($tugas['jenis']) ? ucfirst($tugas['jenis']) : 'Tidak diketahui'; ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Tanggal Dibuat:</strong> <?php echo isset($tugas['tanggal_dibuat']) ? formatDate($tugas['tanggal_dibuat']) : 'Tidak diketahui'; ?></p>
                                <p><strong>Tenggat Waktu:</strong> <?php echo isset($tugas['tenggat_waktu']) ? formatDate($tugas['tenggat_waktu']) : 'Tidak diketahui'; ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6>Deskripsi Tugas:</h6>
                            <div class="p-3 bg-light rounded">
                                <?php echo isset($tugas['deskripsi']) ? $tugas['deskripsi'] : 'Tidak ada deskripsi'; ?>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i> Detail pengumpulan tidak ditemukan. Kemungkinan data pengumpulan telah dihapus, tetapi informasi tugas masih tersedia.
                        </div>
                    </div>
                </div>
                <?php
            } else {
                ?>
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">Detail Aktivitas Pengumpulan Tugas</h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <p><strong>Pengguna:</strong> <?php echo $activity['nama']; ?></p>
                                <p><strong>Tipe Pengguna:</strong> <?php echo ucfirst($activity['tipe_pengguna']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Waktu:</strong> <?php echo formatDate($activity['waktu'], true); ?></p>
                                <p><strong>ID Referensi:</strong> <?php echo $reference_id; ?></p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <h6>Deskripsi Aktivitas:</h6>
                            <div class="p-3 bg-light rounded">
                                <p><?php echo $activity['deskripsi']; ?></p>
                                <p class="text-muted">Catatan: Detail pengumpulan tugas tidak ditemukan di database, kemungkinan data telah dihapus.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        break;
        
    case 'tambah_materi':
    case 'edit_materi':
    case 'hapus_materi':
        // Get material details
        $query = "SELECT m.*, p.nama as pembuat, k.nama as kelas_nama
                 FROM materi_coding m
                 JOIN pengguna p ON m.dibuat_oleh = p.id
                 JOIN kelas k ON m.kelas_id = k.id
                 WHERE m.id = '$reference_id'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $material = mysqli_fetch_assoc($result);
            ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Detail Materi</h5>
                </div>
                <div class="card-body">
                    <h5><?php echo $material['judul']; ?></h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Tingkat:</strong> <?php echo $material['tingkat']; ?></p>
                            <p><strong>Kelas:</strong> <?php echo $material['kelas_nama']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Dibuat Oleh:</strong> <?php echo $material['pembuat']; ?></p>
                            <p><strong>Tanggal Dibuat:</strong> <?php echo formatDate($material['tanggal_dibuat']); ?></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6>Deskripsi:</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo $material['deskripsi']; ?>
                        </div>
                    </div>
                    <a href="../siswa/material_detail.php?id=<?php echo $material['id']; ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-eye me-2"></i> Lihat Materi
                    </a>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-warning">Materi tidak ditemukan.</div>';
        }
        break;
        
    case 'login':
    case 'logout':
        // Get user login details
        $query = "SELECT p.*, ls.id as log_id, ls.waktu, ls.ip_address, ls.user_agent
                 FROM log_sistem ls
                 JOIN pengguna p ON ls.pengguna_id = p.id
                 WHERE ls.id = '$reference_id'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $login = mysqli_fetch_assoc($result);
            ?>
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Detail <?php echo ucfirst($activity_type); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Pengguna:</strong> <?php echo $login['nama']; ?></p>
                            <p><strong>Tipe Pengguna:</strong> <?php echo ucfirst($login['tipe_pengguna']); ?></p>
                            <p><strong>Email:</strong> <?php echo $login['email']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Waktu:</strong> <?php echo formatDate($login['waktu'], true); ?></p>
                            <p><strong>IP Address:</strong> <?php echo $login['ip_address']; ?></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6>User Agent:</h6>
                        <div class="p-3 bg-light rounded">
                            <code><?php echo $login['user_agent']; ?></code>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-warning">Detail login tidak ditemukan.</div>';
        }
        break;
        
    case 'verifikasi':
        // Try to find user verification
        $query = "SELECT p.*, ls.id as log_id, ls.waktu, ls.detail, ls.aksi
                 FROM log_sistem ls
                 JOIN pengguna p ON ls.pengguna_id = p.id
                 WHERE ls.id = '$reference_id'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $verification = mysqli_fetch_assoc($result);
            ?>
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Detail Verifikasi</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Admin:</strong> <?php echo $verification['nama']; ?></p>
                            <p><strong>Aksi:</strong> <?php echo $verification['aksi']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Waktu:</strong> <?php echo formatDate($verification['waktu'], true); ?></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6>Detail:</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo $verification['detail']; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-warning">Detail verifikasi tidak ditemukan.</div>';
        }
        break;
        
    case 'backup':
    case 'restore':
        // Get backup details
        $query = "SELECT ls.*, p.nama
                 FROM log_sistem ls
                 JOIN pengguna p ON ls.pengguna_id = p.id
                 WHERE ls.id = '$reference_id'";
        $result = mysqli_query($conn, $query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $backup = mysqli_fetch_assoc($result);
            ?>
            <div class="card">
                <div class="card-header bg-light text-dark">
                    <h5 class="mb-0">Detail <?php echo ucfirst($activity_type); ?></h5>
                </div>
                <div class="card-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <p><strong>Admin:</strong> <?php echo $backup['nama']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Waktu:</strong> <?php echo formatDate($backup['waktu'], true); ?></p>
                            <p><strong>IP Address:</strong> <?php echo $backup['ip_address']; ?></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6>Detail:</h6>
                        <div class="p-3 bg-light rounded">
                            <?php echo $backup['detail']; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-warning">Detail backup tidak ditemukan.</div>';
        }
        break;
        
    default:
        // Generic handler for any unrecognized activity type
        ?>
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Detail Aktivitas: <?php echo formatActivityType($activity_type); ?></h5>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Pengguna:</strong> <?php echo $activity['nama']; ?></p>
                        <p><strong>Tipe Pengguna:</strong> <?php echo ucfirst($activity['tipe_pengguna']); ?></p>
                        <p><strong>ID Referensi:</strong> <?php echo $reference_id ?: 'Tidak ada'; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Waktu:</strong> <?php echo formatDate($activity['waktu'], true); ?></p>
                        <p><strong>Tipe Aktivitas:</strong> <?php echo formatActivityType($activity_type); ?></p>
                    </div>
                </div>
                <div class="mb-3">
                    <h6>Deskripsi Aktivitas:</h6>
                    <div class="p-3 bg-light rounded">
                        <?php echo $activity['deskripsi']; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
        break;
}
?> 