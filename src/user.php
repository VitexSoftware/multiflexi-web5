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

require_once './init.php';

WebPage::singleton()->onlyForLogged();

$user_id = WebPage::singleton()->getRequestValue('id', 'int');

// $user = Engine::doThings($oPage);
// if (is_null($user)) {
$user = new \MultiFlexi\User($user_id);

if (WebPage::singleton()->isPosted()) {
    if (WebPage::singleton()->getRequestValue('action') === 'rbac_update') {
        // Handle RBAC role assignment
        if (\MultiFlexi\Security\RbacHelpers::isAvailable()) {
            $allRoles = \MultiFlexi\Security\RbacHelpers::getAllRoles();
            $selectedRoles = $_POST['roles'] ?? [];
            $currentRoles = $GLOBALS['rbac']->getUserRoles((int) $user_id);
            $currentRoleIds = array_map(static fn ($r) => (string) $r['id'], $currentRoles);

            // Remove roles that were unchecked
            foreach ($currentRoleIds as $roleId) {
                if (!\in_array($roleId, $selectedRoles, true)) {
                    $GLOBALS['rbac']->removeRoleFromUser((int) $user_id, (int) $roleId);
                }
            }

            // Add newly checked roles
            foreach ($selectedRoles as $roleId) {
                if (!\in_array($roleId, $currentRoleIds, true)) {
                    $GLOBALS['rbac']->assignRoleToUser(
                        (int) $user_id,
                        (int) $roleId,
                        (int) \Ease\Shared::user()->getUserID(),
                    );
                }
            }

            $user->addStatusMessage(_('Roles updated'), 'success');
        }
    } else {
        unset($_REQUEST['class']);
        $user->addStatusMessage(_('Update'), $user->takeData($_REQUEST) && $user->dbsync() ? 'success' : 'error');
    }
}

// }

if (WebPage::singleton()->getGetValue('delete', 'bool') === 'true') {
    if ($user->delete()) {
        WebPage::singleton()->redirect('users.php');

        exit;
    }
}

WebPage::singleton()->addItem(new PageTop(_('User')));

switch (WebPage::singleton()->getRequestValue('action')) {
    case 'delete':
        $confirmBlock = new \Ease\TWB5\Well();

        $confirmBlock->addItem($user);

        $confirmator = $confirmBlock->addItem(new \Ease\TWB5\Panel(_('Are you sure ?'), 'danger'));
        $confirmator->addItem(new \Ease\TWB5\LinkButton('user.php?id='.$user->getId(), _('Ne').' '.\Ease\TWB5\Part::glyphIcon('ok'), 'success'));
        $confirmator->addItem(new \Ease\TWB5\LinkButton('?delete=true&'.$user->keyColumn.'='.$user->getID(), _('Ano').' '.\Ease\TWB5\Part::glyphIcon('remove'), 'danger'));

        WebPage::singleton()->container->addItem(new \Ease\TWB5\Panel('<strong>'.$user->getUserName().'</strong>', 'info', $confirmBlock));

        break;

    default:
        //        $operationsMenu = $user->operationsMenu();
        //        $operationsMenu->setTagCss(['float' => 'right']);
        //        $operationsMenu->dropdown->addTagClass('pull-right');

        WebPage::singleton()->container->addItem(new \Ease\TWB5\Panel(['<strong>'.$user->getUserName().'</strong>'/* $operationsMenu */], 'info', new UserForm($user)));

        // RBAC role management panel
        WebPage::singleton()->container->addItem(new \Ease\TWB5\Panel(
            '<i class="fas fa-shield-alt"></i> '._('Access Control (RBAC)'),
            'warning',
            new \MultiFlexi\Ui\UserRbacForm($user),
        ));

        break;
}

WebPage::singleton()->addItem(new PageBottom());

WebPage::singleton()->draw();
