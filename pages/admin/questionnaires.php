<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has admin role
checkAccess(['admin']);

// Get all questionnaires with class and creator info
$query = "SELECT q.*, k.nama AS kelas_nama, p.nama AS dibuat_oleh_nama,
         (SELECT COUNT(*) FROM pertanyaan_kuesioner WHERE kuesioner_id = q.id) AS jumlah_pertanyaan,
         (SELECT COUNT(DISTINCT siswa_id) FROM jawaban_kuesioner jk 
          JOIN pertanyaan_kuesioner pk ON jk.pertanyaan_id = pk.id 
          WHERE pk.kuesioner_id = q.id) AS jumlah_responden,
         (SELECT COUNT(*) FROM pengguna WHERE kelas_id = q.kelas_id AND tipe_pengguna = 'siswa') AS jumlah_siswa
         FROM kuesioner q
         JOIN kelas k ON q.kelas_id = k.id
         JOIN pengguna p ON q.dibuat_oleh = p.id
         ORDER BY q.tanggal_dibuat DESC";
$result = mysqli_query($conn, $query);

// Get all classes for filter
$classes_query = "SELECT id, nama, tahun_ajaran FROM kelas ORDER BY tahun_ajaran DESC, nama ASC";
$classes_result = mysqli_query($conn, $classes_query);
$classes = [];
while ($row = mysqli_fetch_assoc($classes_result)) {
    $classes[] = $row;
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Manajemen Kuesioner</h1>
        <div>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i> Kembali ke Dashboard
            </a>
        </div>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-primary h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-wrapper bg-primary text-white rounded-circle p-3 me-3">
                        <i class="fas fa-clipboard-list fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="card-title text-muted mb-0">Total Kuesioner</h6>
                        <h2 class="mt-2 mb-0"><?php echo mysqli_num_rows($result); ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card border-success h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-wrapper bg-success text-white rounded-circle p-3 me-3">
                        <i class="fas fa-school fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="card-title text-muted mb-0">Kelas dengan Kuesioner</h6>
                        <?php
                        $classes_with_questionnaires_query = "SELECT COUNT(DISTINCT kelas_id) AS count FROM kuesioner";
                        $classes_count_result = mysqli_query($conn, $classes_with_questionnaires_query);
                        $classes_with_questionnaires = mysqli_fetch_assoc($classes_count_result)['count'];
                        ?>
                        <h2 class="mt-2 mb-0"><?php echo $classes_with_questionnaires; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card border-info h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-wrapper bg-info text-white rounded-circle p-3 me-3">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <div>
                        <h6 class="card-title text-muted mb-0">Total Responden</h6>
                        <?php
                        $respondents_query = "SELECT COUNT(DISTINCT siswa_id) AS count FROM jawaban_kuesioner";
                        $respondents_result = mysqli_query($conn, $respondents_query);
                        $total_respondents = mysqli_fetch_assoc($respondents_result)['count'];
                        ?>
                        <h2 class="mt-2 mb-0"><?php echo $total_respondents; ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter by class -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="kelas_id" class="form-label">Filter berdasarkan Kelas</label>
                    <select class="form-select" id="kelas_id" name="kelas_id" onchange="this.form.submit()">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo (isset($_GET['kelas_id']) && $_GET['kelas_id'] == $class['id']) ? 'selected' : ''; ?>>
                                <?php echo $class['nama'] . ' (' . $class['tahun_ajaran'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <?php if (isset($_GET['kelas_id']) && !empty($_GET['kelas_id'])): ?>
                        <a href="questionnaires.php" class="btn btn-secondary">
                            <i class="fas fa-times me-2"></i> Reset Filter
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Questionnaires Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Daftar Kuesioner</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Judul</th>
                            <th>Kelas</th>
                            <th>Dibuat Oleh</th>
                            <th>Tanggal Dibuat</th>
                            <th>Pertanyaan</th>
                            <th>Responden</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Apply filter if set
                        if (isset($_GET['kelas_id']) && !empty($_GET['kelas_id'])) {
                            $kelas_id = sanitizeInput($_GET['kelas_id']);
                            mysqli_data_seek($result, 0);
                            $filtered_result = [];
                            while ($row = mysqli_fetch_assoc($result)) {
                                if ($row['kelas_id'] == $kelas_id) {
                                    $filtered_result[] = $row;
                                }
                            }
                        } else {
                            mysqli_data_seek($result, 0);
                            $filtered_result = [];
                            while ($row = mysqli_fetch_assoc($result)) {
                                $filtered_result[] = $row;
                            }
                        }
                        
                        if (count($filtered_result) > 0):
                            foreach ($filtered_result as $questionnaire):
                        ?>
                            <tr>
                                <td><?php echo $questionnaire['judul']; ?></td>
                                <td><?php echo $questionnaire['kelas_nama']; ?></td>
                                <td><?php echo $questionnaire['dibuat_oleh_nama']; ?></td>
                                <td><?php echo formatDate($questionnaire['tanggal_dibuat']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $questionnaire['jumlah_pertanyaan']; ?> pertanyaan</span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                            <?php 
                                            $percentage = $questionnaire['jumlah_siswa'] > 0 
                                                ? round(($questionnaire['jumlah_responden'] / $questionnaire['jumlah_siswa']) * 100) 
                                                : 0;
                                            $bg_class = $percentage < 30 ? 'bg-danger' : ($percentage < 70 ? 'bg-warning' : 'bg-success');
                                            ?>
                                            <div class="progress-bar <?php echo $bg_class; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%;" 
                                                 aria-valuenow="<?php echo $percentage; ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100"></div>
                                        </div>
                                        <span><?php echo $questionnaire['jumlah_responden']; ?>/<?php echo $questionnaire['jumlah_siswa']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <a href="view_questionnaire.php?id=<?php echo $questionnaire['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php 
                            endforeach; 
                        else: 
                        ?>
                            <tr>
                                <td colspan="7" class="text-center py-3">Tidak ada data kuesioner yang ditemukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.icon-wrapper {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>

<?php include_once '../../includes/footer.php'; ?> 