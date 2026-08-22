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

// Only an existing admin may grant the admin role to the account being created
$canGrantAdmin = \MultiFlexi\Security\RbacHelpers::isAvailable() && \MultiFlexi\Security\RbacHelpers::isCurrentUserAdmin();

$firstname = WebPage::singleton()->getRequestValue('firstname');
$lastname = WebPage::singleton()->getRequestValue('lastname');

if (WebPage::singleton()->isPosted()) {
    $emailAddress = addslashes(strtolower(WebPage::singleton()->getRequestValue('email_address')));
    $login = addslashes(WebPage::singleton()->getRequestValue('login'));
    $password = addslashes(WebPage::singleton()->getRequestValue('password'));
    $confirmation = addslashes(WebPage::singleton()->getRequestValue('confirmation'));
    // Re-check server-side: a tampered request must never grant admin to a non-admin creator
    $makeAdmin = $canGrantAdmin && WebPage::singleton()->getRequestValue('make_admin') === '1';

    $error = false;

    if (empty($login)) {
        $error = true;
        WebPage::singleton()->addStatusMessage(_('Username is mandatory'), 'warning');
    }

    if (!filter_var($emailAddress, \FILTER_VALIDATE_EMAIL)) {
        $error = true;
        WebPage::singleton()->addStatusMessage(_('invalid mail address'), 'warning');
    } else {
        $testuser = new \MultiFlexi\User();
        $testuser->setkeyColumn('email');
        $testuser->loadFromSQL(addslashes($emailAddress));

        if ($testuser->getUserName()) {
            $error = true;
            WebPage::singleton()->addStatusMessage(sprintf(
                _('Mail address %s is already registered'),
                $emailAddress,
            ), 'warning');
        }

        unset($testuser);
    }

    $passwordValidator = new \MultiFlexi\Security\PasswordValidator(
        \Ease\Shared::cfg('PASSWORD_MIN_LENGTH', 8),
        \Ease\Shared::cfg('PASSWORD_REQUIRE_UPPERCASE', true),
        \Ease\Shared::cfg('PASSWORD_REQUIRE_LOWERCASE', true),
        \Ease\Shared::cfg('PASSWORD_REQUIRE_NUMBERS', true),
        \Ease\Shared::cfg('PASSWORD_REQUIRE_SPECIAL_CHARS', true),
    );

    $passwordValidation = $passwordValidator->validate($password);

    if (!$passwordValidation['valid']) {
        $error = true;

        foreach ($passwordValidation['errors'] as $passwordError) {
            WebPage::singleton()->addStatusMessage($passwordError, 'warning');
        }
    } elseif ($password !== $confirmation) {
        $error = true;
        WebPage::singleton()->addStatusMessage(_('Password control does not match'), 'warning');
    }

    $testuser = new \MultiFlexi\User();
    $testuser->setkeyColumn('login');
    $testuser->loadFromSQL(addslashes($login));

    if ($testuser->getMyKey()) {
        $error = true;
        WebPage::singleton()->addStatusMessage(sprintf(
            _('Username %s is used. Please choose another one'),
            $login,
        ), 'warning');
    }

    unset($testuser);

    if ($error === false) {
        $newUser = new \MultiFlexi\User();

        if (
            $newUser->dbsync([
                'email' => $emailAddress,
                'login' => $login,
                $newUser->passwordColumn => $newUser->encryptPassword($password),
                'firstname' => $firstname,
                'lastname' => $lastname,
            ])
        ) {
            $newUser->setDataValue('enabled', true);
            $newUser->saveToSQL();

            WebPage::singleton()->addStatusMessage(sprintf(
                _('User account "%s" created'),
                $login,
            ), 'success');

            if ($makeAdmin) {
                if (\MultiFlexi\Security\RbacHelpers::assignRoleToUser(
                    (int) $newUser->getUserID(),
                    'admin',
                    (int) \Ease\Shared::user()->getUserID(),
                )) {
                    // Keep the legacy settings-based admin flag in sync — several
                    // older pages (consent-api.php, admin-data-corrections.php,
                    // admin-deletion-requests.php, gdpr-user-deletion-request.php)
                    // still check this instead of the RBAC role.
                    $newUser->setSettingValue('admin', true);
                    $newUser->saveToSQL();
                    WebPage::singleton()->addStatusMessage(_('Administrator role granted'), 'success');
                } else {
                    WebPage::singleton()->addStatusMessage(_('Could not grant the administrator role'), 'warning');
                }
            }

            // Notify the new user by mail with their sign-on info. The creator
            // stays logged in as themselves — unlike createaccount.php's
            // bootstrap flow, this page never auto-logs-in as the new account.
            $email = WebPage::singleton()->addItem(new \Ease\HtmlMailer(
                $newUser->getDataValue('email'),
                _('Sign On info'),
            ));
            $email->setMailHeaders(['From' => \Ease\Shared::cfg('EMAIL_FROM', 'multiflexi@'.$_SERVER['SERVER_NAME'])]);
            $email->addItem(new \Ease\Html\DivTag(sprintf(_('Your new %s account:')."\n", \Ease\Shared::appName())));
            $email->addItem(new \Ease\Html\DivTag(' Login: '.$newUser->getUserLogin()."\n"));
            $email->addItem(new \Ease\Html\DivTag(' Password: '.$password."\n"));

            try {
                $email->send();
            } catch (\Ease\Exception $exc) {
            }

            WebPage::singleton()->redirect('user.php?id='.$newUser->getUserID());

            exit;
        }

        WebPage::singleton()->addStatusMessage(_('User account creation failed'), 'error');
    }
}

WebPage::singleton()->setBreadcrumb([
    _('Administration') => 'users.php',
    _('New User Account') => '',
]);
WebPage::singleton()->addItem(new PageTop(_('New User Account')));

WebPage::singleton()->includeJavaScript('js/password-strength.js');

$regFace = new \Ease\TWB5\Panel(_('New User Account'));

$regForm = $regFace->addItem(new ColumnsForm(new \MultiFlexi\User()));

$regForm->addInput(
    new \Ease\Html\InputTextTag('firstname', $firstname),
    _('Firstname'),
);
$regForm->addInput(
    new \Ease\Html\InputTextTag('lastname', $lastname),
    _('Lastname'),
);

$regForm->addInput(new \Ease\Html\InputTextTag('login'), _('User name').' *');
$regForm->addInput(
    new \Ease\Html\InputPasswordTag('password'),
    _('Password').' *',
);
$regForm->addInput(
    new \Ease\Html\InputPasswordTag('confirmation'),
    _('Password confirmation').' *',
);
$regForm->addInput(
    new \Ease\Html\InputTextTag('email_address'),
    _('eMail address').' *',
);

if ($canGrantAdmin) {
    $regForm->addItem(new \Ease\Html\DivTag([
        new \Ease\Html\CheckboxTag('make_admin', false, '1', ['id' => 'make_admin']),
        ' ',
        new \Ease\Html\LabelTag('make_admin', '&nbsp;'._('Grant administrator role')),
    ], ['class' => 'form-check mb-3']));
}

$regForm->addItem(new \Ease\Html\DivTag(new \Ease\Html\InputSubmitTag(
    'Register',
    _('Create User'),
    ['title' => _('create new user account'), 'class' => 'btn btn-success'],
)));

if (isset($_POST)) {
    $regForm->fillUp($_POST);
}

WebPage::singleton()->container->addItem($regFace);

WebPage::singleton()->addItem(new PageBottom());
WebPage::singleton()->draw();
