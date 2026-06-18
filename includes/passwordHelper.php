<?php
declare(strict_types=1);

/**
 * Reglas de contrasena del sistema (seccion de Seguridad). Fuente de verdad
 * UNICA: la usan el restablecimiento de contrasena y cualquier alta/cambio de
 * contrasena de usuario, para que las reglas sean siempre las mismas.
 *
 * Requisitos:
 *  - Entre 8 y 20 caracteres
 *  - Al menos una letra mayuscula
 *  - Al menos una letra minuscula
 *  - Al menos un numero
 *  - Al menos un caracter especial
 */
function validarPassword(string $password): bool
{
    $largo = mb_strlen($password);
    return $largo >= 8
        && $largo <= 20
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^A-Za-z0-9]/', $password) === 1;
}
