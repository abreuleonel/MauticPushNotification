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

use Mautic\PluginBundle\Bundle\PluginBundleBase;

/**
 * Class PushBundle.
 */
class PushBundle extends PluginBundleBase
{
	public function __construct() 
	{
		$push_messages =  "CREATE TABLE `push_messages` (
							  `id` int(11) NOT NULL AUTO_INCREMENT,
							  `category_id` int(11) DEFAULT NULL,
							  `is_published` tinyint(1) NOT NULL,
							  `date_added` datetime DEFAULT NULL,
							  `created_by` int(11) DEFAULT NULL,
							  `created_by_user` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
							  `date_modified` datetime DEFAULT NULL,
							  `modified_by` int(11) DEFAULT NULL,
							  `modified_by_user` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
							  `checked_out` datetime DEFAULT NULL,
							  `checked_out_by` int(11) DEFAULT NULL,
							  `checked_out_by_user` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
							  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
							  `title` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
							  `subtitle` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
							  `tickertext` longtext CHARACTER SET utf8,
							  `pageto` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
							  `description` longtext COLLATE utf8_unicode_ci,
							  `lang` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
							  `url` longtext COLLATE utf8_unicode_ci,
							  `heading` longtext COLLATE utf8_unicode_ci NOT NULL,
							  `message` longtext COLLATE utf8_unicode_ci NOT NULL,
							  `button` longtext COLLATE utf8_unicode_ci,
							  `notification_type` longtext COLLATE utf8_unicode_ci,
							  `publish_up` datetime DEFAULT NULL,
							  `publish_down` datetime DEFAULT NULL,
							  `read_count` int(11) NOT NULL,
							  `sent_count` int(11) NOT NULL,
							  `push_type` varchar(45) COLLATE utf8_unicode_ci DEFAULT NULL,
							  PRIMARY KEY (`id`),
							  KEY `IDX_5B9B7E4F12469DE2` (`category_id`)
							) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
	
		$push_messages_stats = "CREATE TABLE IF NOT EXISTS `push_message_stats` (
								  `id` int(11) NOT NULL AUTO_INCREMENT,
								  `push_id` int(11) DEFAULT NULL,
								  `lead_id` int(11) DEFAULT NULL,
								  `list_id` int(11) DEFAULT NULL,
								  `ip_id` int(11) DEFAULT NULL,
								  `date_sent` datetime NOT NULL,
								  `tracking_hash` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
								  `source` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
								  `source_id` int(11) DEFAULT NULL,
								  `tokens` longtext COLLATE utf8_unicode_ci COMMENT '(DC2Type:array)',
								  PRIMARY KEY (`id`),
								  KEY `IDX_FE1BAE9BD5C7E60232` (`push_id`),
								  KEY `IDX_FE1BAE955458D1212` (`lead_id`),
								  KEY `IDX_FE1BAE93DAE168B1212` (`list_id`),
								  KEY `IDX_FE1BAE9A03F5E9F232` (`ip_id`),
								  KEY `stat_push_search` (`push_id`,`lead_id`),
								  KEY `stat_push_hash_search` (`tracking_hash`),
								  KEY `stat_push_source_search` (`source`,`source_id`)
								) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
		
		$push_message_list_href = "CREATE TABLE IF NOT EXISTS `push_message_list_xref` (
									  `push_id` int(11) NOT NULL,
									  `leadlist_id` int(11) NOT NULL,
									  PRIMARY KEY (`push_id`,`leadlist_id`),
									  KEY `IDX_B032FC2EBD5C7E60332` (`push_id`),
									  KEY `IDX_B032FC2EB9FC8874323` (`leadlist_id`)
									) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
	
	}
}
