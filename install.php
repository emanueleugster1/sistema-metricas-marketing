<?php
/**
 * Instalación automática del sistema de cupones
 */

// Leer archivo .env
$env = [];
$lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lines as $line) {
    if (strpos($line, '=') !== false) {
        list($key, $value) = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
}

echo "Iniciando instalación...<br>";
echo "Host: {$env['DB_HOST']}<br>";
echo "Base de datos: {$env['DB_DATABASE']}<br>";

try {
    // Probar conexión básica primero
    echo "Probando conexión al servidor MySQL...<br>";
    $dsn = "mysql:host={$env['DB_HOST']}";
    $pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10
    ]);
    echo "✓ Conexión al servidor MySQL exitosa<br>";
    

    
    // Ejecutar create_database_metricas.sql línea por línea
    echo "Ejecutando configuración de tablas...<br>";
    $sql = file_get_contents('create_database_metricas.sql');
    
    // Ejecutar create_database_metricas.sql completo
    echo "Ejecutando create_database_metricas.sql...<br>";
    
    $pdo->exec($sql);
    echo "✓ Configuración completada<br>";
    echo "\nINSTALACIÓN EXITOSA: El sistema está listo para usar.";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Connection timed out') !== false) {
        echo "ERROR: No se puede conectar al servidor de base de datos.<br>";
        echo "Verifica que:<br>";
        echo "1. El servidor MySQL esté ejecutándose<br>";
        echo "2. La IP {$env['DB_HOST']} sea correcta<br>";
        echo "3. El puerto 3306 esté abierto<br>";
        echo "4. Las credenciales sean válidas<br>";
    } else {
        echo "ERROR EN LA INSTALACIÓN: " . $e->getMessage();
    }
} catch (Exception $e) {
    echo "ERROR EN LA INSTALACIÓN: " . $e->getMessage();
}
?>