<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\PushBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\PushBundle\Event\PushSendEvent;
use MauticPlugin\PushBundle\Model\PushModel;
use MauticPlugin\PushBundle\PushEvents;
use MauticPlugin\PushBundle\Api\AbstractPushApi;

/**
 * Class CampaignSubscriber.
 */
class CampaignSubscriber extends CommonSubscriber
{
    /**
     * @var CoreParametersHelper
     */
    protected $coreParametersHelper;

    /**
     * @var LeadModel
     */
    protected $leadModel;

    /**
     * @var PushModel
     */
    protected $pushModel;

    /**
     * @var AbstractPushApi
     */
    protected $pushApi;

    /**
     * CampaignSubscriber constructor.
     *
     * @param CoreParametersHelper $coreParametersHelper
     * @param LeadModel            $leadModel
     * @param PushModel             $pushModel
     * @param AbstractPushApi       $pushApi
     */
    public function __construct(
        CoreParametersHelper $coreParametersHelper,
        LeadModel $leadModel,
        PushModel $pushModel,
        AbstractPushApi $pushApi
    ) {
        $this->coreParametersHelper = $coreParametersHelper;
        $this->leadModel            = $leadModel;
        $this->pushModel            = $pushModel;
        $this->pushApi              = $pushApi;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD     => ['onCampaignBuild', 0],
            PushEvents::ON_CAMPAIGN_TRIGGER_ACTION => ['onCampaignTriggerAction', 0],
        ];
    }

    /**
     * @param CampaignBuilderEvent $event
     */
    public function onCampaignBuild(CampaignBuilderEvent $event)
    {
        if ($this->coreParametersHelper->getParameter('push_enabled')) {
            $event->addAction(
                'push.send_text_push',
                [
                    'label'            => 'mautic.campaign.push.send_text_push',
                    'description'      => 'mautic.campaign.push.send_text_push.tooltip',
                    'eventName'        => PushEvents::ON_CAMPAIGN_TRIGGER_ACTION,
                    'formType'         => 'pushsend_list',
                    'formTypeOptions'  => ['update_select' => 'campaignevent_properties_push'],
                    'formTheme'        => 'MauticPushBundle:FormTheme\PushSendList',
                    'timelineTemplate' => 'MauticPushBundle:SubscribedEvents\Timeline:index.html.php',
                ]
            );
        }
    }

    /**
     * @param CampaignExecutionEvent $event
     */
    public function onCampaignTriggerAction(CampaignExecutionEvent $event)
    {
        $lead = $event->getLead();

        if ($this->leadModel->isContactable($lead, 'push') !== DoNotContact::IS_CONTACTABLE) {
            return $event->setFailed('mautic.push.campaign.failed.not_contactable');
        }

        $gcmid = $lead->getFieldValue('gcmid');
        

        if (empty($gcmid)) {
            return $event->setFailed('mautic.push.campaign.failed.missing_number');
        }

        $pushId = (int) $event->getConfig()['push'];
        $push   = $this->pushModel->getEntity($pushId);

        if ($push->getId() !== $pushId) {
            return $event->setFailed('mautic.push.campaign.failed.missing_entity');
        }

        $pushEvent = new PushSendEvent($push->getMessage(), $lead);
        $pushEvent->setPushId($pushId);
        $this->dispatcher->dispatch(PushEvents::PUSH_ON_SEND, $pushEvent);

        $tokenEvent = $this->dispatcher->dispatch(
            PushEvents::TOKEN_REPLACEMENT,
            new TokenReplacementEvent(
                $pushEvent->getContent(),
                $lead,
                ['channel' => ['push', $push->getId()]]
            )
        );

        
        $metadata = $this->pushApi->sendPush($gcmid, $tokenEvent->getContent());

        $defaultFrequencyNumber = $this->coreParametersHelper->getParameter('push_frequency_number');
        $defaultFrequencyTime   = $this->coreParametersHelper->getParameter('push_frequency_time');

        /** @var \Mautic\LeadBundle\Entity\FrequencyRuleRepository $frequencyRulesRepo */
        $frequencyRulesRepo = $this->leadModel->getFrequencyRuleRepository();

        $leadIds = $lead->getId();

        $dontSendTo = $frequencyRulesRepo->getAppliedFrequencyRules('push', $leadIds, $defaultFrequencyNumber, $defaultFrequencyTime);

        if (!empty($dontSendTo) and $dontSendTo[0]['lead_id'] != $lead->getId()) {
            $metadata = $this->pushApi->sendPush($gcmid, $pushEvent->getContent());
        }

        // If there was a problem sending at this point, it's an API problem and should be requeued
        if ($metadata === false) {
            return $event->setResult(false);
        }

        $this->pushModel->createStatEntry($push, $lead);
        $this->pushModel->getRepository()->upCount($pushId);
        $event->setChannel('push', $push->getId());
        $event->setResult(
            [
                'type'    => 'mautic.push.push',
                'status'  => 'mautic.push.timeline.status.delivered',
                'id'      => $push->getId(),
                'name'    => $push->getName(),
                'content' => $tokenEvent->getContent(),
            ]
        );
    }
}
