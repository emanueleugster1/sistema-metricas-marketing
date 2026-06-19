<?php
require_once __DIR__ . '/config/databaseConfig.php';
echo "Creación de usuario de prueba...<br>";
try {
    $pdo = Database::getInstance()->getConnection();
    $dbNameRow = $pdo->query('SELECT DATABASE() AS db')->fetch();
    echo "Base de datos actual: " . htmlspecialchars($dbNameRow['db'] ?? '', ENT_QUOTES, 'UTF-8') . "<br>";

    $email = 'admin';
    $nombre = 'Administrador Sistema';
    $password = 'admin';

    $check = $pdo->prepare('SELECT COUNT(*) AS c FROM usuarios WHERE email = :email');
    $check->execute([':email' => $email]);
    $exists = (int)($check->fetch()['c'] ?? 0);

    if ($exists > 0) {
        echo "El usuario ya existe: " . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . "<br>";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        // El usuario inicial es administrador de la agencia principal.
        $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol_id, agencia_id) VALUES (:nombre, :email, :password_hash, (SELECT id FROM roles WHERE nombre = :rol), (SELECT MIN(id) FROM agencias))');
        $ok = $stmt->execute([
            ':nombre' => $nombre,
            ':email' => $email,
            ':password_hash' => $hash,
            ':rol' => 'administrador',
        ]);
        if ($ok) {
            echo "Usuario de prueba creado con éxito. ID: " . htmlspecialchars($pdo->lastInsertId(), ENT_QUOTES, 'UTF-8') . "<br>";
        } else {
            echo "No se pudo crear el usuario.<br>";
        }
    }

    echo "Proceso finalizado.<br>";
} catch (PDOException $e) {
    echo "ERROR: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "<br>";
} catch (Throwable $e) {
    echo "ERROR: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "<br>";
}
