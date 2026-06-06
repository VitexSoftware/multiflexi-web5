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

$currentUser = \Ease\Shared::user();
$currentUserId = $currentUser->getUserID();

WebPage::singleton()->addItem(new PageTop(_('Home')));

$container = WebPage::singleton()->container;

// Welcome section
$welcomeCard = new \Ease\TWB5\Card();
$welcomeCard->addItem(new \Ease\Html\H2Tag(_('Welcome back').' '.$currentUser->getUserName()));
$welcomeCard->addItem(new \Ease\Html\PTag(_('This is your personal dashboard with overview of your recent activities.')));

// Quick actions — col-12 col-sm-6 col-md-3: full-width on phones, 2×2 on sm, 4 across on md+
$actionRow = new \Ease\TWB5\Row(null, 0, ['class' => 'g-2']);
$actions = [
    ['profile.php', new \Ease\TWB5\Widgets\BsIcon('person'), _('Edit Profile'), 'primary', 'editProfileButton'],
    ['data-export-page.php', new \Ease\TWB5\Widgets\BsIcon('download'), _('Export My Data'), 'info', 'exportDataButton'],
    ['consent-preferences.php', new \Ease\TWB5\Widgets\BsIcon('person-lock'), _('Privacy Settings'), 'secondary', 'privacySettingsButton'],
    ['joblist.php', new \Ease\TWB5\Widgets\BsIcon('list'), _('All Jobs'), 'success', 'allJobsButton'],
];

foreach ($actions as [$url, $icon, $label, $style, $id]) {
    $actionRow->addItem(new \Ease\Html\DivTag(
        new \Ease\TWB5\LinkButton($url, $icon.' '.$label, $style.' w-100', ['id' => $id]),
        ['class' => 'col-12 col-sm-6 col-md-3'],
    ));
}

$welcomeCard->addItem($actionRow);
$container->addItem($welcomeCard);

// User statistics
$statsRow = new \Ease\TWB5\Row();

// Note: job table uses 'launched_by' text field, not user_id
// We'll show all jobs since we can't filter by current user
$jobEngine = new \MultiFlexi\Job();
$totalJobsCount = $jobEngine->getFluentPDO()->from($jobEngine->getMyTable())
    ->count();

// Count successful jobs
$successfulJobsCount = $jobEngine->getFluentPDO()->from($jobEngine->getMyTable())
    ->where('exitcode', 0)
    ->count();

// Count failed jobs
$failedJobsCount = $jobEngine->getFluentPDO()->from($jobEngine->getMyTable())
    ->where('exitcode IS NOT NULL AND exitcode <> 0')
    ->count();

// Count user's log entries
$logEngine = new \MultiFlexi\UserLogger();
$totalLogsCount = $logEngine->listingQuery()->count();

// Display statistics cards — col-6 col-md-3 = 2×2 on mobile, 4 across on desktop
$statDefs = [
    [$totalJobsCount, _('Total Jobs in System'), 'text-center', ''],
    [$successfulJobsCount, _('Successful Jobs'), 'text-center text-success', ''],
    [$failedJobsCount, _('Failed Jobs'), 'text-center text-danger', ''],
    [$totalLogsCount, _('Log Entries'), 'text-center', ''],
];

foreach ($statDefs as [$count, $label, $numClass, $extra]) {
    $card = new \Ease\TWB5\Card();
    $card->addItem(new \Ease\Html\H3Tag($count, ['class' => $numClass]));
    $card->addItem(new \Ease\Html\PTag($label, ['class' => 'text-center text-muted mb-0']));
    $statsRow->addItem(new \Ease\Html\DivTag($card, ['class' => 'col-6 col-md-3 mb-3']));
}

$container->addItem($statsRow);

// Recent jobs section
$recentJobsCard = new \Ease\TWB5\Card(_('Recent Jobs'));

// Note: job table uses 'launched_by' text field, showing all recent jobs
$recentJobs = $jobEngine->getFluentPDO()->from('job j')
    ->select('j.id')
    ->select('a.name as app')
    ->select('c.name as company')
    ->select('j.begin')
    ->select('j.exitcode')
    ->select('j.schedule as launched')
    ->leftJoin('apps a ON j.app_id = a.id')
    ->leftJoin('company c ON j.company_id = c.id')
    ->orderBy('j.schedule DESC')
    ->limit(10)
    ->fetchAll() ?: [];

if (!empty($recentJobs)) {
    $jobsTable = new \Ease\Html\TableTag(null, ['class' => 'table table-sm table-striped']);
    $jobsTable->addRowHeaderColumns([
        _('ID'),
        _('Application'),
        _('Company'),
        _('Started'),
        _('Launched'),
        _('Status'),
    ]);

    foreach ($recentJobs as $job) {
        $statusBadge = '';

        if ($job['exitcode'] === null && $job['begin'] !== null) {
            $statusBadge = new \Ease\TWB5\Badge('🏃 '._('Running'), 'primary');
        } elseif ($job['exitcode'] === 0 || $job['exitcode'] === '0') {
            $statusBadge = new \Ease\TWB5\Badge('✅ '._('Success'), 'success');
        } elseif ($job['exitcode'] !== null) {
            $statusBadge = new \Ease\TWB5\Badge('❌ '._('Failed'), 'danger');
        } else {
            $statusBadge = new \Ease\TWB5\Badge('⏳ '._('Pending'), 'warning');
        }

        $jobsTable->addRowColumns([
            new \Ease\Html\ATag('job.php?id='.$job['id'], (string) $job['id']),
            (string) $job['app'],
            (string) $job['company'],
            $job['begin'] ? date('Y-m-d H:i:s', strtotime($job['begin'])) : '-',
            $job['launched'] ? date('Y-m-d H:i:s', strtotime($job['launched'])) : '-',
            $statusBadge,
        ]);
    }

    $recentJobsCard->addItem($jobsTable);
} else {
    $recentJobsCard->addItem(new \Ease\TWB5\Alert(_('No jobs found'), 'info'));
}

$container->addItem($recentJobsCard);

// Recent logs section
$recentLogsCard = new \Ease\TWB5\Card(_('My Recent Activity Log'));

// Use UserLogger — the user_id filter is enforced server-side in
// listingQuery(), so it cannot be bypassed via URL tampering.
WebPage::singleton()->includeJavascript('js/dismisLog.js');
$recentLogsCard->addItem(new DBDataTable($logEngine, ['buttons' => false]));

$container->addItem($recentLogsCard);

// Account information section
$accountCard = new \Ease\TWB5\Card(_('Account Information'));

$accountInfo = new \Ease\Html\DlTag(null, ['class' => 'row']);

$accountInfo->addItem(new \Ease\Html\DtTag(_('Login'), ['class' => 'col-sm-3']));
$accountInfo->addItem(new \Ease\Html\DdTag($currentUser->getDataValue('login'), ['class' => 'col-sm-9']));

$accountInfo->addItem(new \Ease\Html\DtTag(_('Email'), ['class' => 'col-sm-3']));
$accountInfo->addItem(new \Ease\Html\DdTag($currentUser->getDataValue('email') ?: _('(not set)'), ['class' => 'col-sm-9']));

$accountInfo->addItem(new \Ease\Html\DtTag(_('Member since'), ['class' => 'col-sm-3']));
$accountInfo->addItem(new \Ease\Html\DdTag(
    date('F j, Y', strtotime($currentUser->getDataValue($currentUser->createColumn))),
    ['class' => 'col-sm-9'],
));

$accountCard->addItem($accountInfo);
$accountCard->addItem(new \Ease\Html\DivTag(
    new \Ease\TWB5\LinkButton('profile.php', new \Ease\TWB5\Widgets\BsIcon('pencil').' '._('Edit Profile'), 'primary'),
    ['class' => 'text-end mt-3'],
));

$container->addItem($accountCard);

// RBAC privileges section
$rbacCard = new \Ease\TWB5\Card(_('RBAC Privileges & Access'));

try {
    $accessControl = new \MultiFlexi\Security\CompanyAccessControl();
    $accessibleCompanyIds = $accessControl->getCurrentUserAccessibleCompanies();

    // RBAC Status
    $rbacInfo = new \Ease\Html\DlTag(null, ['class' => 'row']);

    $rbacInfo->addItem(new \Ease\Html\DtTag(_('Access Control Status'), ['class' => 'col-sm-3']));
    $statusBadge = \count($accessibleCompanyIds) > 0
        ? new \Ease\TWB5\Badge('✅ '._('Active'), 'success')
        : new \Ease\TWB5\Badge('⚠️ '._('No Access'), 'warning');
    $rbacInfo->addItem(new \Ease\Html\DdTag($statusBadge, ['class' => 'col-sm-9']));

    $rbacInfo->addItem(new \Ease\Html\DtTag(_('Companies Assigned'), ['class' => 'col-sm-3']));
    $rbacInfo->addItem(new \Ease\Html\DdTag(
        (string) \count($accessibleCompanyIds),
        ['class' => 'col-sm-9'],
    ));

    $rbacInfo->addItem(new \Ease\Html\DtTag(_('Current Role'), ['class' => 'col-sm-3']));
    $rbacInfo->addItem(new \Ease\Html\DdTag(_('Viewer (Full Access to Assigned Companies)'), ['class' => 'col-sm-9']));

    $rbacInfo->addItem(new \Ease\Html\DtTag(_('Data Filtering'), ['class' => 'col-sm-3']));
    $filteringStatus = new \Ease\TWB5\Badge('🔒 '._('Enabled'), 'info');
    $rbacInfo->addItem(new \Ease\Html\DdTag($filteringStatus, ['class' => 'col-sm-9']));

    $rbacCard->addItem($rbacInfo);

    // Assigned companies list
    if (\count($accessibleCompanyIds) > 0) {
        $rbacCard->addItem('<hr>');
        $rbacCard->addItem(new \Ease\Html\H5Tag(_('Your Assigned Companies')));

        $companyList = new \Ease\Html\UlTag(null, ['class' => 'list-group list-group-flush']);

        foreach ($accessibleCompanyIds as $companyId) {
            // Load company by passing ID to constructor
            $company = new \MultiFlexi\Company((int) $companyId);

            if ($company && $company->getMyKey()) {
                $listItem = new \Ease\Html\LiTag(null, ['class' => 'list-group-item']);
                $listItem->addItem(new \Ease\Html\ATag(
                    'company.php?id='.$company->getMyKey(),
                    '🏢 '.$company->getRecordName(),
                ));
                $companyList->addItem($listItem);
            }
        }

        $rbacCard->addItem($companyList);
    } else {
        $rbacCard->addItem(new \Ease\TWB5\Alert(
            _('You have not been assigned to any companies. Contact your administrator to request access.'),
            'warning',
        ));
    }
} catch (Exception $e) {
    $rbacCard->addItem(new \Ease\TWB5\Alert(
        _('Error loading RBAC information').': '.$e->getMessage(),
        'danger',
    ));
}

$container->addItem($rbacCard);

WebPage::singleton()->addItem(new PageBottom());
WebPage::singleton()->draw();
