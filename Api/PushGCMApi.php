<?php
/**
 * @package     Mautic
 * @copyright   2016 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
namespace MauticPlugin\PushBundle\Api;


use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\PageBundle\Model\TrackableModel;
use MauticPlugin\PushBundle\Api\AbstractPushApi;

class PushGCMApi extends AbstractPushApi
{
	private $gcmid;
	private $username;
	
    public function __construct(TrackableModel $pageTrackableModel = null, MauticFactory $factory, $username)
    {
        parent::__construct($pageTrackableModel);
        
        $this->username = $username;
    }

    public function sendPush($gcm_id, $content, $title = '', $subtitle = '', $tickerText = '', $pageto = '')
    {
    	
    	$registrationIds = $gcm_id;
    	
    	$msg = array
    	(
    			'message' 		=> utf8_encode($content),
    			'title'			=> utf8_encode($title),
    			'subtitle'		=> utf8_encode($subtitle),
    			'tickerText'	=> utf8_encode($tickerText),
    			'vibrate'		=> 1,
    			'sound'			=> 1,
    			'largeIcon'		=> 'large_icon',
    			'smallIcon'		=> 'small_icon',
    			'pageTo'		=> $pageto
    	);
    	$fields = array
    	(
    			'registration_ids' 	=> [$registrationIds],
    			'data'			=> $msg
    	);
    	
    	$headers = array
    	(
    			'Authorization: key=' . $this->username,
    			'Content-Type: application/json'
    	);
    	
    	$ch = curl_init();
    	curl_setopt( $ch,CURLOPT_URL, 'http://android.googleapis.com/gcm/send' );
    	curl_setopt( $ch,CURLOPT_POST, true );
    	curl_setopt( $ch,CURLOPT_HTTPHEADER, $headers );
    	curl_setopt( $ch,CURLOPT_RETURNTRANSFER, true );
    	curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
    	curl_setopt( $ch,CURLOPT_POSTFIELDS, json_encode( $fields ) );
    	$result = curl_exec($ch );
    	curl_close( $ch );
    	
    	return $result;
    }
}
