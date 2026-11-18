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
 * Safe "return to" target for multi-page flows (e.g. the activation wizard).
 *
 * Only a relative link to a local *.php page with a simple query string is
 * accepted, so the parameter cannot be abused as an open redirect.
 *
 * @no-named-arguments
 */
final class ReturnUrl
{
    public static function sanitize(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return preg_match('/^[A-Za-z0-9_-]+\.php(\?[A-Za-z0-9_=&%.,-]*)?$/', $url) === 1 ? $url : null;
    }

    /**
     * Current request's validated return target.
     */
    public static function fromRequest(): ?string
    {
        return self::sanitize(WebPage::getRequestValue('return'));
    }
}
