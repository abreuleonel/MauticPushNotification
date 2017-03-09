<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\PushBundle\EventListener;

use Mautic\CoreBundle\EventListener\CommonSubscriber;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\FormEvents;
use MauticPlugin\PushBundle\Event\PushSendEvent;
use MauticPlugin\PushBundle\Model\PushModel;
use MauticPlugin\PushBundle\PushEvents;
use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\FormBundle\Event\SubmissionEvent;
use Mautic\FormBundle\Entity\Action;
use MauticPlugin\PushBundle\Api\AbstractPushApi;

/**
 * Class FormSubscriber.
 */
class FormSubscriber extends CommonSubscriber
{
	
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
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            FormEvents::FORM_ON_BUILD => ['onFormBuilder', 0],
        	FormEvents::FORM_ON_SUBMIT => ['onFormSubmit', 0]
        ];
    }

    /**
     * Add a send email actions to available form submit actions.
     *
     * @param FormBuilderEvent $event
     */
    public function onFormBuilder(FormBuilderEvent $event)
    {
        // Send email to lead
        $action = [
            'group'           => 'mautic.push.actions',
            'label'           => 'mautic.campaign.push.send_text_push',
            'description'     => 'mautic.campaign.push.send_text_push.tooltip',
            'formType'        => 'push_list',
            'formTheme'       => 'MauticEmailBundle:FormTheme\EmailSendList',
            'callback'        => '',
        ];

        $event->addSubmitAction('push.send.lead', $action);
    }
    
    public function onFormSubmit(SubmissionEvent $event) 
    {
    	$type = $event->getForm()->getActions()->first()->getType();
    	
    	if($type == 'push.send.lead') { 
	    	$data =  $event->getPost();
	    	$properties = $event->getForm()->getActions()->first()->getProperties();
	    	$currentLead = $event->getLead();
	    	$pushModel = $this->pushModel;
	    	
	    	foreach($properties as $k => $pushId) {
	    		try {
		    		$push   = $pushModel->getEntity($pushId);
		    		
		    		$pushEvent = new PushSendEvent($push->getMessage(), $currentLead);
		    		$pushEvent->setPushId($pushId);
		    		
		    		$this->dispatcher->dispatch(PushEvents::PUSH_ON_SEND, $pushEvent);
		    		
		    		$tokenEvent = $this->dispatcher->dispatch(
		    				PushEvents::TOKEN_REPLACEMENT,
		    				new TokenReplacementEvent(
		    						$pushEvent->getContent(),
		    						$currentLead,
		    						['channel' => ['push', $push->getId()]]
		    					)
		    				);
		    		
		    		$response = $this->pushApi->sendPush($data['gcmid'], $tokenEvent->getContent());
	    		} catch(Exception $e) {
	    			return $e->getMessage();
	    		}
	    	}
    	}
 		return true;
    }
}
