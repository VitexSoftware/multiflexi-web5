<?php

declare(strict_types=1);

/**
 * This file is part of the MultiFlexi package
 *
 * https://multiflexi.eu/
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace MultiFlexi\Security;

/**
 * Role-Based Access Control (RBAC) — web5-facing facade.
 *
 * The DB-backed role/permission primitives (querying and writing
 * rbac_roles / rbac_permissions / rbac_role_permissions / rbac_user_roles)
 * live in MultiFlexi\Rbac (multiflexi-core) — the same class multiflexi-cli
 * and multiflexi-server use, so all three interfaces share one
 * implementation instead of three hand-rolled copies of this SQL.
 *
 * What stays here, because it is web5-specific and doesn't belong in a
 * portable core library:
 *  - per-request result caching
 *  - security audit logging ($GLOBALS['securityAuditLogger'])
 *  - session-based "current user" resolution
 *  - default role/permission/mapping seed data for this application
 *
 * Table schema (rbac_roles, rbac_permissions, rbac_role_permissions,
 * rbac_user_roles, rbac_role_hierarchy) is owned by multiflexi-database
 * migrations (20260715015632_rbac_roles.php,
 * 20260824192005_rbac_permissions.php) — this class no longer creates
 * those tables itself.
 */
class RoleBasedAccessControl
{
    private \MultiFlexi\Rbac $rbac;

    /**
     * Cache for roles/permissions checks within one request.
     */
    private array $cache = [];

    /**
     * Default system roles and permissions, seeded on first construction.
     */
    private array $defaultRoles = [
        'super_admin' => [
            'name' => 'Super Administrator',
            'description' => 'Full system access with all permissions',
            'is_system' => true,
        ],
        'admin' => [
            'name' => 'Administrator',
            'description' => 'Administrative access to most system functions',
            'is_system' => true,
        ],
        'editor' => [
            'name' => 'Editor',
            'description' => 'Can create and edit content',
            'is_system' => true,
        ],
        'user' => [
            'name' => 'User',
            'description' => 'Basic user access',
            'is_system' => true,
        ],
        'viewer' => [
            'name' => 'Viewer',
            'description' => 'Read-only access',
            'is_system' => true,
        ],
    ];
    private array $defaultPermissions = [
        // System permissions
        'system.admin' => 'Full system administration',
        'system.config' => 'Modify system configuration',
        'system.backup' => 'Create and restore backups',
        'system.logs' => 'View system logs',
        // User management
        'users.create' => 'Create new users',
        'users.read' => 'View user information',
        'users.update' => 'Update user information',
        'users.delete' => 'Delete users',
        'users.impersonate' => 'Login as other users',
        // Role and permission management
        'roles.create' => 'Create new roles',
        'roles.read' => 'View roles',
        'roles.update' => 'Update roles',
        'roles.delete' => 'Delete roles',
        'roles.assign' => 'Assign roles to users',
        // Company management
        'companies.create' => 'Create companies',
        'companies.read' => 'View companies',
        'companies.update' => 'Update companies',
        'companies.delete' => 'Delete companies',
        // Application management
        'applications.create' => 'Create applications',
        'applications.read' => 'View applications',
        'applications.update' => 'Update applications',
        'applications.delete' => 'Delete applications',
        // Job management
        'jobs.create' => 'Create jobs',
        'jobs.read' => 'View jobs',
        'jobs.update' => 'Update jobs',
        'jobs.delete' => 'Delete jobs',
        'jobs.execute' => 'Execute jobs manually',
        // Security management
        'security.audit' => 'View security audit logs',
        'security.config' => 'Configure security settings',
        'security.whitelist' => 'Manage IP whitelist',
        // Profile management
        'profile.read' => 'View own profile',
        'profile.update' => 'Update own profile',
    ];

    /**
     * Constructor.
     *
     * @param \PDO       $pdo    Database connection (kept for API compatibility; MultiFlexi\Rbac connects via multiflexi-core's own config)
     * @param null|array $tables unused — table names are fixed in MultiFlexi\Rbac, kept for API compatibility
     */
    public function __construct(\PDO $pdo, ?array $tables = null)
    {
        $this->rbac = new \MultiFlexi\Rbac();

        $this->initializeDefaultData();
    }

    /**
     * Create a new role.
     *
     * @return null|int Role ID or null on failure
     */
    public function createRole(string $name, string $displayName, ?string $description = null, bool $isSystem = false): ?int
    {
        try {
            $roleId = $this->rbac->createRole($name, $displayName, $description, $isSystem);

            $this->clearCache();

            if ($roleId !== null && isset($GLOBALS['securityAuditLogger'])) {
                $GLOBALS['securityAuditLogger']->logEvent(
                    'role_created',
                    "Role '{$name}' created with ID {$roleId}",
                    'low',
                    null,
                    ['role_name' => $name, 'role_id' => $roleId],
                );
            }

            return $roleId;
        } catch (\Exception $e) {
            error_log('Failed to create role: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Create a new permission.
     *
     * @return null|int Permission ID or null on failure
     */
    public function createPermission(string $name, ?string $description = null, ?string $resource = null, ?string $action = null, bool $isSystem = false): ?int
    {
        try {
            return $this->rbac->createPermission($name, $description, $resource, $action, $isSystem);
        } catch (\Exception $e) {
            error_log('Failed to create permission: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Assign a permission to a role.
     */
    public function assignPermissionToRole(int $roleId, string $permissionName, ?int $grantedBy = null): bool
    {
        try {
            $success = $this->rbac->assignPermissionToRole($roleId, $permissionName, $grantedBy);

            if ($success) {
                $this->clearCache();
            }

            return $success;
        } catch (\Exception $e) {
            error_log('Failed to assign permission to role: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Assign a role to a user.
     */
    public function assignRoleToUser(int $userId, int $roleId, ?int $assignedBy = null, ?string $expiresAt = null): bool
    {
        try {
            $success = $this->rbac->assignRoleToUser($userId, $roleId, $assignedBy, $expiresAt);

            if ($success) {
                $this->clearCache();

                if (isset($GLOBALS['securityAuditLogger'])) {
                    $roleName = $this->getRoleById($roleId)['name'] ?? "ID:{$roleId}";
                    $GLOBALS['securityAuditLogger']->logEvent(
                        'role_assigned',
                        "Role '{$roleName}' assigned to user {$userId}",
                        'medium',
                        $assignedBy,
                        ['user_id' => $userId, 'role_id' => $roleId, 'role_name' => $roleName],
                    );
                }
            }

            return $success;
        } catch (\Exception $e) {
            error_log('Failed to assign role to user: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check if a user has a specific permission.
     */
    public function userHasPermission(int $userId, string $permissionName): bool
    {
        $cacheKey = "user_permission_{$userId}_{$permissionName}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            return $this->cache[$cacheKey] = $this->rbac->userHasPermission($userId, $permissionName);
        } catch (\Exception $e) {
            error_log('Failed to check user permission: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Check if a user has a specific role.
     */
    public function userHasRole(int $userId, string $roleName): bool
    {
        $cacheKey = "user_role_{$userId}_{$roleName}";

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            return $this->cache[$cacheKey] = $this->rbac->userHasRole($userId, [$roleName]);
        } catch (\Exception $e) {
            error_log('Failed to check user role: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get all roles assigned to a user.
     *
     * @return array Array of role data
     */
    public function getUserRoles(int $userId): array
    {
        try {
            return $this->rbac->getUserRoleDetails($userId);
        } catch (\Exception $e) {
            error_log('Failed to get user roles: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get all permissions for a user (including inherited from roles).
     *
     * @return array Array of permission data
     */
    public function getUserPermissions(int $userId): array
    {
        try {
            return $this->rbac->getUserPermissions($userId);
        } catch (\Exception $e) {
            error_log('Failed to get user permissions: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Remove a role from a user.
     */
    public function removeRoleFromUser(int $userId, int $roleId): bool
    {
        try {
            $success = $this->rbac->removeRoleFromUser($userId, $roleId);

            if ($success) {
                $this->clearCache();

                if (isset($GLOBALS['securityAuditLogger'])) {
                    $roleName = $this->getRoleById($roleId)['name'] ?? "ID:{$roleId}";
                    $GLOBALS['securityAuditLogger']->logEvent(
                        'role_removed',
                        "Role '{$roleName}' removed from user {$userId}",
                        'medium',
                        null,
                        ['user_id' => $userId, 'role_id' => $roleId, 'role_name' => $roleName],
                    );
                }
            }

            return $success;
        } catch (\Exception $e) {
            error_log('Failed to remove role from user: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Get all available roles.
     *
     * @return array Array of role data
     */
    public function getAllRoles(bool $includeInactive = false): array
    {
        try {
            return $this->rbac->getAllRoles($includeInactive);
        } catch (\Exception $e) {
            error_log('Failed to get all roles: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get all available permissions.
     *
     * @return array Array of permission data
     */
    public function getAllPermissions(): array
    {
        try {
            return $this->rbac->getAllPermissions();
        } catch (\Exception $e) {
            error_log('Failed to get all permissions: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get permissions for a specific role.
     *
     * @return array Array of permission data
     */
    public function getRolePermissions(int $roleId): array
    {
        try {
            return $this->rbac->getRolePermissions($roleId);
        } catch (\Exception $e) {
            error_log('Failed to get role permissions: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Get RBAC statistics.
     *
     * @return array Statistics data
     */
    public function getStatistics(): array
    {
        try {
            return $this->rbac->getStatistics();
        } catch (\Exception $e) {
            error_log('Failed to get RBAC statistics: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Enforce permission check - throws exception if user lacks permission.
     *
     * @throws \Exception If user lacks permission
     */
    public function enforcePermission(int $userId, string $permissionName, ?string $errorMessage = null): void
    {
        if (!$this->userHasPermission($userId, $permissionName)) {
            $message = $errorMessage ?: "Access denied: Missing permission '{$permissionName}'";

            if (isset($GLOBALS['securityAuditLogger'])) {
                $GLOBALS['securityAuditLogger']->logEvent(
                    'access_denied',
                    "Access denied for user {$userId}, missing permission: {$permissionName}",
                    'medium',
                    $userId,
                    ['permission' => $permissionName],
                );
            }

            throw new \Exception($message, 403);
        }
    }

    /**
     * Check if current user (from session) has permission.
     */
    public function currentUserHasPermission(string $permissionName): bool
    {
        $userId = self::getCurrentUserId();

        return $userId ? $this->userHasPermission($userId, $permissionName) : false;
    }

    /**
     * Check if the current user has a specific role.
     */
    public function hasRole(string $roleName): bool
    {
        $userId = self::getCurrentUserId();

        return $userId ? $this->userHasRole($userId, $roleName) : false;
    }

    /**
     * Check if a specific role is assigned to ANY user in the system.
     * This is useful for first-run detection or system-wide checks.
     */
    public function isRoleAssigned(string $roleName): bool
    {
        try {
            return $this->rbac->isRoleAssigned($roleName);
        } catch (\Exception $e) {
            error_log('Failed to check if role is assigned: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Initialize default roles and permissions.
     */
    private function initializeDefaultData(): void
    {
        foreach ($this->defaultPermissions as $name => $description) {
            [$resource, $action] = explode('.', $name, 2);
            $this->createPermission($name, $description, $resource, $action, true);
        }

        foreach ($this->defaultRoles as $roleName => $roleData) {
            $this->createRole(
                $roleName,
                $roleData['name'],
                $roleData['description'],
                $roleData['is_system'],
            );
        }

        $this->assignDefaultPermissions();
    }

    /**
     * Assign default permissions to system roles.
     */
    private function assignDefaultPermissions(): void
    {
        $rolePermissions = [
            'super_admin' => array_keys($this->defaultPermissions), // All permissions
            'admin' => [
                'users.create', 'users.read', 'users.update', 'users.delete',
                'roles.read', 'roles.assign',
                'companies.create', 'companies.read', 'companies.update', 'companies.delete',
                'applications.create', 'applications.read', 'applications.update', 'applications.delete',
                'jobs.create', 'jobs.read', 'jobs.update', 'jobs.delete', 'jobs.execute',
                'security.audit', 'security.config',
                'profile.read', 'profile.update',
                'system.config', 'system.logs',
            ],
            'editor' => [
                'companies.read', 'companies.update',
                'applications.read', 'applications.update',
                'jobs.create', 'jobs.read', 'jobs.update', 'jobs.execute',
                'profile.read', 'profile.update',
            ],
            'user' => [
                'companies.read',
                'applications.read',
                'jobs.create', 'jobs.read', 'jobs.update',
                'profile.read', 'profile.update',
            ],
            'viewer' => [
                'companies.read',
                'applications.read',
                'jobs.read',
                'profile.read',
            ],
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $roleId = $this->getRoleIdByName($roleName);

            if ($roleId) {
                foreach ($permissions as $permissionName) {
                    $this->assignPermissionToRole($roleId, $permissionName);
                }
            }
        }
    }

    /**
     * Get role ID by name.
     */
    private function getRoleIdByName(string $name): ?int
    {
        return $this->rbac->getAvailableRoles()[$name] ?? null;
    }

    /**
     * Get role by ID.
     */
    private function getRoleById(int $id): ?array
    {
        foreach ($this->rbac->getAllRoles(true) as $role) {
            if ((int) $role['id'] === $id) {
                return $role;
            }
        }

        return null;
    }

    /**
     * Clear internal cache.
     */
    private function clearCache(): void
    {
        $this->cache = [];
    }

    /**
     * Get current user ID from session.
     */
    private static function getCurrentUserId(): ?int
    {
        if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            return (int) $_SESSION['user_id'];
        }

        if (isset($_SESSION['USER_ID']) && is_numeric($_SESSION['USER_ID'])) {
            return (int) $_SESSION['USER_ID'];
        }

        if (class_exists('\\Ease\\User') && method_exists('\\Ease\\User', 'singleton')) {
            $user = \Ease\User::singleton();

            if (method_exists($user, 'getUserID') && $user->getUserID()) {
                return (int) $user->getUserID();
            }
        }

        return null;
    }
}
