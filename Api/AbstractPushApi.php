<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\PushBundle\Api;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PageBundle\Model\TrackableModel;

abstract class AbstractPushApi
{
    /**
     * @var MauticFactory
     */
    protected $pageTrackableModel;

    /**
     * AbstractPushApi constructor.
     *
     * @param TrackableModel $pageTrackableModel
     */
    public function __construct(TrackableModel $pageTrackableModel)
    {
        $this->pageTrackableModel = $pageTrackableModel;
    }

    /**
     * @param string $number
     * @param string $content
     *
     * @return mixed
     */
    abstract public function sendPush($gcm_id, $content, $title = '', $subtitle = '', $tickerText = '', $pageto = '');

    /**
     * Convert a non-tracked url to a tracked url.
     *
     * @param string $url
     * @param array  $clickthrough
     *
     * @return string
     */
    public function convertToTrackedUrl($url, array $clickthrough = [])
    {
        /* @var \Mautic\PageBundle\Entity\Redirect $redirect */
        $trackable = $this->pageTrackableModel->getTrackableByUrl($url, 'push', $clickthrough['push']);

        return $this->pageTrackableModel->generateTrackableUrl($trackable, $clickthrough, true);
    }
}
