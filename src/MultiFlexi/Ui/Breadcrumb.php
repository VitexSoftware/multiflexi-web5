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

/**
 * Description of Breadcrumb.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class Breadcrumb extends \Ease\TWB5\Breadcrumb
{
    /**
     * App Breadcrumb.
     *
     * Items are resolved in finalize() (called right before draw()) instead
     * of here, so a page controller can call
     * WebPage::singleton()->setBreadcrumb() any time after PageTop is
     * instantiated to replace the default Customer/Company trail with a
     * page-specific one (e.g. Company > Application > RunTemplate).
     *
     * @param array<string, string> $properties
     */
    public function __construct($properties = [])
    {
        $properties['class'] = trim('mf-breadcrumb container-fluid '.($properties['class'] ?? ''));

        parent::__construct([], 'breadcrumb', $properties);
    }

    public function finalize(): void
    {
        $items = WebPage::singleton()->breadcrumbItems;

        if ($items === null) {
            $items = [];

            if (empty($_SESSION['customer'])) {
                $items[_('choose Customer')] = 'customers.php';
            } else {
                $customer = new \MultiFlexi\Customer($_SESSION['customer']);
                $items[_('Customer').': '.$customer->getRecordName()] = $customer->getLink();
            }

            if (empty($_SESSION['company'])) {
                $items[_('choose Company')] = 'companies.php';
            } else {
                $company = new \MultiFlexi\Company($_SESSION['company']);
                $items[_('Company').': '.$company->getRecordName()] = $company->getLink();
            }
        }

        if (empty($items)) {
            // setBreadcrumb([]) means "no breadcrumb is meaningful here"
            // (pre-login pages, root/landing pages, one-off utility scripts) —
            // hide the bar entirely instead of showing an empty one.
            $this->addTagClass('d-none');
        } else {
            $keys = array_keys($items);
            $lastKey = end($keys);

            foreach ($items as $label => $url) {
                $this->addCrumb((string) $label, (string) $url, $label === $lastKey);
            }
        }

        parent::finalize();
    }
}
