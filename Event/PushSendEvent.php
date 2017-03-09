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
use Mautic\LeadBundle\Entity\Lead;

/**
 * Class PushSendEvent.
 */
class PushSendEvent extends CommonEvent
{
    /**
     * @var int
     */
    protected $pushId;

    /**
     * @var string
     */
    protected $content;

    /**
     * @var Lead
     */
    protected $lead;

    /**
     * @param string $content
     * @param Lead   $lead
     */
    public function __construct($content, Lead $lead)
    {
        $this->content = $content;
        $this->lead    = $lead;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * @return Lead
     */
    public function getLead()
    {
        return $this->lead;
    }

    /**
     * @param Lead $lead
     */
    public function setLead($lead)
    {
        $this->lead = $lead;
    }

    /**
     * @return int
     */
    public function getPushId()
    {
        return $this->pushId;
    }

    /**
     * @param int $pushId
     */
    public function setPushId($pushId)
    {
        $this->pushId = $pushId;
    }
}
