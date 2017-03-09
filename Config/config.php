<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

return [
    'services' => [
        'events' => [
            'mautic.push.campaignbundle.subscriber' => [
                'class'     => 'MauticPlugin\PushBundle\EventListener\CampaignSubscriber',
                'arguments' => [
                    'mautic.helper.core_parameters',
                    'mautic.lead.model.lead',
                    'mautic.push.model.push',
                    'mautic.push.api',
                ],
            ],
        	'mautic.push.formbundle.subscriber' => [
        		'class' => 'MauticPlugin\PushBundle\EventListener\FormSubscriber',
        		'arguments' => [
        			'mautic.helper.core_parameters',
        			'mautic.lead.model.lead',
        			'mautic.push.model.push',
        			'mautic.push.api',
        		],
        	],
            'mautic.push.configbundle.subscriber' => [
                'class' => 'MauticPlugin\PushBundle\EventListener\ConfigSubscriber',
            ],
//             'mautic.push.pushbundle.subscriber' => [
//                 'class'     => 'MauticPlugin\PushBundle\EventListener\PushSubscriber',
//                 'arguments' => [
//                     'mautic.core.model.auditlog',
//                     'mautic.page.model.trackable',
//                     'mautic.page.helper.token',
//                     'mautic.asset.helper.token',
//                 ],
//             ],
//             'mautic.push.channel.subscriber' => [
//                 'class' => \MauticPlugin\PushBundle\EventListener\ChannelSubscriber::class,
//             ],
//             'mautic.push.stats.subscriber' => [
//                 'class'     => \MauticPlugin\PushBundle\EventListener\StatsSubscriber::class,
//                 'arguments' => [
//                     'doctrine.orm.entity_manager',
//                 ],
//             ],
			],
        'forms' => [
            'mautic.form.type.push' => [
                'class'     => 'MauticPlugin\PushBundle\Form\Type\PushType',
                'arguments' => 'mautic.factory',
                'alias'     => 'push',
            ],
            'mautic.form.type.pushconfig' => [
                'class' => 'MauticPlugin\PushBundle\Form\Type\ConfigType',
                'alias' => 'pushconfig',
            ],
            'mautic.form.type.pushsend_list' => [
                'class'     => 'MauticPlugin\PushBundle\Form\Type\PushSendType',
                'arguments' => 'router',
                'alias'     => 'pushsend_list',
            ],
            'mautic.form.type.push_list' => [
                'class'     => 'MauticPlugin\PushBundle\Form\Type\PushListType',
                'arguments' => 'mautic.factory',
                'alias'     => 'push_list',
            ],
        ],
    		
    	'other' => [ 
    		'mautic.push.api' => [ 
    				'class' => 'MauticPlugin\PushBundle\Api\PushGCMApi', 
    				'arguments' => [ 
    						'mautic.page.model.trackable',
    						'mautic.factory',
    						'%mautic.push_username%',
    				], 
    				'alias' => 'push_api'
    		],
        ],
        'models' => [
            'mautic.push.model.push' => [
                'class'     => 'MauticPlugin\PushBundle\Model\PushModel',
                'arguments' => [
                		'mautic.page.model.trackable',
                		'mautic.lead.model.lead',
                		'mautic.channel.model.queue',
                		'mautic.push.api',
                ],
            ],
	    'mautic.channel.model.queue' => [
        				'class'     => 'Mautic\ChannelBundle\Model\MessageQueueModel',
        				'arguments' => [
        						'mautic.lead.model.lead',
        						'mautic.lead.model.company',
        						'mautic.helper.core_parameters',
        				],
        		],
        ],
     ],
    'routes' => [
        'main' => [
            'mautic_push_index' => [
                'path'       => '/push/{page}',
                'controller' => 'PushBundle:Push:index',
            ],
            'mautic_push_action' => [
                'path'       => '/push/{objectAction}/{objectId}',
                'controller' => 'PushBundle:Push:execute',
            ],
            'mautic_push_contacts' => [
                'path'       => '/push/view/{objectId}/contact/{page}',
                'controller' => 'PushBundle:Push:contacts',
            ],
        ],
    ],
    'menu' => [
        'main' => [
            'items' => [
                'mautic.push.pushes' => [
                    'route'  => 'mautic_push_index',
                    'parent' => 'mautic.core.channels',
                    'checks' => [
                        'parameters' => [
                            'push_enabled' => true,
                        ],
                    ],
                ],
            ],
        ],
    ],
    'parameters' => [
    	'push_enabled'              => false,
    	'push_username'             => null,
  	],
];
