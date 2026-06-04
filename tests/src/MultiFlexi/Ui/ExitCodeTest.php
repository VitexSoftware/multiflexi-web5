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

namespace Test\MultiFlexi\Ui;

use MultiFlexi\Ui\ExitCode;

/**
 * @covers \MultiFlexi\Ui\ExitCode
 *
 * @no-named-arguments
 */
class ExitCodeTest extends \PHPUnit\Framework\TestCase
{
    protected ExitCode $object;

    protected function setUp(): void
    {
        $this->object = new ExitCode(0);
    }

    /**
     * Exit code 0 must map to the success state (regression: a switch() used
     * loose comparison so 0 matched `case null` and was mis-classified).
     */
    public function testStatusSuccessForZero(): void
    {
        self::assertSame('success', ExitCode::status(0));
        self::assertSame('success', ExitCode::status('0'));
    }

    public function testStatusNotFinished(): void
    {
        self::assertSame('info', ExitCode::status(null));
        self::assertSame('info', ExitCode::status(''));
    }

    public function testStatusSecondary(): void
    {
        self::assertSame('secondary', ExitCode::status(-1));
    }

    public function testStatusWarning(): void
    {
        self::assertSame('warning', ExitCode::status(127));
    }

    public function testStatusDanger(): void
    {
        self::assertSame('danger', ExitCode::status(255));
        self::assertSame('danger', ExitCode::status(1));
    }

    /**
     * The widget renders a span carrying the dark semantic class and the code.
     */
    public function testRendersSemanticClassAndValue(): void
    {
        $html = (new ExitCode(0))->__toString();
        self::assertStringContainsString('mf-exit', $html);
        self::assertStringContainsString('mf-exit-success', $html);
        self::assertStringContainsString('0', $html);
    }

    public function testRendersHourglassWhenNotFinished(): void
    {
        $html = (new ExitCode(null))->__toString();
        self::assertStringContainsString('mf-exit-info', $html);
        self::assertStringContainsString('⏳', $html);
    }

    public function testDangerRendersCode(): void
    {
        $html = (new ExitCode(255))->__toString();
        self::assertStringContainsString('mf-exit-danger', $html);
        self::assertStringContainsString('255', $html);
    }
}
