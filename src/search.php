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
use Ease\Html\H5Tag;
use Ease\Html\PTag;
use Ease\Html\SmallTag;
use Ease\Html\SpanTag;

require_once './init.php';

WebPage::singleton()->onlyForLogged();

$searchTerm = \Ease\WebPage::getRequestValue('search');
$what = \Ease\WebPage::getRequestValue('what');

if (str_starts_with($searchTerm, '#')) {
    $searchTerm = substr($searchTerm, 1);
}

$foundItems = [];
$resultCards = [];

$categoryMeta = [
    'RunTemplate' => ['icon' => '⚗️', 'badge' => 'primary', 'label' => _('RunTemplate')],
    'Application' => ['icon' => '🧩', 'badge' => 'success', 'label' => _('Application')],
    'Company' => ['icon' => '🏢', 'badge' => 'info', 'label' => _('Company')],
    'Job' => ['icon' => '🏁', 'badge' => 'warning', 'label' => _('Job')],
    'Credential' => ['icon' => '🔐', 'badge' => 'danger', 'label' => _('Credential')],
];

function addResult(array &$foundItems, array &$resultCards, string $url, string $category, string $title, string $matchField, string $matchValue): void
{
    global $categoryMeta;
    $foundItems[] = $url;
    $meta = $categoryMeta[$category] ?? ['icon' => '🔍', 'badge' => 'secondary', 'label' => $category];
    $resultCards[] = [
        'url' => $url,
        'icon' => $meta['icon'],
        'badge' => $meta['badge'],
        'category' => $meta['label'],
        'title' => $title,
        'field' => $matchField,
        'value' => $matchValue,
    ];
}

if ($what === 'all' || $what === 'RunTemplate') {
    $runTemplater = new \MultiFlexi\RunTemplate();
    $runtemplatesFound = $runTemplater->listingQuery()->where('name LIKE "%'.$searchTerm.'%"')->whereOr(['id' => $searchTerm]);

    if ($runtemplatesFound->count()) {
        foreach ($runtemplatesFound as $runTemplate) {
            addResult($foundItems, $resultCards, 'runtemplate.php?id='.$runTemplate['id'], 'RunTemplate', $runTemplate['name'], 'name', $runTemplate['name']);
        }
    }
}

if ($what === 'all' || $what === 'Application') {
    $apper = new \MultiFlexi\Application();
    $appsFound = $apper->listingQuery()->where('name LIKE "%'.$searchTerm.'%"')->whereOr('executable LIKE "%'.$searchTerm.'%"')->whereOr('uuid', $searchTerm)->whereOr(['id' => $searchTerm]);

    if ($appsFound->count()) {
        foreach ($appsFound as $app) {
            if (str_contains(strtolower($app['name']), strtolower($searchTerm))) {
                addResult($foundItems, $resultCards, 'app.php?id='.$app['id'], 'Application', $app['name'], 'name', $app['name']);
            } elseif (str_contains(strtolower($app['executable']), strtolower($searchTerm))) {
                addResult($foundItems, $resultCards, 'app.php?id='.$app['id'], 'Application', $app['name'], 'executable', $app['executable']);
            } elseif (str_contains(strtolower($app['uuid']), strtolower($searchTerm))) {
                addResult($foundItems, $resultCards, 'app.php?id='.$app['id'], 'Application', $app['name'], 'uuid', $app['uuid']);
            } elseif ($app['id'] === $searchTerm) {
                addResult($foundItems, $resultCards, 'app.php?id='.$app['id'], 'Application', $app['name'], 'id', (string) $app['id']);
            }
        }
    }
}

if ($what === 'all' || $what === 'Company') {
    $companer = new \MultiFlexi\Company();
    $companyFound = $companer->listingQuery()->where('name LIKE "%'.$searchTerm.'%"')->whereOr(['id' => $searchTerm]);

    if ($companyFound->count()) {
        foreach ($companyFound as $company) {
            addResult($foundItems, $resultCards, 'company.php?id='.$company['id'], 'Company', $company['name'], 'name', $company['name']);
        }
    }
}

if ($what === 'all' || $what === 'Job') {
    $jobber = new \MultiFlexi\Job();
    $jobsFound = $jobber->listingQuery()->where('stdout LIKE "%'.$searchTerm.'%"')->whereOr('stderr LIKE "%'.$searchTerm.'%"')->whereOr(['id' => $searchTerm]);

    if ($jobsFound->count()) {
        foreach ($jobsFound as $job) {
            if (str_contains(strtolower($job['stdout']), strtolower($searchTerm))) {
                addResult($foundItems, $resultCards, 'job.php?id='.$job['id'], 'Job', _('Job').' #'.$job['id'], 'stdout', mb_strimwidth((string) $job['stdout'], 0, 120, '…'));
            } elseif (str_contains(strtolower($job['stderr']), strtolower($searchTerm))) {
                addResult($foundItems, $resultCards, 'job.php?id='.$job['id'], 'Job', _('Job').' #'.$job['id'], 'stderr', mb_strimwidth((string) $job['stderr'], 0, 120, '…'));
            } elseif ($job['id'] === $searchTerm) {
                addResult($foundItems, $resultCards, 'job.php?id='.$job['id'], 'Job', _('Job').' #'.$job['id'], 'id', (string) $job['id']);
            }
        }
    }
}

if ($what === 'all' || $what === 'Credential') {
    $credentor = new \MultiFlexi\Credential();
    $credentialsFound = $credentor->listingQuery()->where('name LIKE "%'.$searchTerm.'%"')->whereOr(['id' => $searchTerm]);

    if ($credentialsFound->count()) {
        foreach ($credentialsFound as $credential) {
            addResult($foundItems, $resultCards, 'credential.php?id='.$credential['id'], 'Credential', $credential['name'], 'name', $credential['name']);
        }
    }
}

// Redirect if only one result is found
if (\count($foundItems) === 1) {
    header('Location: '.$foundItems[0]);

    exit;
}

WebPage::singleton()->addItem(new PageTop(_('Search Results')));

$container = WebPage::singleton()->container;

// Header with search summary
$header = new DivTag(null, ['class' => 'mb-4']);
$header->addItem(new \Ease\Html\H2Tag(
    sprintf(_('Search Results for "%s"'), htmlspecialchars($searchTerm)),
    ['class' => 'mb-1'],
));
$header->addItem(new PTag(
    sprintf(ngettext('%d result found', '%d results found', \count($resultCards)), \count($resultCards)).
    ($what !== 'all' ? ' '.sprintf(_('in %s'), $categoryMeta[$what]['label'] ?? $what) : ''),
    ['class' => 'text-muted'],
));
$container->addItem($header);

if (empty($resultCards)) {
    $emptyDiv = new DivTag(null, ['class' => 'text-center py-5']);
    $emptyDiv->addItem(new PTag('🔍', ['style' => 'font-size: 3rem;']));
    $emptyDiv->addItem(new PTag(_('No results found. Try a different search term or category.'), ['class' => 'text-muted']));
    $container->addItem($emptyDiv);
} else {
    $listGroup = new DivTag(null, ['class' => 'list-group']);

    foreach ($resultCards as $card) {
        $item = new ATag($card['url'], null, [
            'class' => 'list-group-item list-group-item-action d-flex align-items-start py-3',
        ]);

        // Icon
        $item->addItem(new SpanTag($card['icon'], ['class' => 'me-3', 'style' => 'font-size: 1.5rem; line-height: 1;']));

        // Content
        $content = new DivTag(null, ['class' => 'flex-grow-1']);
        $titleRow = new DivTag(null, ['class' => 'd-flex justify-content-between align-items-center']);
        $titleRow->addItem(new H5Tag($card['title'], ['class' => 'mb-1']));
        $titleRow->addItem(new SpanTag($card['category'], ['class' => 'badge bg-'.$card['badge']]));
        $content->addItem($titleRow);
        $content->addItem(new SmallTag(
            $card['field'].': '.htmlspecialchars($card['value']),
            ['class' => 'text-muted'],
        ));

        $item->addItem($content);
        $listGroup->addItem($item);
    }

    $container->addItem($listGroup);
}

WebPage::singleton()->addItem(new PageBottom());

WebPage::singleton()->draw();
