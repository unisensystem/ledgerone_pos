<?php
// file: ../../../../includes/check_permission.php

/**
 * Check if current user has a specific permission
 * @param PDO $pdo Database connection
 * @param int $user_id Current user ID
 * @param string $category Permission category (e.g., 'HRM', 'Customer / Supplier')
 * @param string $form_name Form name (e.g., 'New Payroll', 'New Employee')
 * @param string $sub_permission Sub-permission (e.g., 'Add', 'Edit', 'Delete', 'View')
 * @return bool True if user has permission
 */
function hasPermission($pdo, $user_id, $category, $form_name, $sub_permission) {
    // Static cache to prevent multiple DB queries in same request
    static $permission_cache = [];
    
    // Create cache key
    $cache_key = "{$user_id}_{$category}_{$form_name}_{$sub_permission}";
    
    // Return cached result if available
    if (isset($permission_cache[$cache_key])) {
        return $permission_cache[$cache_key];
    }
    
    try {
        // Get user's role
        $stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $role = $stmt->fetch();
        
        if (!$role) {
            $permission_cache[$cache_key] = false;
            return false;
        }
        
        // Check if role has this permission
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM role_permissions 
            WHERE role_id = ? 
            AND category = ? 
            AND form_name = ? 
            AND sub_permission = ? 
            AND allowed = 1
        ");
        $stmt->execute([$role['role_id'], $category, $form_name, $sub_permission]);
        $result = $stmt->fetchColumn() > 0;
        
        // Cache the result
        $permission_cache[$cache_key] = $result;
        return $result;
        
    } catch (PDOException $e) {
        error_log("Permission check error: " . $e->getMessage());
        $permission_cache[$cache_key] = false;
        return false;
    }
}

/**
 * Get all permissions for current user in a specific category/form
 * @param PDO $pdo Database connection
 * @param int $user_id Current user ID
 * @param string $category Permission category
 * @param string $form_name Form name
 * @return array Array of permission strings like ['Add', 'Edit', 'Delete']
 */
function getUserPermissions($pdo, $user_id, $category, $form_name) {
    static $permissions_cache = [];
    
    $cache_key = "{$user_id}_{$category}_{$form_name}";
    
    if (isset($permissions_cache[$cache_key])) {
        return $permissions_cache[$cache_key];
    }
    
    try {
        // Get user's role
        $stmt = $pdo->prepare("SELECT role_id FROM user_roles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $role = $stmt->fetch();
        
        if (!$role) {
            $permissions_cache[$cache_key] = [];
            return [];
        }
        
        // Get all permissions for this role, category, and form
        $stmt = $pdo->prepare("
            SELECT sub_permission 
            FROM role_permissions 
            WHERE role_id = ? 
            AND category = ? 
            AND form_name = ? 
            AND allowed = 1
        ");
        $stmt->execute([$role['role_id'], $category, $form_name]);
        $permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $permissions_cache[$cache_key] = $permissions;
        return $permissions;
        
    } catch (PDOException $e) {
        error_log("Get permissions error: " . $e->getMessage());
        $permissions_cache[$cache_key] = [];
        return [];
    }
}
?>