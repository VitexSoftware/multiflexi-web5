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
 * Job exit code indicator.
 *
 * Renders the exit code value using a dark, high-contrast semantic colour
 * (dark green for success, dark blue for secondary, dark red for danger,
 * dark orange for warning, …) so it stays readable on the tinted job rows.
 *
 * @author vitex
 *
 * @no-named-arguments
 */
class ExitCode extends \Ease\Html\SpanTag
{
    /**
     * @param null|int    $exitcode    Exit code value (null = pending)
     * @param array       $properties  HTML tag properties
     * @param null|string $description Localized meaning of the exit code (from app_exit_codes);
     *                                 shown as tooltip when set
     */
    public function __construct($exitcode = null, $properties = [], ?string $description = null)
    {
        $status = self::status($exitcode);
        $label = null === $exitcode ? '⏳' : (string) $exitcode;

        $properties['class'] = trim('mf-exit mf-exit-'.$status.' '.($properties['class'] ?? ''));

        if (!isset($properties['title'])) {
            if (null === $exitcode) {
                $properties['title'] = _('Pending');
            } elseif (null !== $description && '' !== $description) {
                $properties['title'] = $description;
            } else {
                $properties['title'] = $status;
            }
        }

        parent::__construct('&nbsp;'.$label.'&nbsp;', $properties);
    }

    /**
     * Map an exit code to a Bootstrap semantic state name.
     *
     * @param int $exitcode
     *
     * @return string bootstrap state name
     */
    public static function status($exitcode)
    {
        // Strict checks — a switch() would use loose comparison where 0 == null.
        if (null === $exitcode || '' === $exitcode) {
            return 'info'; // not finished yet
        }

        $code = (int) $exitcode;

        if ($code === 0) {
            return 'success';
        }

        if ($code === -1) {
            return 'secondary';
        }

        if ($code === 75) {
            return 'warning'; // EX_TEMPFAIL — credential blocked, deferred
        }

        if ($code === 127) {
            return 'warning';
        }

        return 'danger';
    }
}
