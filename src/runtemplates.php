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

WebPage::singleton()->addItem(new PageTop(_('Runtemplates')));

$buttonRow = new \Ease\TWB5\Row();
$buttonRow->addColumn(12, [
    new \Ease\TWB5\LinkButton('activation-wizard.php', '🧙 '._('Activation Wizard'), 'success btn-lg'),
    '&nbsp;',
    new \Ease\Html\SmallTag(_('Use the wizard to easily activate an application in a company')),
]);
WebPage::singleton()->container->addItem($buttonRow);
WebPage::singleton()->container->addItem(new \Ease\Html\HrTag());

WebPage::singleton()->container->addItem(new DBDataTable(new \MultiFlexi\FilteredRunTemplateLister()));

WebPage::singleton()->addCSS(<<<'CSS'
/* Runtemplates listing: improve readability/contrast for app & company pills */
#FilteredRunTemplateLister tbody a.btn.btn-inverse {
    display: inline-flex;
    align-items: center;
    gap: 0.55rem;
    height: auto !important;
    min-height: 2.5rem;
    padding: 0.35rem 0.65rem;
    border-radius: 0.7rem;
    border: 1px solid #cbd5e1;
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    color: #0f172a !important;
    text-decoration: none;
    font-weight: 600;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    transition: background-color 120ms ease, border-color 120ms ease, box-shadow 120ms ease;
}

#FilteredRunTemplateLister tbody a.btn.btn-inverse:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
    color: #0f172a !important;
    box-shadow: 0 4px 10px rgba(15, 23, 42, 0.10);
}

#FilteredRunTemplateLister tbody a.btn.btn-inverse:focus-visible {
    outline: 2px solid #0284c7;
    outline-offset: 2px;
}

#FilteredRunTemplateLister tbody a.btn.btn-inverse img {
    height: 2rem !important;
    width: 2rem;
    max-height: 2rem !important;
    max-width: 2rem;
    object-fit: cover;
    border-radius: 0.4rem;
    flex: 0 0 2rem;
}
CSS);

WebPage::singleton()->addItem(new PageBottom('runtemplates'));

WebPage::singleton()->draw();
