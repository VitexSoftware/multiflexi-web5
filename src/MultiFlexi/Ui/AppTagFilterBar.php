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

use MultiFlexi\AppTags;

/**
 * Tag picker narrowing a listing to applications having all selected tags.
 *
 * The selection lives in the page URL (?apptags=A,B), so a filtered view can be
 * bookmarked or shared; other query parameters are kept when it changes.
 *
 * @no-named-arguments
 */
class AppTagFilterBar extends \Ease\Html\DivTag
{
    /**
     * @param array<int, string>   $selected   currently selected tags
     * @param array<string, mixed> $properties
     */
    public function __construct(array $selected, array $properties = [])
    {
        parent::__construct(null, $properties + ['class' => 'mf-apptag-filter d-flex flex-wrap align-items-center gap-2 mb-3']);

        $tags = AppTags::all();

        if ($tags === []) {
            return;
        }

        WebPage::singleton()->includeJavaScript('js/selectize.min.js');
        WebPage::singleton()->includeCss('css/selectize.bootstrap5.css');

        $options = array_map(static fn (string $tag): array => ['id' => $tag, 'name' => $tag], $tags);

        $this->addItem(new \Ease\Html\SpanTag('🏷️ '._('Application tags'), ['class' => 'fw-semibold']));
        $this->addItem(new \Ease\Html\DivTag(
            new PillBox(AppTags::PARAM, $options, $selected, ['class' => 'form-control', 'placeholder' => _('All applications – pick tags to narrow down')]),
            ['class' => 'flex-grow-1', 'style' => 'min-width: 16rem;'],
        ));

        if ($selected !== []) {
            $this->addItem(new \Ease\Html\ButtonTag(_('Clear'), [
                'type' => 'button',
                'class' => 'btn btn-outline-secondary btn-sm',
                'id' => 'mf-apptag-clear',
            ]));
        }

        $param = json_encode(AppTags::PARAM);
        $pillBoxId = json_encode(AppTags::PARAM.'pillBox');

        $this->addJavaScript(<<<JS
(function () {
    function applyTags(tags) {
        var url = new URL(window.location.href);
        if (tags.length) {
            url.searchParams.set({$param}, tags.join(','));
        } else {
            url.searchParams.delete({$param});
        }
        window.location.href = url.toString();
    }

    document.addEventListener('click', function (e) {
        if (e.target && e.target.id === 'mf-apptag-clear') {
            applyTags([]);
        }
    });

    // PillBox turns the input into selectize shortly after DOM ready
    (function waitForSelectize() {
        var el = document.getElementById({$pillBoxId});
        if (!el) {
            return;
        }
        if (!el.selectize) {
            setTimeout(waitForSelectize, 150);
            return;
        }
        el.selectize.on('change', function (value) {
            applyTags(Array.isArray(value) ? value : (value ? String(value).split(',') : []));
        });
    })();
})();
JS);
    }
}
