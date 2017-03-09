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

/**
 * Class PushEvent.
 */
class PushEvent extends CommonEvent
{
    /**
     * @param Push  $push
     * @param bool $isNew
     */
    public function __construct(Push $push, $isNew = false)
    {
        $this->entity = $push;
        $this->isNew  = $isNew;
    }

    /**
     * Returns the Push entity.
     *
     * @return Push
     */
    public function getPush()
    {
        return $this->entity;
    }

    /**
     * Sets the Push entity.
     *
     * @param Push $push
     */
    public function setPush(Push $push)
    {
        $this->entity = $push;
    }
}
