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
 * Job history spiral: one pixel per job.
 *
 * The spiral is a timeline running from the centre outwards: the oldest job
 * shown sits in the centre and the newest one at the outer end, so as new jobs
 * arrive the oldest drop out in the middle. Jobs are passed newest first (as
 * fetched with ORDER BY id DESC); only as many as fit the spiral are drawn.
 *
 * @no-named-arguments
 */
class JobGraph
{
    /**
     * Background colour of the image (Nocturne neutral-900).
     */
    private const GROUND = [41, 43, 49];

    /**
     * How much of its own colour the oldest shown job keeps (0 = invisible).
     */
    private const FAR_VISIBILITY = 0.1;

    /**
     * Steepness of the fade into the distance: above 1 the past recedes fast,
     * so most of the older half already blends into the background.
     */
    private const DEPTH_CURVE = 1.8;

    private $width;
    private $height;
    private $todaysJobs;
    private $image;
    private $successCount = 0;
    private $failureCount = 0;
    private $noExecutableCount = 0;
    private $exceptionCount = 0;
    private $waitingCount = 0;

    public function __construct(int $width, int $height, array $todaysJobs)
    {
        $this->width = $width;
        $this->height = $height;
        $this->todaysJobs = $todaysJobs;
    }

    public function calcultateStats(): void
    {
        foreach ($this->todaysJobs as $job) {
            if ($job['exitcode'] === 0) {
                ++$this->successCount;
            } elseif ($job['exitcode'] === 127) {
                ++$this->noExecutableCount;
            } elseif ($job['exitcode'] === 255) {
                ++$this->exceptionCount;
            } elseif (null === $job['exitcode']) {
                ++$this->waitingCount;
            } else {
                ++$this->failureCount;
            }
        }
    }

    /**
     * Number of pixels the spiral covers before it leaves a width × height image.
     */
    public static function spiralCapacity(int $width, int $height): int
    {
        $x = (int) ($width / 2);
        $y = (int) ($height / 2);
        $dx = 1;
        $dy = 0;
        $segmentLength = 1;
        $segmentPassed = 0;
        $capacity = 0;

        while ($x >= 0 && $x < $width && $y >= 0 && $y < $height) {
            ++$capacity;
            $x += $dx;
            $y += $dy;

            if (++$segmentPassed === $segmentLength) {
                $segmentPassed = 0;
                [$dx, $dy] = [-$dy, $dx];

                if ($dy === 0) {
                    ++$segmentLength;
                }
            }
        }

        return $capacity;
    }

    public function generateImage(): void
    {
        $this->image = imagecreatetruecolor($this->width, $this->height);

        // Nocturne palette (same RGB as the dashboard legend); ground = neutral-900
        $ground = imagecolorallocate($this->image, ...self::GROUND);
        imagefill($this->image, 0, 0, $ground);

        $palette = [
            'success' => [110, 183, 135],
            'failure' => [214, 104, 98],
            'noexec' => [214, 190, 110],
            'exception' => [145, 132, 217],
            'waiting' => [147, 151, 171],
        ];

        $centerX = $this->width / 2;
        $centerY = $this->height / 2;
        $x = (int) $centerX;
        $y = (int) $centerY;
        $dx = 1;
        $dy = 0;
        $segment_length = 1;
        $segment_passed = 0;

        // Keep the newest jobs that fit and draw them oldest first: the oldest
        // shown job lands in the centre, the newest at the end of the spiral
        $jobs = array_reverse(\array_slice(array_values($this->todaysJobs), 0, self::spiralCapacity($this->width, $this->height)));
        $totalJobs = max(1, \count($jobs));

        foreach ($jobs as $index => $job) {
            $visibility = self::visibility($index, $totalJobs);

            if ($job['exitcode'] === 0) {
                $key = 'success';
                ++$this->successCount;
            } elseif ($job['exitcode'] === 127) {
                $key = 'noexec';
                ++$this->noExecutableCount;
            } elseif ($job['exitcode'] === 255) {
                $key = 'exception';
                ++$this->exceptionCount;
            } elseif (null === $job['exitcode']) {
                $key = 'waiting';
                ++$this->waitingCount;
            } else {
                $key = 'failure';
                ++$this->failureCount;
            }

            $rgb = $palette[$key];
            $color = imagecolorallocate($this->image, ...self::towardsGround($rgb, $visibility));

            imagesetpixel($this->image, $x, $y, $color);

            $x += $dx;
            $y += $dy;
            ++$segment_passed;

            if ($segment_passed === $segment_length) {
                $segment_passed = 0;

                // Change direction
                $temp = $dx;
                $dx = -$dy;
                $dy = $temp;

                // Increase segment length after two segments
                if ($dy === 0) {
                    ++$segment_length;
                }
            }

            // Stop if we reach the edge of the image
            if ($x < 0 || $x >= $this->width || $y < 0 || $y >= $this->height) {
                break;
            }
        }
    }

    /**
     * Perspective of the timeline: the newest job (outer end) is fully visible,
     * going back in time towards the centre jobs recede into the distance.
     *
     * @param int $index position in drawing order, 0 = oldest shown job
     */
    public static function visibility(int $index, int $total): float
    {
        if ($total <= 1) {
            return 1.0;
        }

        $recency = $index / ($total - 1); // 0 = oldest, 1 = newest

        return self::FAR_VISIBILITY + (1 - self::FAR_VISIBILITY) * $recency ** self::DEPTH_CURVE;
    }

    /**
     * Blend a colour into the background by the given visibility (1 = own colour).
     *
     * @param array{0: int, 1: int, 2: int} $rgb
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public static function towardsGround(array $rgb, float $visibility): array
    {
        $blended = [];

        foreach ($rgb as $channel => $value) {
            $blended[$channel] = (int) round(self::GROUND[$channel] + ($value - self::GROUND[$channel]) * $visibility);
        }

        return $blended;
    }

    public function getBase64Image(): string
    {
        // Encode the image data as base64
        return base64_encode($this->getImage());
    }

    public function getImage(): string
    {
        // Capture the image output
        ob_start();
        imagepng($this->image);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($this->image);

        return $imageData;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    public function getNoExecutableCount(): int
    {
        return $this->noExecutableCount;
    }

    public function getExceptionCount(): int
    {
        return $this->exceptionCount;
    }

    public function getWaitingCount(): int
    {
        return $this->waitingCount;
    }

    public function getTotalJobs(): int
    {
        return \count($this->todaysJobs);
    }

    public function getImageTag($companyId)
    {
        return new \Ease\Html\ImgTag(
            'jobgraph.php?width='.$this->width.'&height='.$this->height.'&company_id='.$companyId,
            _('Job Success/Failure Graph'),
            ['width' => $this->width, 'height' => $this->height],
        );
    }
}
