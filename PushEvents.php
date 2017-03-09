<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\PushBundle;

/**
 * Class PushEvents
 * Events available for PushBundle.
 */
final class PushEvents
{
    /**
     * The mautic.push_token_replacement event is thrown right before the content is returned.
     *
     * The event listener receives a
     * Mautic\CoreBundle\Event\TokenReplacementEvent instance.
     *
     * @var string
     */
    const TOKEN_REPLACEMENT = 'mautic.push_token_replacement';

    /**
     * The mautic.push_on_send event is thrown when a push is sent.
     *
     * The event listener receives a
     * Mautic\PushBundle\Event\PushSendEvent instance.
     *
     * @var string
     */
    const PUSH_ON_SEND = 'mautic.push_on_send';

    /**
     * The mautic.push_pre_save event is thrown right before a push is persisted.
     *
     * The event listener receives a
     * Mautic\PushBundle\Event\PushEvent instance.
     *
     * @var string
     */
    const PUSH_PRE_SAVE = 'mautic.push_pre_save';

    /**
     * The mautic.push_post_save event is thrown right after a push is persisted.
     *
     * The event listener receives a
     * Mautic\PushBundle\Event\PushEvent instance.
     *
     * @var string
     */
    const PUSH_POST_SAVE = 'mautic.push_post_save';

    /**
     * The mautic.push_pre_delete event is thrown prior to when a push is deleted.
     *
     * The event listener receives a
     * Mautic\PushBundle\Event\PushEvent instance.
     *
     * @var string
     */
    const PUSH_PRE_DELETE = 'mautic.push_pre_delete';

    /**
     * The mautic.push_post_delete event is thrown after a push is deleted.
     *
     * The event listener receives a
     * Mautic\PushBundle\Event\PushEvent instance.
     *
     * @var string
     */
    const PUSH_POST_DELETE = 'mautic.push_post_delete';

    /**
     * The mautic.push.on_campaign_trigger_action event is fired when the campaign action triggers.
     *
     * The event listener receives a
     * Mautic\CampaignBundle\Event\CampaignExecutionEvent
     *
     * @var string
     */
    const ON_CAMPAIGN_TRIGGER_ACTION = 'mautic.push.on_campaign_trigger_action';
}
