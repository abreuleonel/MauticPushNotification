<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\PushBundle\Model;

use Doctrine\DBAL\Query\QueryBuilder;
use Mautic\ChannelBundle\Entity\MessageQueue;
use Mautic\ChannelBundle\Model\MessageQueueModel;
use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\CoreBundle\Helper\Chart\ChartQuery;
use Mautic\CoreBundle\Helper\Chart\LineChart;
use Mautic\CoreBundle\Model\AjaxLookupModelInterface;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\LeadBundle\Entity\DoNotContact;
use Mautic\LeadBundle\Entity\DoNotContactRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PageBundle\Model\TrackableModel;
use MauticPlugin\PushBundle\Api\AbstractPushApi;
use MauticPlugin\PushBundle\Entity\Push;
use MauticPlugin\PushBundle\Entity\Stat;
use MauticPlugin\PushBundle\Event\PushEvent;
use MauticPlugin\PushBundle\Event\PushSendEvent;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use MauticPlugin\PushBundle\PushEvents;

/**
 * Class PushModel
 * {@inheritdoc}
 */
class PushModel extends FormModel implements AjaxLookupModelInterface
{
    /**
     * @var TrackableModel
     */
    protected $pageTrackableModel;

    /**
     * @var LeadModel
     */
    protected $leadModel;

    /**
     * @var MessageQueueModel
     */
    protected $messageQueueModel;

    /**
     * @var
     */
    protected $pushApi;

    /**
     * PushModel constructor.
     *
     * @param TrackableModel    $pageTrackableModel
     * @param LeadModel         $leadModel
     * @param MessageQueueModel $messageQueueModel
     * @param AbstractPushApi    $pushApi
     */
    public function __construct(TrackableModel $pageTrackableModel, LeadModel $leadModel, MessageQueueModel $messageQueueModel,         AbstractPushApi $pushApi)
    {
        $this->pageTrackableModel = $pageTrackableModel;
        $this->leadModel          = $leadModel;
        $this->messageQueueModel  = $messageQueueModel;
        $this->pushApi             = $pushApi;
    }

    /**
     * {@inheritdoc}
     *
     * @return \Mautic\PushBundle\Entity\PushRepository
     */
    public function getRepository()
    {
        return $this->em->getRepository('PushBundle:Push');
    }

    /**
     * @return \Mautic\PushBundle\Entity\StatRepository
     */
    public function getStatRepository()
    {
        return $this->em->getRepository('PushBundle:Stat');
    }

    /**
     * {@inheritdoc}
     */
    public function getPermissionBase()
    {
        return 'push:pushes';
    }

    /**
     * Save an array of entities.
     *
     * @param  $entities
     * @param  $unlock
     *
     * @return array
     */
    public function saveEntities($entities, $unlock = true)
    {
        //iterate over the results so the events are dispatched on each delete
        $batchSize = 20;
        foreach ($entities as $k => $entity) {
            $isNew = ($entity->getId()) ? false : true;

            //set some defaults
            $this->setTimestamps($entity, $isNew, $unlock);

            if ($dispatchEvent = $entity instanceof Push) {
                $event = $this->dispatchEvent('pre_save', $entity, $isNew);
            }

            $this->getRepository()->saveEntity($entity, false);

            if ($dispatchEvent) {
                $this->dispatchEvent('post_save', $entity, $isNew, $event);
            }

            if ((($k + 1) % $batchSize) === 0) {
                $this->em->flush();
            }
        }
        $this->em->flush();
    }

    /**
     * {@inheritdoc}
     *
     * @param       $entity
     * @param       $formFactory
     * @param null  $action
     * @param array $options
     *
     * @return mixed
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function createForm($entity, $formFactory, $action = null, $options = [])
    {
        if (!$entity instanceof Push) {
            throw new MethodNotAllowedHttpException(['Push']);
        }
        if (!empty($action)) {
            $options['action'] = $action;
        }

        return $formFactory->create('push', $entity, $options);
    }

    /**
     * Get a specific entity or generate a new one if id is empty.
     *
     * @param $id
     *
     * @return null|Push
     */
    public function getEntity($id = null)
    {
        if ($id === null) {
            $entity = new Push();
        } else {
            $entity = parent::getEntity($id);
        }

        return $entity;
    }

    /**
     * @param Push   $push
     * @param       $sendTo
     * @param array $options
     *
     * @return array
     */
    public function sendPush(Push $push, $sendTo, $options = [])
    {
        $channel = (isset($options['channel'])) ? $options['channel'] : null;

        if ($sendTo instanceof Lead) {
            $sendTo = [$sendTo];
        } elseif (!is_array($sendTo)) {
            $sendTo = [$sendTo];
        }

        $sentCount     = 0;
        $results       = [];
        $contacts      = [];
        $fetchContacts = [];
        foreach ($sendTo as $lead) {
            if (!$lead instanceof Lead) {
                $fetchContacts[] = $lead;
            } else {
                $contacts[$lead->getId()] = $lead;
            }
        }

        if ($fetchContacts) {
            $foundContacts = $this->leadModel->getEntities(
                [
                    'ids' => $fetchContacts,
                ]
            );

            foreach ($foundContacts as $contact) {
                $contacts[$contact->getId()] = $contact;
            }
        }
        $contactIds = array_keys($contacts);

        /** @var DoNotContactRepository $dncRepo */
        $dncRepo = $this->em->getRepository('MauticLeadBundle:DoNotContact');
        $dnc     = $dncRepo->getChannelList('push', $contactIds);

        if (!empty($dnc)) {
            foreach ($dnc as $removeMeId => $removeMeReason) {
                $results[$removeMeId] = [
                    'sent'   => false,
                    'status' => 'mautic.push.campaign.failed.not_contactable',
                ];

                unset($contacts[$removeMeId], $contactIds[$removeMeId]);
            }
        }

        if (!empty($contacts)) {
            $messageQueue    = (isset($options['resend_message_queue'])) ? $options['resend_message_queue'] : null;
            $campaignEventId = (is_array($channel) && 'campaign.event' === $channel[0] && !empty($channel[1])) ? $channel[1] : null;

            $queued = $this->messageQueueModel->processFrequencyRules(
                $contacts,
                'push',
                $push->getId(),
                $campaignEventId,
                3,
                MessageQueue::PRIORITY_NORMAL,
                $messageQueue,
                'push_message_stats'
            );

            if ($queued) {
                foreach ($queued as $queue) {
                    $results[$queue] = [
                        'sent'   => false,
                        'status' => 'mautic.push.timeline.status.scheduled',
                    ];

                    unset($contacts[$queue]);
                }
            }

            $stats = [];
            if (count($contacts)) {
                /** @var Lead $lead */
                foreach ($contacts as $lead) {
                    $leadId = $lead->getId();

                    $gcmid = $lead->getGcmId();

                    if (empty($gcmid)) {
                        $results[$leadId] = [
                            'sent'   => false,
                            'status' => 'mautic.push.campaign.failed.missing_number',
                        ];
                    }

                    $pushEvent = new PushSendEvent($push->getMessage(), $lead);
                    $pushEvent->setPushId($push->getId());
                    $this->dispatcher->dispatch(PushEvents::PUSH_ON_SEND, $pushEvent);

                    $tokenEvent = $this->dispatcher->dispatch(
                        PushEvents::TOKEN_REPLACEMENT,
                        new TokenReplacementEvent(
                            $pushEvent->getContent(),
                            $lead,
                            ['channel' => ['push', $push->getId()]]
                        )
                    );

                    $sendResult = [
                        'sent'    => false,
                        'type'    => 'mautic.push.push',
                        'status'  => 'mautic.push.timeline.status.delivered',
                        'id'      => $push->getId(),
                        'name'    => $push->getName(),
                        'content' => $tokenEvent->getContent(),
                    ];

                    $metadata = $this->pushApi->pushPush($gcmid, $tokenEvent->getContent());

                    if (true !== $metadata) {
                        $sendResult['status'] = $metadata;
                    } else {
                        $sendResult['sent'] = true;
                        $stats[]            = $this->createStatEntry($push, $lead, $channel, false);
                        ++$sentCount;
                    }

                    $results[$leadId] = $sendResult;

                    unset($pushEvent, $tokenEvent, $sendResult, $metadata);
                }
            }
        }

        if ($sentCount) {
            $this->getRepository()->upCount($push->getId(), 'sent', $sentCount);
            $this->getStatRepository()->saveEntities($stats);
            $this->em->clear(Stat::class);
        }

        return $results;
    }

    /**
     * @param Push  $push
     * @param Lead $lead
     * @param null $source
     * @param bool $persist
     *
     * @return Stat
     */
    public function createStatEntry(Push $push, Lead $lead, $source = null, $persist = true)
    {
        $stat = new Stat();
        $stat->setDateSent(new \DateTime());
        $stat->setLead($lead);
        $stat->setPush($push);
        if (is_array($source)) {
            $stat->setSourceId($source[1]);
            $source = $source[0];
        }
        $stat->setSource($source);

        if ($persist) {
            $this->getStatRepository()->saveEntity($stat);
        }

        return $stat;
    }

    /**
     * {@inheritdoc}
     *
     * @param $action
     * @param $event
     * @param $entity
     * @param $isNew
     *
     * @throws \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException
     */
    protected function dispatchEvent($action, &$entity, $isNew = false, Event $event = null)
    {
        if (!$entity instanceof Push) {
            throw new MethodNotAllowedHttpException(['Push']);
        }

        switch ($action) {
            case 'pre_save':
                $name = PushEvents::PUSH_PRE_SAVE;
                break;
            case 'post_save':
                $name = PushEvents::PUSH_POST_SAVE;
                break;
            case 'pre_delete':
                $name = PushEvents::PUSH_PRE_DELETE;
                break;
            case 'post_delete':
                $name = PushEvents::PUSH_POST_DELETE;
                break;
            default:
                return;
        }

        if ($this->dispatcher->hasListeners($name)) {
            if (empty($event)) {
                $event = new PushEvent($entity, $isNew);
                $event->setEntityManager($this->em);
            }

            $this->dispatcher->dispatch($name, $event);

            return $event;
        } else {
            return;
        }
    }

    /**
     * Joins the page table and limits created_by to currently logged in user.
     *
     * @param QueryBuilder $q
     */
    public function limitQueryToCreator(QueryBuilder &$q)
    {
        $q->join('t', MAUTIC_TABLE_PREFIX.'push_messages', 's', 's.id = t.push_id')
            ->andWhere('s.created_by = :userId')
            ->setParameter('userId', $this->userHelper->getUser()->getId());
    }

    /**
     * Get line chart data of hits.
     *
     * @param char      $unit          {@link php.net/manual/en/function.date.php#refsect1-function.date-parameters}
     * @param \DateTime $dateFrom
     * @param \DateTime $dateTo
     * @param string    $dateFormat
     * @param array     $filter
     * @param bool      $canViewOthers
     *
     * @return array
     */
    public function getHitsLineChartData($unit, \DateTime $dateFrom, \DateTime $dateTo, $dateFormat = null, $filter = [], $canViewOthers = true)
    {
        $flag = null;

        if (isset($filter['flag'])) {
            $flag = $filter['flag'];
            unset($filter['flag']);
        }

        $chart = new LineChart($unit, $dateFrom, $dateTo, $dateFormat);
        $query = new ChartQuery($this->em->getConnection(), $dateFrom, $dateTo);

        if (!$flag || $flag === 'total_and_unique') {
            $q = $query->prepareTimeDataQuery('push_message_stats', 'date_sent', $filter);

            if (!$canViewOthers) {
                $this->limitQueryToCreator($q);
            }

            $data = $query->loadAndBuildTimeData($q);
            $chart->setDataset($this->translator->trans('mautic.push.show.total.sent'), $data);
        }

        return $chart->render();
    }

    /**
     * @param $idHash
     *
     * @return Stat
     */
    public function getPushStatus($idHash)
    {
        return $this->getStatRepository()->getPushStatus($idHash);
    }

    /**
     * Search for an push stat by push and lead IDs.
     *
     * @param $pushId
     * @param $leadId
     *
     * @return array
     */
    public function getPushStatByLeadId($pushId, $leadId)
    {
        return $this->getStatRepository()->findBy(
            [
                'push'  => (int) $pushId,
                'lead' => (int) $leadId,
            ],
            ['dateSent' => 'DESC']
        );
    }

    /**
     * Get an array of tracked links.
     *
     * @param $pushId
     *
     * @return array
     */
    public function getPushClickStats($pushId)
    {
        return $this->pageTrackableModel->getTrackableList('push', $pushId);
    }

    /**
     * @param        $type
     * @param string $filter
     * @param int    $limit
     * @param int    $start
     * @param array  $options
     *
     * @return array
     */
    public function getLookupResults($type, $filter = '', $limit = 10, $start = 0, $options = [])
    {
        $results = [];
        switch ($type) {
            case 'push':
                $entities = $this->getRepository()->getPushList(
                    $filter,
                    $limit,
                    $start,
                    $this->security->isGranted($this->getPermissionBase().':viewother'),
                    isset($options['template']) ? $options['template'] : false
                );

                foreach ($entities as $entity) {
                    $results[$entity['language']][$entity['id']] = $entity['name'];
                }

                //sort by language
                ksort($results);

                break;
        }

        return $results;
    }
}
