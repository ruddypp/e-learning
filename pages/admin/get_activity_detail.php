<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Get parameters
$activity_id = isset($_GET['id']) ? sanitizeInput($_GET['id']) : '';
$reference_id = isset($_GET['ref']) ? sanitizeInput($_GET['ref']) : '';
$activity_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';

if (empty($activity_id) || empty($reference_id) || empty($activity_type)) {
    echo '<div class="alert alert-danger">Parameter yang diperlukan tidak lengkap.</div>';
    exit;
}

// Get activity details based on type
switch ($activity_type) {
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
    case 'nilai_tugas':
        // Get assignment submission details
        $query = "SELECT nt.*, t.judul as tugas_judul, s.nama as siswa_nama, g.nama as guru_nama, k.nama as kelas_nama
                 FROM nilai_tugas nt
                 JOIN tugas t ON nt.tugas_id = t.id
                 JOIN pengguna s ON nt.siswa_id = s.id
                 LEFT JOIN pengguna g ON nt.dinilai_oleh = g.id
                 JOIN kelas k ON t.kelas_id = k.id
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
                    
                    <a href="../guru/nilai_detail.php?id=<?php echo $submission['id']; ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-eye me-2"></i> Lihat Detail Penilaian
                    </a>
                </div>
            </div>
            <?php
        } else {
            echo '<div class="alert alert-warning">Pengumpulan tugas tidak ditemukan.</div>';
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
        echo '<div class="alert alert-warning">Tipe aktivitas tidak dikenali.</div>';
        break;
}
?> 