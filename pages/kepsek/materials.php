<?php
// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Check if user is logged in and has principal role
checkAccess(['kepsek']);

// Handle filter
$class_filter = isset($_GET['class']) ? sanitizeInput($_GET['class']) : '';
$level_filter = isset($_GET['level']) ? sanitizeInput($_GET['level']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query
$query_materials = "SELECT m.*, k.nama as kelas_nama, p.nama as guru_nama,
                  (SELECT COUNT(*) FROM tugas WHERE materi_id = m.id) as jumlah_quiz
                  FROM materi_coding m 
                  JOIN kelas k ON m.kelas_id = k.id 
                  JOIN pengguna p ON m.dibuat_oleh = p.id 
                  WHERE 1=1 ";

// Apply filters
if (!empty($class_filter)) {
    $query_materials .= "AND m.kelas_id = '$class_filter' ";
}

if (!empty($level_filter)) {
    $query_materials .= "AND m.tingkat = '$level_filter' ";
}

if (!empty($search)) {
    $query_materials .= "AND (m.judul LIKE '%$search%' OR m.deskripsi LIKE '%$search%') ";
}

// Add order
$query_materials .= "ORDER BY m.tanggal_dibuat DESC";

// Execute query
$result_materials = mysqli_query($conn, $query_materials);

// Get classes for filter
$query_classes = "SELECT id, nama, tahun_ajaran FROM kelas ORDER BY nama ASC";
$result_classes = mysqli_query($conn, $query_classes);
$classes = [];

while ($row = mysqli_fetch_assoc($result_classes)) {
    $classes[] = $row;
}

// Get unique levels for filter
$query_levels = "SELECT DISTINCT tingkat FROM materi_coding ORDER BY tingkat";
$result_levels = mysqli_query($conn, $query_levels);
$levels = [];

while ($row = mysqli_fetch_assoc($result_levels)) {
    $levels[] = $row['tingkat'];
}

// Include header
include_once '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Materi Pembelajaran</h1>
    </div>
    
    <?php displayFlashMessage(); ?>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Filter Materi</h5>
        </div>
        <div class="card-body">
            <form method="GET" action="materials.php" class="row g-3">
                <div class="col-md-4">
                    <label for="class" class="form-label">Kelas</label>
                    <select class="form-select" id="class" name="class">
                        <option value="">Semua Kelas</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo $class['id']; ?>" <?php echo ($class_filter === $class['id']) ? 'selected' : ''; ?>>
                                <?php echo $class['nama'] . ' (' . $class['tahun_ajaran'] . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="level" class="form-label">Tingkat Kesulitan</label>
                    <select class="form-select" id="level" name="level">
                        <option value="">Semua Tingkat</option>
                        <?php foreach ($levels as $level): ?>
                            <option value="<?php echo $level; ?>" <?php echo ($level_filter === $level) ? 'selected' : ''; ?>>
                                <?php echo $level; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Cari</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Judul atau deskripsi" value="<?php echo $search; ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Materials Stats -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="p-3 rounded-circle bg-primary bg-gradient text-white d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                                <i class="fas fa-book-open fa-2x"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase mb-1 small">Total Materi</h6>
                            <h2 class="fw-bold mb-0"><?php echo mysqli_num_rows($result_materials); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="p-3 rounded-circle bg-success bg-gradient text-white d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                                <i class="fas fa-school fa-2x"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase mb-1 small">Total Kelas</h6>
                            <h2 class="fw-bold mb-0"><?php echo count($classes); ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        // Get total teachers who create materials
        $query_teachers = "SELECT COUNT(DISTINCT dibuat_oleh) as total FROM materi_coding";
        $result_teachers_count = mysqli_query($conn, $query_teachers);
        $teachers_count = mysqli_fetch_assoc($result_teachers_count)['total'];
        ?>
        
        <div class="col-md-4 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="p-3 rounded-circle bg-info bg-gradient text-white d-inline-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                                <i class="fas fa-user-tie fa-2x"></i>
                            </div>
                        </div>
                        <div>
                            <h6 class="text-muted text-uppercase mb-1 small">Guru Kontributor</h6>
                            <h2 class="fw-bold mb-0"><?php echo $teachers_count; ?></h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Materials Table -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Daftar Materi Pembelajaran</h5>
            <span class="badge bg-primary"><?php echo mysqli_num_rows($result_materials); ?> Materi</span>
        </div>
        <div class="card-body">
            <?php if (mysqli_num_rows($result_materials) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Judul</th>
                                <th>Kelas</th>
                                <th>Tingkat</th>
                                <th>Guru</th>
                                <th>Quiz</th>
                                <th>Tanggal Dibuat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($material = mysqli_fetch_assoc($result_materials)): ?>
                                <tr>
                                    <td><?php echo $material['judul']; ?></td>
                                    <td><?php echo $material['kelas_nama']; ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $material['tingkat']; ?></span>
                                    </td>
                                    <td><?php echo $material['guru_nama']; ?></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $material['jumlah_quiz']; ?> quiz</span>
                                    </td>
                                    <td><?php echo formatDate($material['tanggal_dibuat']); ?></td>
                                    <td>
                                        <a href="../guru/material_detail.php?id=<?php echo $material['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <p class="lead">Tidak ada materi yang ditemukan.</p>
                    <?php if (!empty($class_filter) || !empty($level_filter) || !empty($search)): ?>
                        <p>Coba ubah filter atau kriteria pencarian Anda.</p>
                        <a href="materials.php" class="btn btn-outline-primary">
                            <i class="fas fa-redo me-2"></i> Reset Filter
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Materials by Class Chart -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Distribusi Materi per Kelas</h5>
        </div>
        <div class="card-body">
            <?php
            // Get materials count by class
            $query_chart = "SELECT k.nama as kelas_nama, COUNT(m.id) as jumlah_materi 
                          FROM kelas k 
                          LEFT JOIN materi_coding m ON k.id = m.kelas_id 
                          GROUP BY k.id 
                          ORDER BY jumlah_materi DESC";
            $result_chart = mysqli_query($conn, $query_chart);
            
            $class_labels = [];
            $material_counts = [];
            
            while ($row = mysqli_fetch_assoc($result_chart)) {
                $class_labels[] = $row['kelas_nama'];
                $material_counts[] = $row['jumlah_materi'];
            }
            ?>
            
            <canvas id="materialsChart" height="100"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var ctx = document.getElementById('materialsChart').getContext('2d');
    var materialsChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($class_labels); ?>,
            datasets: [{
                label: 'Jumlah Materi',
                data: <?php echo json_encode($material_counts); ?>,
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
                    ticks: {
                        precision: 0
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
});
</script>

<?php
// Include footer
include_once '../../includes/footer.php';
?> 