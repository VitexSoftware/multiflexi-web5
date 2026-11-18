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

namespace MultiFlexi;

/**
 * Application with localization support.
 *
 * This class extends the base Application class with translation capabilities
 */
class LocalizedApplication extends Application
{
    use ApplicationTranslation;

    /**
     * Localized record name, falling back to the raw name when no translation exists
     * (empty string for an application that has no record yet).
     */
    public function getRecordName(): string
    {
        // parent::getRecordName() is null for a not yet saved application (import / new)
        return $this->getLocalizedName() ?? (string) parent::getRecordName();
    }
}
