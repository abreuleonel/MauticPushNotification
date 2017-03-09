<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\PushBundle\Event;

use Mautic\CoreBundle\Event\CommonEvent;
use MauticPlugin\PushBundle\Entity\Push;
use MauticPlugin\PushBundle\Entity\Stat;

/**
 * Class PushClickEvent.
 */
class PushClickEvent extends CommonEvent
{
    private $request;

    private $push;

    /**
     * @param Stat $stat
     * @param $request
     */
    public function __construct(Stat $stat, $request)
    {
        $this->entity  = $stat;
        $this->push     = $stat->getPush();
        $this->request = $request;
    }

    /**
     * Returns the Push entity.
     *
     * @return Push
     */
    public function getPush()
    {
        return $this->push;
    }

    /**
     * Get push request.
     *
     * @return string
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return Stat
     */
    public function getStat()
    {
        return $this->entity;
    }
}
