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
    // Master list of all manager-panel permissions
    $allPerms = [
        // Content
        'courses_view','courses_edit','courses_create','courses_delete',
        'internships_view','internships_edit','internships_create','internships_delete',
        // Users & data
        'users_view','enrollments_view','reports_view',
        // Homepage banners
        'banners_view','banners_edit',
        // Communications
        'contacts_view','contacts_edit',
        'offline_apps_view','offline_apps_edit',
        // Certificates
        'certificates_view','certificates_edit',
        // Coupons
        'coupons_view','coupons_create','coupons_edit','coupons_delete',
        // Referrals
        'referrals_view',
    ];

    // Super admin = sab kuch allow
    if ($_SESSION['user_role'] === 'admin') {
        return array_fill_keys($allPerms, 1);
    }

    // Manager bhi by default sab kuch allow (listed features) —
    // manager_permissions table se per-user overrides aate hain (deny-list).
    if ($_SESSION['user_role'] === 'manager') {
        if (!isset($_SESSION['manager_permissions'])) {
            try {
                $stmt = getDB()->prepare("SELECT * FROM manager_permissions WHERE user_id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $row = $stmt->fetch();
            } catch (Exception $e) {
                $row = false;
            }

            if ($row && is_array($row)) {
                // DB row exists — use those values, fill unset keys with default 1
                $merged = array_fill_keys($allPerms, 1);
                foreach ($row as $k => $v) {
                    if (in_array($k, $allPerms, true)) {
                        $merged[$k] = (int)$v;
                    }
                }
                $_SESSION['manager_permissions'] = $merged;
            } else {
                // No row found — grant all listed defaults
                $_SESSION['manager_permissions'] = array_fill_keys($allPerms, 1);
            }
        }
        return $_SESSION['manager_permissions'];
    }

    return [];
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