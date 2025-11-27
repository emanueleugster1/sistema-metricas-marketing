<?php
require_once __DIR__ . '/config/databaseConfig.php';
echo "<h1>Sistema de Centralización de Métricas</h1>";
echo "<h2>Docker Environment Status</h2>";
echo "<p><strong>✅ PHP Version:</strong> " . PHP_VERSION . "</p>";
try {
    $pdo = Database::getInstance()->getConnection();
    $dbNameRow = $pdo->query('SELECT DATABASE() AS db')->fetch();
    $dbName = $dbNameRow['db'] ?? '';
    echo "<p><strong>✅ MySQL Connection:</strong> Successful</p>";
    echo "<p><strong>📊 Database:</strong> " . htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') . "</p>";
    $tables = $pdo->query('SHOW TABLES')->fetchAll();
    $count = is_array($tables) ? count($tables) : 0;
    echo "<p><strong>📁 Tables:</strong> $count</p>";
    if ($count === 0) {
        echo "<p>⚠️ No hay tablas creadas aún.</p>";
    }

    try {
        $users = $pdo->query('SELECT id, nombre, email, activo, fecha_creacion FROM usuarios ORDER BY id DESC LIMIT 10')->fetchAll();
        $usersCount = is_array($users) ? count($users) : 0;
        echo "<hr>";
        echo "<h3>Usuarios (" . $usersCount . ")</h3>";
        if ($usersCount === 0) {
            echo "<p>Sin registros en la tabla usuarios.</p>";
        } else {
            echo "<table border='1' cellpadding='6' cellspacing='0'>";
            echo "<thead><tr><th>ID</th><th>Nombre</th><th>Email</th><th>Activo</th><th>Creado</th></tr></thead><tbody>";
            foreach ($users as $u) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars((string)$u['id'], ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars((string)$u['nombre'], ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . htmlspecialchars((string)$u['email'], ENT_QUOTES, 'UTF-8') . "</td>";
                echo "<td>" . ((isset($u['activo']) && (int)$u['activo'] === 1) ? 'Sí' : 'No') . "</td>";
                echo "<td>" . htmlspecialchars((string)($u['fecha_creacion'] ?? ''), ENT_QUOTES, 'UTF-8') . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
    } catch (Throwable $e) {
        echo "<p><strong>❌ Error consultando usuarios:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
    }
} catch (Throwable $e) {
    echo "<p><strong>❌ MySQL Connection:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
}
echo "<hr>";
echo "<p><a href='/views/'>📁 Views Directory</a></p>";
echo "<p>🚀 <em>Ready for development!</em></p>";
?>
