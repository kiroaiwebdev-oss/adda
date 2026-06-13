<?php
require_once __DIR__ . '/db.php';
session_start();

function requireManagerAccess(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['user_role'])) {
        header('Location: manager_login.php'); exit;
    }
    if (!in_array($_SESSION['user_role'], ['admin', 'manager'])) {
        header('Location: manager_login.php?error=unauthorized'); exit;
    }
}

function getManagerPermissions(): array {
    // Super admin = sab kuch allow
    if ($_SESSION['user_role'] === 'admin') {
        return array_fill_keys([
            'courses_view','courses_edit','courses_create','courses_delete',
            'internships_view','internships_edit','internships_create','internships_delete',
            'banners_view','banners_edit','banners_create','banners_delete',
            'enrollments_view','users_view','reports_view',
            'coupons_view','coupons_edit','offline_apps_view','offline_apps_edit'
        ], 1);
    }
    if (!isset($_SESSION['manager_permissions'])) {
        $stmt = getDB()->prepare("SELECT * FROM manager_permissions WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['manager_permissions'] = $stmt->fetch() ?: [];
    }
    return $_SESSION['manager_permissions'];
}

// Hard block — 403 agar permission nahi
function checkPermission(string $perm): void {
    $perms = getManagerPermissions();
    if (empty($perms[$perm])) {
        http_response_code(403);
        include __DIR__ . '/403.php'; exit;
    }
}

// UI ke liye boolean check
function can(string $perm): bool {
    return !empty(getManagerPermissions()[$perm]);
}

// Activity log karo
function logAction(string $action, string $type = 'other', ?int $id = null, string $desc = ''): void {
    try {
        $stmt = getDB()->prepare("INSERT INTO manager_activity_log 
            (manager_id,action,entity_type,entity_id,description,ip_address) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$_SESSION['user_id'],$action,$type,$id,$desc,$_SERVER['REMOTE_ADDR']??null]);
    } catch(Exception $e) {}
}