<?php
// Password hashing helpers.
// New hashes need ca_userPass to hold at least 60 characters. Until the column is
// widened (see migrations/001_widen_password_column.sql) accounts keep using md5 so
// nothing breaks, and old md5 hashes are upgraded on the next successful login.

function password_column_fits_hash($conn)
{
    static $fits = null;
    if ($fits !== null) {
        return $fits;
    }
    $fits = false;
    $result = $conn->query("SHOW COLUMNS FROM ca_users LIKE 'ca_userPass'");
    if ($result && ($col = $result->fetch_assoc())) {
        $type = strtolower($col['Type']);
        if (preg_match('/\((\d+)\)/', $type, $m)) {
            $fits = (int)$m[1] >= 60;
        } else {
            $fits = true; // text and blob types
        }
    }
    return $fits;
}

function hash_new_password($conn, $password)
{
    if (password_column_fits_hash($conn)) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    return md5($password);
}

function is_legacy_md5_hash($stored)
{
    return strlen($stored) === 32 && ctype_xdigit($stored);
}

function verify_stored_password($password, $stored)
{
    if (is_legacy_md5_hash($stored)) {
        return hash_equals($stored, md5($password));
    }
    return password_verify($password, $stored);
}
