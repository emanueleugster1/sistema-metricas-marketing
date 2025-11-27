<?php 
// Verificación de funcionamiento - TFG Sistema de Métricas 
echo "<h1>Sistema de Centralización de Métricas</h1>"; 
echo "<h2>Docker Environment Status</h2>"; 

// Test PHP 
echo "<p><strong>✅ PHP Version:</strong> " . PHP_VERSION . "</p>"; 

// Test conexión MySQL 
try { 
    $host = $_ENV['DB_HOST'] ?? 'db'; 
    $dbname = $_ENV['DB_NAME'] ?? 'sistema_metricas_marketing'; 
    $username = $_ENV['DB_USER'] ?? 'metrics_user'; 
    $password = $_ENV['DB_PASS'] ?? 'metrics_pass'; 
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password); 
    echo "<p><strong>✅ MySQL Connection:</strong> Successful</p>"; 
    echo "<p><strong>📊 Database:</strong> $dbname</p>"; 
    
} catch(PDOException $e) { 
    echo "<p><strong>❌ MySQL Connection:</strong> " . $e->getMessage() . "</p>"; 
} 
 
echo "<hr>"; 
echo "<p><a href='/views/'>📁 Views Directory</a></p>"; 
echo "<p>🚀 <em>Ready for development!</em></p>"; 
?> 
