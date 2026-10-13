<?php
// Super Admin is role_id = 1 (a system role, tenant_id = 0, assigned to every tenant's
// first/owner user at registration - see server/api/auth/register.php).
function isSuperAdmin($pdo, $user_id, $tenant_id) {
    $stmt = $pdo->prepare("
        SELECT ur.role_id
        FROM user_roles ur
        WHERE ur.user_id = ? AND ur.tenant_id = ? AND ur.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$user_id, $tenant_id]);
    $roleId = $stmt->fetchColumn();
    return $roleId !== false && (int)$roleId === 1;
}
