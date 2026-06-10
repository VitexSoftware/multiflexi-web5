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

// Ensure user is logged in
WebPage::singleton()->onlyForLogged();

$currentUser = new \MultiFlexi\User();
$currentUser->loadFromSQL(\Ease\Shared::user()->getUserID());

// Handle profile update form submission
if (WebPage::singleton()->isPosted()) {
    if (WebPage::singleton()->getRequestValue('action') === 'change_password') {
        $currentPassword = trim((string) WebPage::singleton()->getRequestValue('current_password'));
        $newPassword = trim((string) WebPage::singleton()->getRequestValue('new_password'));
        $passwordConfirm = trim((string) WebPage::singleton()->getRequestValue('new_password_confirm'));

        if (empty($currentPassword) || empty($newPassword) || empty($passwordConfirm)) {
            \Ease\Shared::user()->addStatusMessage(_('All password fields are required'), 'warning');
        } elseif ($newPassword !== $passwordConfirm) {
            \Ease\Shared::user()->addStatusMessage(_('Password confirmation does not match'), 'warning');
        } elseif (!\MultiFlexi\User::passwordValidation($currentPassword, (string) $currentUser->getDataValue($currentUser->passwordColumn))) {
            \Ease\Shared::user()->addStatusMessage(_('Current password is not valid'), 'warning');
        } else {
            $passwordValidator = new \MultiFlexi\Security\PasswordValidator(
                \Ease\Shared::cfg('PASSWORD_MIN_LENGTH', 8),
                \Ease\Shared::cfg('PASSWORD_REQUIRE_UPPERCASE', true),
                \Ease\Shared::cfg('PASSWORD_REQUIRE_LOWERCASE', true),
                \Ease\Shared::cfg('PASSWORD_REQUIRE_NUMBERS', true),
                \Ease\Shared::cfg('PASSWORD_REQUIRE_SPECIAL_CHARS', true),
            );
            $passwordValidation = $passwordValidator->validate($newPassword);

            if (!$passwordValidation['valid']) {
                foreach ($passwordValidation['errors'] as $passwordError) {
                    \Ease\Shared::user()->addStatusMessage($passwordError, 'warning');
                }
            } elseif ($currentUser->passwordChange($newPassword)) {
                \Ease\Shared::user()->addStatusMessage(_('Password changed successfully'), 'success');
            } else {
                \Ease\Shared::user()->addStatusMessage(_('Password change failed'), 'error');
            }
        }
    } else {
        $correctionForm = new UserDataCorrectionForm($currentUser);
        $correctionForm->processSubmission($_POST);
    }
}

WebPage::singleton()->addItem(new PageTop(_('My Profile')));

// Create main container
$container = WebPage::singleton()->container;

// Group the profile sections into an accordion; Profile Information is open
// by default.
$profileAccordion = new \Ease\TWB5\Accordion('profileSections');

// Profile header section
$profileHeader = new \Ease\Html\DivTag([
    new \Ease\Html\H4Tag($currentUser->getUserName()),
    new \Ease\Html\PTag([
        new \Ease\Html\StrongTag(_('Login').': '),
        $currentUser->getDataValue('login'),
    ]),
    new \Ease\Html\PTag([
        new \Ease\Html\StrongTag(_('Email').': '),
        $currentUser->getDataValue('email') ?: _('(not set)'),
    ]),
    new \Ease\Html\PTag([
        new \Ease\Html\StrongTag(_('Member since').': '),
        date('F j, Y', strtotime($currentUser->getDataValue($currentUser->createColumn))),
    ]),
]);

$profileAccordion->addAccordionItem(new \Ease\TWB5\Widgets\BsIcon('person-circle').'&nbsp;'._('Profile Information'), $profileHeader, true);

// Data correction form
$profileAccordion->addAccordionItem(
    new \Ease\TWB5\Widgets\BsIcon('pencil-square').'&nbsp;'._('Update Personal Information'),
    new UserDataCorrectionForm($currentUser),
);

// Password change section
$passwordForm = new \MultiFlexi\Ui\SecureForm([
    'method' => 'POST',
    'action' => 'profile.php',
]);

$passwordForm->addItem(new \Ease\TWB5\FormGroup(
    _('Current Password'),
    new \Ease\Html\InputPasswordTag('current_password', '', ['class' => 'form-control']),
));
$passwordForm->addItem(new \Ease\TWB5\FormGroup(
    _('New Password'),
    new \Ease\Html\InputPasswordTag('new_password', '', ['class' => 'form-control']),
));
$passwordForm->addItem(new \Ease\TWB5\FormGroup(
    _('Confirm New Password'),
    new \Ease\Html\InputPasswordTag('new_password_confirm', '', ['class' => 'form-control']),
));
$passwordForm->addItem(new \Ease\Html\InputHiddenTag('action', 'change_password'));
$passwordForm->addItem(new \Ease\Html\DivTag(
    new \Ease\TWB5\SubmitButton(_('Change Password'), 'warning', ['id' => 'changePasswordButton']),
    ['class' => 'text-end'],
));

$profileAccordion->addAccordionItem(new \Ease\TWB5\Widgets\BsIcon('key').'&nbsp;'._('Change Password'), $passwordForm);

// GDPR Information section
$gdprInfo = new \Ease\Html\DivTag(new \Ease\Html\PTag(_('Under the General Data Protection Regulation (GDPR), you have several rights regarding your personal data:')));

$rightsList = new \Ease\Html\UlTag([
    new \Ease\Html\LiTag([
        new \Ease\Html\StrongTag(_('Right of Rectification (Article 16)').': '),
        _('You can request correction of inaccurate personal data using the form above.'),
    ]),
    new \Ease\Html\LiTag([
        new \Ease\Html\StrongTag(_('Right of Access (Article 15)').': '),
        new \Ease\TWB5\LinkButton('data-export.php', _('Export your data'), 'info', ['size' => 'sm', 'title' => _('Export your personal data'), 'id' => 'exportdatabutton']),
    ]),
    new \Ease\Html\LiTag([
        new \Ease\Html\StrongTag(_('Right to be Forgotten (Article 17)').': '),
        _('Contact an administrator to request account deletion.'),
    ]),
    new \Ease\Html\LiTag([
        new \Ease\Html\StrongTag(_('Right to Data Portability (Article 20)').': '),
        _('You can export your data in a machine-readable format.'),
    ]),
]);

$gdprInfo->addItem($rightsList);
$profileAccordion->addAccordionItem(new \Ease\TWB5\Widgets\BsIcon('shield-check').'&nbsp;'._('Your Rights Under GDPR'), $gdprInfo);

// Recent data changes (audit log preview)
$auditLogger = new \MultiFlexi\Audit\UserDataAuditLogger();
$recentChanges = $auditLogger->getUserAuditLog($currentUser->getId(), 10);

if (!empty($recentChanges)) {
    $auditTable = new \Ease\Html\TableTag(null, ['class' => 'table table-sm']);
    $auditTable->addRowHeaderColumns([
        _('Field'),
        _('Old Value'),
        _('New Value'),
        _('Type'),
        _('Date'),
    ]);

    foreach ($recentChanges as $change) {
        $fieldDisplayName = \MultiFlexi\GDPR\UserDataCorrectionRequest::getFieldDisplayName($change['field_name']);
        $changeTypeBadge = '';

        switch ($change['change_type']) {
            case 'direct':
                $changeTypeBadge = new \Ease\TWB5\Badge(_('Direct'), 'success');

                break;
            case 'pending_approval':
                $changeTypeBadge = new \Ease\TWB5\Badge(_('Pending'), 'warning');

                break;
            case 'approved':
                $changeTypeBadge = new \Ease\TWB5\Badge(_('Approved'), 'success');

                break;
            case 'rejected':
                $changeTypeBadge = new \Ease\TWB5\Badge(_('Rejected'), 'danger');

                break;
        }

        $auditTable->addRowColumns([
            $fieldDisplayName,
            substr($change['old_value'], 0, 30),
            substr($change['new_value'], 0, 30),
            $changeTypeBadge,
            date('M j, Y H:i', strtotime($change['created_at'])),
        ]);
    }

    $profileAccordion->addAccordionItem(new \Ease\TWB5\Widgets\BsIcon('clock-history').'&nbsp;'._('Recent Data Changes'), $auditTable);
}

$container->addItem($profileAccordion);

WebPage::singleton()->addItem(new PageBottom());
WebPage::singleton()->draw();
