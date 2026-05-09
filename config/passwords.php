<?php
/**
 * Password helpers for migrating from legacy plain-text / MD5 storage to bcrypt.
 */

function isBcryptPasswordHash($storedPassword) {
    $info = password_get_info($storedPassword);
    return !empty($info['algo']);
}

function isLegacyMd5Password($storedPassword) {
    return preg_match('/^[a-f0-9]{32}$/i', $storedPassword) === 1;
}

function verifyStoredPassword($plainPassword, $storedPassword) {
    if (isBcryptPasswordHash($storedPassword)) {
        return [
            'valid' => password_verify($plainPassword, $storedPassword),
            'needs_rehash' => password_needs_rehash($storedPassword, PASSWORD_BCRYPT),
            'format' => 'bcrypt'
        ];
    }

    if (hash_equals((string) $storedPassword, (string) $plainPassword)) {
        return [
            'valid' => true,
            'needs_rehash' => true,
            'format' => 'plaintext'
        ];
    }

    if (isLegacyMd5Password($storedPassword) && hash_equals(strtolower($storedPassword), md5($plainPassword))) {
        return [
            'valid' => true,
            'needs_rehash' => true,
            'format' => 'md5'
        ];
    }

    return [
        'valid' => false,
        'needs_rehash' => false,
        'format' => 'unknown'
    ];
}

function hashPasswordForStorage($plainPassword) {
    return password_hash($plainPassword, PASSWORD_BCRYPT);
}

function upgradeUserPasswordHash($conn, $userId, $plainPassword) {
    $newHash = hashPasswordForStorage($plainPassword);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $newHash, $userId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}
?>
