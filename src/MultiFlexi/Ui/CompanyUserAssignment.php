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

use Ease\Html\ATag;
use Ease\Html\DivTag;
use Ease\TWB5\Table;
use Ease\TWB5\Widgets\Toggle;
use MultiFlexi\Company;
use MultiFlexi\CompanyUser;
use MultiFlexi\User;

/**
 * Company user assignment widget.
 *
 * @no-named-arguments
 */
class CompanyUserAssignment extends DivTag
{
    public function __construct(Company $company, bool $canManageAssignments = true, $properties = [])
    {
        $userer = new User();
        $allUsers = $userer->listingQuery()->select(['id', 'login', 'firstname', 'lastname', 'email'], true)->orderBy('login')->fetchAll();

        $companyUser = new CompanyUser($company);
        $assignedTo = [];

        foreach ($companyUser->getAssigned()->fetchAll() as $assignment) {
            $assignedTo[(int) $assignment['user_id']] = $assignment['role'] ?? 'viewer';
        }

        $assignmentsTable = new Table(null, ['class' => 'table table-hover mb-0', 'id' => 'company-user-assignments-table']);
        $assignmentsTable->addRowHeaderColumns([_('User'), _('Email'), _('Role'), _('Assigned')], ['class' => 'thead-light']);

        foreach ($allUsers as $userData) {
            $userId = (int) $userData['id'];
            $fullName = trim((string) ($userData['firstname'] ?? '').' '.(string) ($userData['lastname'] ?? ''));
            $label = $fullName ? $userData['login'].' ('.$fullName.')' : $userData['login'];
            $role = $assignedTo[$userId] ?? 'viewer';

            $toggle = new Toggle(
                'assign['.$userId.']',
                \array_key_exists($userId, $assignedTo),
                (string) $userId,
                [
                    'class' => 'company-user-assign-toggle',
                    'data-company-id' => $company->getMyKey(),
                    'data-user-id' => $userId,
                    'disabled' => $canManageAssignments ? null : 'disabled',
                ],
            );

            $assignmentsTable->addRowColumns([
                new ATag('user.php?id='.$userId, $label),
                (string) ($userData['email'] ?? ''),
                $role,
                $toggle,
            ]);
        }

        $csrfToken = $GLOBALS['csrfProtection']->generateToken();

        $card = new \Ease\TWB5\Card(
            new \Ease\Html\DivTag([
                new \Ease\Html\H4Tag(_('User Access Rights'), ['class' => 'card-title mb-0']),
                new \Ease\Html\SmallTag(_('Enable or disable company access for users'), ['class' => 'text-muted']),
            ], ['class' => 'd-flex justify-content-between align-items-center mb-3']),
            ['class' => 'shadow-sm border-0 mt-4'],
        );

        $searchBox = new \Ease\Html\InputSearchTag('user_search', '', [
            'placeholder' => _('Search users...'),
            'class' => 'form-control mb-3',
            'id' => 'company-user-search',
        ]);

        $cardBody = new \Ease\Html\DivTag([$searchBox, new \Ease\Html\DivTag($assignmentsTable, ['class' => 'table-responsive'])], ['class' => 'card-body']);
        $card->addItem($cardBody);

        WebPage::singleton()->addJavaScript(<<<'JS'
            $('#company-user-search').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                $('#company-user-assignments-table tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
JS);

        if ($canManageAssignments) {
            WebPage::singleton()->addJavaScript(<<<JS
                $('.company-user-assign-toggle').change(function() {
                    var toggle = $(this);
                    var companyId = toggle.data('company-id');
                    var userId = toggle.data('user-id');
                    var state = toggle.prop('checked');

                    $.post('togglecompanyuser.php', {
                        company_id: companyId,
                        user_id: userId,
                        state: state,
                        csrf_token: '{$csrfToken}'
                    }, function(data) {
                        if (data.result === 'success') {
                            // Success
                        } else {
                            alert('Error updating assignment');
                            toggle.bootstrapToggle(state ? 'off' : 'on', true);
                        }
                    }, 'json').fail(function() {
                        alert('Request failed');
                        toggle.bootstrapToggle(state ? 'off' : 'on', true);
                    });
                });
JS);
        }

        parent::__construct($card, $properties);
    }
}
