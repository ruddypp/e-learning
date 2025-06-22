<?php
// Include database connection
require_once 'config/database.php';

// Check table structure
$tables = ['pengguna', 'kelas', 'materi_coding'];

foreach ($tables as $table) {
    echo "<h2>Table structure for: $table</h2>";
    $query = "DESCRIBE $table";
    $result = mysqli_query($conn, $query);
    
    if (!$result) {
        echo "Error getting table structure: " . mysqli_error($conn);
        continue;
    }
    
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = mysqli_fetch_assoc($result)) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "<br>";
}
?> 