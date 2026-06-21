<?php
declare(strict_types=1);

/**
 * Cifrado de las credenciales de plataforma (Tanda 3 - Seguridad).
 *
 * El JSON de credenciales se cifra con libsodium (secretbox: cifrado autenticado)
 * antes de persistir, y se descifra al leer. La clave vive en .env (CREDENCIALES_KEY,
 * base64 de 32 bytes), nunca hardcodeada.
 *
 * Formato guardado: un SOBRE JSON valido cuyo contenido sensible va cifrado:
 *   {"_cifrado":"v1","_dato":"<base64(nonce . ciphertext)>"}
 * Al ser JSON valido, la columna `credenciales` (tipo JSON en MariaDB/MySQL, con su
 * CHECK json_valid) lo acepta SIN necesidad de cambiar el tipo ni soltar constraints.
 * El token real queda cifrado dentro de "_dato"; no se puede leer.
 *
 * Lectura RETROCOMPATIBLE: credencialesDescifrar() entiende tanto el sobre cifrado
 * nuevo como el JSON en texto plano legacy, asi ninguna conexion existente se rompe.
 * El migrador (migrar_cifrar_credenciales.php) reescribe las filas planas a cifradas
 * de forma idempotente.
 */

require_once __DIR__ . '/../config/databaseConfig.php'; // provee _readEnvFile()

const CREDENCIALES_VERSION = 'v1';

/**
 * Campos sensibles del JSON de credenciales. Fuente unica de verdad usada por el
 * modelo (merge "vacio = mantener") y por la vista (enmascarado del Arreglo 2).
 */
function camposSensiblesCredenciales(): array
{
    return ['access_token'];
}

/**
 * Devuelve la clave binaria (32 bytes) desde .env, o lanza si falta/es invalida.
 * No persistimos nada que despues no podriamos descifrar.
 */
function _credencialesClave(): string
{
    static $clave = null;
    if ($clave !== null) {
        return $clave;
    }
    $env = _readEnvFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
    $b64 = (string)($env['CREDENCIALES_KEY'] ?? getenv('CREDENCIALES_KEY') ?: '');
    if ($b64 === '') {
        throw new RuntimeException('CREDENCIALES_KEY ausente en .env: no se puede cifrar/descifrar credenciales.');
    }
    $bin = base64_decode($b64, true);
    if ($bin === false || strlen($bin) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
        throw new RuntimeException('CREDENCIALES_KEY invalida: debe ser base64 de ' . SODIUM_CRYPTO_SECRETBOX_KEYBYTES . ' bytes.');
    }
    $clave = $bin;
    return $clave;
}

/** Cifra un texto y lo devuelve como sobre JSON valido. */
function cifrarTextoCredencial(string $texto): string
{
    $clave = _credencialesClave();
    $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = sodium_crypto_secretbox($texto, $nonce, $clave);
    $sobre = ['_cifrado' => CREDENCIALES_VERSION, '_dato' => base64_encode($nonce . $cipher)];
    return (string)json_encode($sobre, JSON_UNESCAPED_SLASHES);
}

/**
 * Descifra un sobre JSON. Devuelve null si no es un sobre cifrado nuestro o si no se
 * puede (clave equivocada / dato manipulado): el secretbox es autenticado.
 */
function descifrarTextoCredencial(string $valor): ?string
{
    $sobre = json_decode($valor, true);
    if (!is_array($sobre) || !isset($sobre['_cifrado'], $sobre['_dato'])) {
        return null;
    }
    $raw = base64_decode((string)$sobre['_dato'], true);
    if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
        return null;
    }
    $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    try {
        $plano = sodium_crypto_secretbox_open($cipher, $nonce, _credencialesClave());
    } catch (Throwable $e) {
        return null;
    }
    return $plano === false ? null : $plano;
}

/** ¿El valor de la columna es un sobre cifrado nuestro? */
function credencialesEstaCifrado(string $valor): bool
{
    $sobre = json_decode($valor, true);
    return is_array($sobre) && isset($sobre['_cifrado'], $sobre['_dato']);
}

/** Cifra el array de campos de un registro de credenciales para persistir. */
function credencialesCifrar(array $campos): string
{
    $json = json_encode($campos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No se pudo serializar las credenciales para cifrar.');
    }
    return cifrarTextoCredencial($json);
}

/**
 * Lee el valor de la columna credenciales y devuelve el array de campos.
 * RETROCOMPATIBLE: sobre cifrado -> descifra; texto plano legacy -> json_decode directo.
 */
function credencialesDescifrar(?string $columna): array
{
    $columna = (string)$columna;
    if ($columna === '') {
        return [];
    }
    if (credencialesEstaCifrado($columna)) {
        $plano = descifrarTextoCredencial($columna);
        if ($plano === null) {
            return []; // no recuperable (clave/manipulacion): array vacio controlado
        }
        $data = json_decode($plano, true);
        return is_array($data) ? $data : [];
    }
    // Legacy: JSON en texto plano.
    $data = json_decode($columna, true);
    return is_array($data) ? $data : [];
}
