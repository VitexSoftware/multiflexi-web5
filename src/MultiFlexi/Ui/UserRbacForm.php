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

namespace MultiFlexi\Ui;

use Ease\Html\DivTag;
use Ease\Html\InputHiddenTag;
use Ease\TWB5\SubmitButton;
use MultiFlexi\Security\RbacHelpers;
use MultiFlexi\User;

/**
 * RBAC role assignment form for user administration.
 */
class UserRbacForm extends SecureForm
{
    /**
     * @param User $user User to manage roles for
     */
    public function __construct(User $user)
    {
        $userId = $user->getMyKey();
        parent::__construct(['name' => 'userRbac'.$userId]);

        if (!RbacHelpers::isAvailable()) {
            $this->addItem(new DivTag(
                '<i class="fas fa-info-circle"></i> '._('RBAC is not enabled. Set RBAC_ENABLED=true in configuration to activate role-based access control.'),
                ['class' => 'alert alert-info'],
            ));

            return;
        }

        // Get current user roles
        $userRoles = $GLOBALS['rbac']->getUserRoles((int) $userId);
        $selectedRoleIds = array_column($userRoles, 'id');

        // Role assignment section
        $this->addItem(new DivTag(
            '<h6><i class="fas fa-user-shield"></i> '._('Assigned Roles').'</h6>',
            ['class' => 'mb-3'],
        ));

        $this->addItem(new DivTag(
            RbacHelpers::generateRoleSelectHtml('roles', $selectedRoleIds),
            ['class' => 'mb-3'],
        ));

        // Effective permissions display
        $userPermissions = $GLOBALS['rbac']->getUserPermissions((int) $userId);
        $this->addItem(new DivTag(
            '<h6 class="mt-4"><i class="fas fa-key"></i> '._('Effective Permissions').'</h6>'
            .'<small class="text-muted">'._('Permissions inherited from assigned roles').'</small>',
            ['class' => 'mb-2'],
        ));

        $this->addItem(new DivTag(
            RbacHelpers::generatePermissionsTableHtml($userPermissions),
            ['class' => 'mb-3', 'style' => 'max-height: 400px; overflow-y: auto;'],
        ));

        // Hidden fields
        $this->addItem(new InputHiddenTag('user_id', (string) $userId));
        $this->addItem(new InputHiddenTag('action', 'rbac_update'));

        $this->addItem(new DivTag(new SubmitButton(
            _('Save Roles'),
            'primary',
        ), ['style' => 'text-align: right']));
    }
}
