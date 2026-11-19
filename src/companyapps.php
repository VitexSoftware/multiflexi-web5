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

use Ease\Html\CheckboxTag;
use Ease\Html\DivTag;
use Ease\Html\H2Tag;
use Ease\Html\InputHiddenTag;
use Ease\Html\LabelTag;
use Ease\Html\PTag;
use Ease\Html\SmallTag;
use Ease\TWB5\Card;
use Ease\TWB5\Row;
use Ease\TWB5\SubmitButton;

require_once './init.php';

WebPage::singleton()->onlyForLogged();

$companer = new \MultiFlexi\Company(\Ease\WebPage::getRequestValue('company_id', 'int'));

if (null === $companer->getMyKey()) {
    WebPage::singleton()->redirect('companies.php');
}

// Enforce access control
\MultiFlexi\Security\CompanyAccessControl::enforceCompanyAccess(
    (int) $companer->getMyKey(),
    sprintf(_('You do not have access to company "%s"'), $companer->getRecordName()),
);

$companyApp = new \MultiFlexi\CompanyApp($companer);

// Handle form submission
if (\Ease\WebPage::isPosted()) {
    $selectedApps = \Ease\WebPage::getRequestValue('apps', 'array');
    $appIds = $selectedApps ? array_map('intval', $selectedApps) : [];
    $companyApp->assignApps($appIds);
    WebPage::singleton()->addStatusMessage(_('Applications updated successfully'), 'success');
}

WebPage::singleton()->setBreadcrumb([
    _('Company').': '.$companer->getRecordName() => $companer->getLink(),
    _('Applications used by Company') => '',
]);
WebPage::singleton()->addItem(new PageTop(_('Applications used by Company')));

// Get all applications and currently assigned ones with localized names and descriptions
$apper = new \MultiFlexi\Application();
$currentLang = substr(\Ease\Locale::$localeUsed ?? 'en_US', 0, 2);

$allApps = $apper->getFluentPDO()
    ->from('apps')
    ->select('apps.id, apps.name, apps.description, apps.uuid, apps.image, apps.tags, apps.executable')
    ->select('COALESCE(app_translations.name, apps.name) AS localized_name')
    ->select('COALESCE(app_translations.description, apps.description) AS localized_description')
    ->leftJoin('app_translations ON app_translations.app_id = apps.id AND app_translations.lang = ?', $currentLang)
    ->orderBy('COALESCE(app_translations.name, apps.name)')
    ->fetchAll();

$assignedRaw = $companyApp->getAssigned()->fetchAll('app_id');
$assigned = empty($assignedRaw) ? [] : array_keys($assignedRaw);

// Collect all unique tags from applications
$allTags = [];

foreach ($allApps as $app) {
    if (!empty($app['tags'])) {
        $tags = explode(',', $app['tags']);

        foreach ($tags as $tag) {
            $tag = trim($tag);

            if (!empty($tag) && !isset($allTags[$tag])) {
                $allTags[$tag] = ['id' => $tag, 'name' => $tag];
            }
        }
    }
}

// Sort tags alphabetically
ksort($allTags);
$allTags = array_values($allTags);

// Create container for filter controls (outside form to avoid CSRF issues)
$filterContainer = new DivTag();
$filterContainer->addItem(new H2Tag(sprintf(_('Choose Applications for %s'), $companer->getRecordName())));

// Include Selectize assets for tag filtering
if (!empty($allTags)) {
    WebPage::singleton()->includeJavaScript('js/selectize.min.js');
    WebPage::singleton()->includeCss('css/selectize.bootstrap5.css');
}

// Add tag filter using PillBox if tags are available
if (!empty($allTags)) {
    $filterContainer->addItem(new \Ease\Html\H4Tag(_('Filter by Tags')));
    $filterContainer->addItem(new PTag(_('Pick one or more tags to show only applications having all of them. With no tag picked, all applications are shown.')));

    $filterRow = new Row();

    // Nothing picked = no filter; picking tags narrows the list down
    $tagFilter = new PillBox('tag_filter', $allTags, [], ['class' => 'form-control mb-3', 'placeholder' => _('Select tags to filter applications...')]);
    $filterRow->addColumn(10, $tagFilter);

    // Add reset filter button
    $resetButton = new \Ease\Html\ButtonTag(_('Reset Filter'), [
        'class' => 'btn btn-outline-secondary mb-3',
        'type' => 'button',
        'id' => 'reset-tag-filter',
        'title' => _('Clear the tag filter to show all applications'),
    ]);
    $filterRow->addColumn(2, $resetButton);

    $filterContainer->addItem($filterRow);
}

// Add search box
$searchBox = new \Ease\Html\InputSearchTag('app_search', '', ['id' => 'app_search', 'placeholder' => _('Search applications...'), 'class' => 'form-control form-control-lg mb-3']);
$filterContainer->addItem($searchBox);

// Show count of selected apps
$countDiv = new DivTag(
    new SmallTag(['<strong id="selected-count">'.\count($assigned).'</strong> ', _('applications selected')], ['class' => 'text-muted']),
    ['class' => 'mb-3'],
);
$filterContainer->addItem($countDiv);

// Create form with card grid
$addAppForm = new SecureForm();
$addAppForm->addItem(new InputHiddenTag('company_id', $companer->getMyKey()));

// Create cards grid
$cardsRow = new Row();

foreach ($allApps as $app) {
    $isAssigned = \in_array($app['id'], $assigned, true);

    // Add data-tags attribute for JavaScript filtering
    $tagsList = !empty($app['tags']) ? explode(',', $app['tags']) : [];
    $tagsDataAttr = implode(',', array_map('trim', $tagsList));

    // Everything the search box matches against: original and localized name and
    // description, the executable (package command, e.g. abraflexi-daydigest) and tags
    $searchText = mb_strtolower(implode(' ', [
        $app['name'],
        $app['localized_name'] ?? '',
        $app['description'] ?? '',
        $app['localized_description'] ?? '',
        basename((string) ($app['executable'] ?? '')),
        $tagsDataAttr,
    ]));

    $cardDiv = new DivTag(null, ['class' => 'col-md-4 col-lg-3 mb-3 app-card-wrapper', 'data-search' => $searchText, 'data-tags' => $tagsDataAttr]);

    $card = new Card(
        null,
        ['class' => 'h-100 app-card'.($isAssigned ? ' app-card-selected' : '')],
    );

    $cardBody = new DivTag(null, ['class' => 'card-body']);

    // Checkbox at top
    $checkboxDiv = new DivTag(null, ['class' => 'form-check']);
    $checkbox = new CheckboxTag('apps[]', $isAssigned, (string) $app['id'], ['class' => 'form-check-input app-checkbox', 'id' => 'app_'.$app['id']]);
    $checkboxLabel = new LabelTag('app_'.$app['id'], '', ['class' => 'form-check-label']);
    $checkboxDiv->addItem($checkbox);
    $checkboxDiv->addItem($checkboxLabel);
    $cardBody->addItem($checkboxDiv);

    // App logo centered
    $logoDiv = new DivTag(null, ['class' => 'text-center my-3']);
    $appImage = empty($app['image']) ? 'appimage.php?uuid='.$app['uuid'] : $app['image'];
    $displayName = $app['localized_name'] ?? $app['name'];
    $logoDiv->addItem(new \Ease\Html\ImgTag($appImage, $displayName, ['style' => 'max-width: 80px; max-height: 80px;']));
    $cardBody->addItem($logoDiv);

    // App name (localized) with link to detail
    $nameWithLink = new \Ease\Html\H5Tag(null, ['class' => 'card-title text-center']);
    $nameWithLink->addItem(new \Ease\Html\ATag('app.php?id='.$app['id'], $displayName, ['class' => 'text-decoration-none app-detail-link', 'title' => _('View application details'), 'onclick' => 'event.stopPropagation();']));
    $cardBody->addItem($nameWithLink);

    // App description (localized)
    $displayDescription = $app['localized_description'] ?? $app['description'] ?? '';

    if (!empty($displayDescription)) {
        $desc = mb_strlen($displayDescription) > 100 ? mb_substr($displayDescription, 0, 97).'...' : $displayDescription;
        $cardBody->addItem(new PTag(new SmallTag($desc, ['class' => 'text-muted']), ['class' => 'card-text text-center']));
    }

    // Show tags as badges
    if (!empty($app['tags'])) {
        $tagBadges = new DivTag(null, ['class' => 'mb-2 tag-badges text-center']);

        foreach ($tagsList as $tag) {
            $tag = trim($tag);

            if (!empty($tag)) {
                $badge = new \Ease\TWB5\Badge($tag, 'secondary', ['class' => 'me-1 mb-1 tag-badge']);
                $tagBadges->addItem($badge);
            }
        }

        $cardBody->addItem($tagBadges);
    }

    $card->addItem($cardBody);
    $cardDiv->addItem($card);
    $cardsRow->addItem($cardDiv);
}

$addAppForm->addItem($cardsRow);

// Fixed submit button
$addAppForm->addItem(new \Ease\Html\HrTag());
$addAppForm->addItem(new SubmitButton('🍏 '._('Apply Changes'), 'success btn-lg w-100', ['style' => 'position: sticky; bottom: 10px; z-index: 100;']));

// Create a container with filters and form
$contentContainer = new DivTag();
$contentContainer->addItem($filterContainer);
$contentContainer->addItem($addAppForm);

WebPage::singleton()->container->addItem(new CompanyPanel($companer, $contentContainer));

// Add CSS
WebPage::singleton()->addCSS(<<<'CSS'
.app-card {
    cursor: pointer;
    transition: all 0.2s;
}
.app-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
.app-card-wrapper[data-hidden="true"],
.app-card-wrapper[data-tag-hidden="true"] {
    display: none;
}
.tag-badge {
    cursor: pointer;
}
.app-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
}
.tag-badge {
    transition: all 0.3s ease-in-out;
    font-size: 0.75rem;
}
.app-detail-link {
    color: inherit;
}
.app-card-selected {
    border-color: var(--color-accent-500, var(--bs-primary));
    background-color: color-mix(in srgb, var(--color-accent, var(--bs-primary)) 16%, var(--bs-card-bg, transparent));
    box-shadow: 0 0 0 1px var(--color-accent-500, var(--bs-primary));
}
.tag-badge.tag-badge-selected {
    font-weight: bold;
    background-color: var(--color-accent-600, var(--bs-primary)) !important;
    color: var(--color-neutral-100, #fff) !important;
    box-shadow: 0 0 0 1px var(--color-accent-300, var(--bs-primary));
}
.app-detail-link:hover {
    color: var(--bs-link-hover-color);
    text-decoration: underline !important;
}
CSS);

// Add JavaScript for interactivity
WebPage::singleton()->addJavaScript(<<<'JS'
$(document).ready(function() {
    // Click on card to toggle checkbox
    $('.app-card').click(function(e) {
        if (!$(e.target).is('input[type="checkbox"]')) {
            var checkbox = $(this).find('.app-checkbox');
            checkbox.prop('checked', !checkbox.prop('checked')).trigger('change');
        }
    });

    // Update card styling when checkbox changes
    $('.app-checkbox').change(function() {
        var card = $(this).closest('.app-card');
        if ($(this).is(':checked')) {
            card.addClass('app-card-selected');
        } else {
            card.removeClass('app-card-selected');
        }
        updateCount();
    });

    // Search functionality
    // Text search among the applications left by the tag filter: the two filters
    // hide cards through separate attributes (data-hidden / data-tag-hidden), so a
    // card is shown only when it passes both. Every word typed must match.
    function filterApplicationsBySearch() {
        var words = ($('#app_search').val() || '').toLowerCase().split(/\s+/).filter(function(word) {
            return word.length > 0;
        });

        $('.app-card-wrapper').each(function() {
            var haystack = $(this).attr('data-search') || '';
            var matches = words.every(function(word) {
                return haystack.indexOf(word) !== -1;
            });
            $(this).attr('data-hidden', matches ? 'false' : 'true');
        });
    }

    // "input" also catches paste and the clear (×) button of the search field
    $('#app_search').on('input', filterApplicationsBySearch);

    // Tag filtering (selection remembered in localStorage)
    const STORAGE_KEY = 'multiflexi_companyapps_tag_filter';

    var tagFilterSelectize = null;
    var allAvailableTags = [];

    function saveTagSelection(selectedTags) {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify(selectedTags));
        } catch (e) {
            // storage unavailable (private window etc.) - the filter still works
        }
    }

    function loadTagSelection() {
        try {
            var parsed = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            // Older versions stored the string "all_tags_selected" (= no filter)
            return Array.isArray(parsed) ? parsed.filter(function (tag) { return allAvailableTags.includes(tag); }) : [];
        } catch (e) {
            return [];
        }
    }

    // Show only applications having every selected tag; no tag selected = show all.
    // Hidden cards keep their checkbox state, so filtering never changes which
    // applications get assigned when the form is submitted.
    function filterApplicationsByTags(selectedTags) {
        var tagsArray = Array.isArray(selectedTags) ? selectedTags : (selectedTags ? String(selectedTags).split(',') : []);

        $('.app-card-wrapper').each(function() {
            var cardTagsArray = ($(this).attr('data-tags') || '').split(',').map(function(tag) {
                return tag.trim();
            }).filter(function(tag) {
                return tag.length > 0;
            });

            var shouldShow = tagsArray.every(function(tag) {
                return cardTagsArray.indexOf(tag) !== -1;
            });

            $(this).attr('data-tag-hidden', shouldShow ? 'false' : 'true');
        });

        highlightSelectedTags(tagsArray);
        updateCount();
    }

    // Function to highlight selected tags on application cards
    function highlightSelectedTags(selectedTags) {
        $('.tag-badge').each(function() {
            var tagText = $(this).text().trim();

            // Colours come from .tag-badge-selected so they follow the dark theme
            $(this).toggleClass('tag-badge-selected', selectedTags.includes(tagText));
        });
    }

    // Clicking a tag badge on a card adds that tag to the filter
    $(document).on('click', '.tag-badge', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (tagFilterSelectize) {
            tagFilterSelectize.addItem($(this).text().trim());
        }
    });

    // Initialize tag filter selectize (PillBox creates it shortly after DOM ready)
    (function initTagFilter() {
        var element = $('#tag_filterpillBox');
        if (element.length === 0) {
            return;
        }
        if (!element[0].selectize) {
            setTimeout(initTagFilter, 200);
            return;
        }

        tagFilterSelectize = element[0].selectize;
        allAvailableTags = Object.keys(tagFilterSelectize.options);

        var savedSelection = loadTagSelection();
        tagFilterSelectize.setValue(savedSelection, true);
        filterApplicationsByTags(savedSelection);

        tagFilterSelectize.on('change', function(value) {
            var selectedTags = Array.isArray(value) ? value : (value ? value.split(',') : []);
            saveTagSelection(selectedTags);
            filterApplicationsByTags(selectedTags);
        });

        $('#reset-tag-filter').on('click', function() {
            tagFilterSelectize.clear();
        });
    })();

    // Update selected count
    function updateCount() {
        var count = $('.app-checkbox:checked').length;
        $('#selected-count').text(count);
    }
});
JS);

WebPage::singleton()->addItem(new PageBottom());
WebPage::singleton()->draw();
