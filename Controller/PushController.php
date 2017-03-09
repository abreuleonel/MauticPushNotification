<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\PushBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Controller\EntityContactsTrait;
use MauticPlugin\PushBundle\Entity\Push;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PushController extends FormController
{
    use EntityContactsTrait;

    /**
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction($page = 1)
    {
        /** @var \Mautic\PushBundle\Model\PushModel $model */
        $model = $this->getModel('push');

        //set some permissions
        $permissions = $this->get('mautic.security')->isGranted(
            [
                'push:pushes:viewown',
                'push:pushes:viewother',
                'push:pushes:create',
                'push:pushes:editown',
                'push:pushes:editother',
                'push:pushes:deleteown',
                'push:pushes:deleteother',
                'push:pushes:publishown',
                'push:pushes:publishother',
            ],
            'RETURN_ARRAY'
        );

        if (!$permissions['push:pushes:viewown'] && !$permissions['push:pushes:viewother']) {
            return $this->accessDenied();
        }

        if ($this->request->getMethod() == 'POST') {
            $this->setListFilters();
        }

        $session = $this->get('session');

        $listFilters = [
            'filters' => [
                'multiple' => true,
            ],
        ];

        // Reset available groups
        $listFilters['filters']['groups'] = [];

        //set limits
        $limit = $session->get('mautic.push.limit', $this->coreParametersHelper->getParameter('default_pagelimit'));
        $start = ($page === 1) ? 0 : (($page - 1) * $limit);
        if ($start < 0) {
            $start = 0;
        }

        $search = $this->request->get('search', $session->get('mautic.push.filter', ''));
        $session->set('mautic.email.filter', $search);

        $filter = ['string' => $search];

        if (!$permissions['push:pushes:viewother']) {
            $filter['force'][] =
                ['column' => 'e.createdBy', 'expr' => 'eq', 'value' => $this->user->getId()];
        }

        //retrieve a list of categories
        $listFilters['filters']['groups']['mautic.core.filter.categories'] = [
            'options' => $this->getModel('category')->getLookupResults('email', '', 0),
            'prefix'  => 'category',
        ];

        //retrieve a list of Lead Lists
        $listFilters['filters']['groups']['mautic.core.filter.lists'] = [
            'options' => $this->getModel('lead.list')->getUserLists(),
            'prefix'  => 'list',
        ];

        //retrieve a list of themes
        $listFilters['filters']['groups']['mautic.core.filter.themes'] = [
            'options' => $this->factory->getInstalledThemes('email'),
            'prefix'  => 'theme',
        ];

        $currentFilters = $session->get('mautic.push.list_filters', []);
        $updatedFilters = $this->request->get('filters', false);

        if ($updatedFilters) {
            // Filters have been updated

            // Parse the selected values
            $newFilters     = [];
            $updatedFilters = json_decode($updatedFilters, true);

            if ($updatedFilters) {
                foreach ($updatedFilters as $updatedFilter) {
                    list($clmn, $fltr) = explode(':', $updatedFilter);

                    $newFilters[$clmn][] = $fltr;
                }

                $currentFilters = $newFilters;
            } else {
                $currentFilters = [];
            }
        }
        $session->set('mautic.push.list_filters', $currentFilters);

        if (!empty($currentFilters)) {
            $listIds = $catIds = [];
            foreach ($currentFilters as $type => $typeFilters) {
                switch ($type) {
                    case 'list':
                        $key = 'lists';
                        break;
                    case 'category':
                        $key = 'categories';
                        break;
                }

                $listFilters['filters']['groups']['mautic.core.filter.'.$key]['values'] = $typeFilters;

                foreach ($typeFilters as $fltr) {
                    switch ($type) {
                        case 'list':
                            $listIds[] = (int) $fltr;
                            break;
                        case 'category':
                            $catIds[] = (int) $fltr;
                            break;
                    }
                }
            }

            if (!empty($listIds)) {
                $filter['force'][] = ['column' => 'l.id', 'expr' => 'in', 'value' => $listIds];
            }

            if (!empty($catIds)) {
                $filter['force'][] = ['column' => 'c.id', 'expr' => 'in', 'value' => $catIds];
            }
        }

        $orderBy    = $session->get('mautic.push.orderby', 'e.name');
        $orderByDir = $session->get('mautic.push.orderbydir', 'DESC');

        $pushs = $model->getEntities([
            'start'      => $start,
            'limit'      => $limit,
            'filter'     => $filter,
            'orderBy'    => $orderBy,
            'orderByDir' => $orderByDir,
        ]);

        $count = count($pushs);
        if ($count && $count < ($start + 1)) {
            //the number of entities are now less then the current page so redirect to the last page
            if ($count === 1) {
                $lastPage = 1;
            } else {
                $lastPage = (floor($count / $limit)) ?: 1;
            }

            $session->set('mautic.push.page', $lastPage);
            $returnUrl = $this->generateUrl('mautic_push_index', ['page' => $lastPage]);

            return $this->postActionRedirect([
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $lastPage],
                'contentTemplate' => 'PushBundle:Push:index',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_push_index',
                    'mauticContent' => 'push',
                ],
            ]);
        }
        $session->set('mautic.push.page', $page);

        return $this->delegateView([
            'viewParameters' => [
                'searchValue' => $search,
                'filters'     => $listFilters,
                'items'       => $pushs,
                'totalItems'  => $count,
                'page'        => $page,
                'limit'       => $limit,
                'tmpl'        => $this->request->get('tmpl', 'index'),
                'permissions' => $permissions,
                'model'       => $model,
                'security'    => $this->get('mautic.security'),
                'configured'  => $this->coreParametersHelper->getParameter('push_enabled'),
            ],
            'contentTemplate' => 'PushBundle:Push:list.html.php',
            'passthroughVars' => [
                'activeLink'    => '#mautic_push_index',
                'mauticContent' => 'push',
                'route'         => $this->generateUrl('mautic_push_index', ['page' => $page]),
            ],
        ]);
    }

    /**
     * Loads a specific form into the detailed panel.
     *
     * @param $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function viewAction($objectId)
    {
        /** @var \Mautic\PushBundle\Model\PushModel $model */
        $model    = $this->getModel('push');
        $security = $this->get('mautic.security');

        /** @var \Mautic\PushBundle\Entity\Push $push */
        $push = $model->getEntity($objectId);
        //set the page we came from
        $page = $this->get('session')->get('mautic.push.page', 1);

        if ($push === null) {
            //set the return URL
            $returnUrl = $this->generateUrl('mautic_push_index', ['page' => $page]);

            return $this->postActionRedirect([
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $page],
                'contentTemplate' => 'PushBundle:Push:index',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_push_index',
                    'mauticContent' => 'push',
                ],
                'flashes' => [
                    [
                        'type'    => 'error',
                        'msg'     => 'mautic.push.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ],
                ],
            ]);
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'push:pushes:viewown',
            'push:pushes:viewother',
            $push->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        }

        // Audit Log
        $logs = $this->getModel('core.auditLog')->getLogForObject('push', $push->getId(), $push->getDateAdded());

        // Init the date range filter form
        $dateRangeValues = $this->request->get('daterange', []);
        $action          = $this->generateUrl('mautic_push_action', ['objectAction' => 'view', 'objectId' => $objectId]);
        $dateRangeForm   = $this->get('form.factory')->create('daterange', $dateRangeValues, ['action' => $action]);
        $entityViews     = $model->getHitsLineChartData(
            null,
            new \DateTime($dateRangeForm->get('date_from')->getData()),
            new \DateTime($dateRangeForm->get('date_to')->getData()),
            null,
            ['push_id' => $push->getId()]
        );

        // Get click through stats
        $trackableLinks = $model->getPushClickStats($push->getId());

        return $this->delegateView([
            'returnUrl'      => $this->generateUrl('mautic_push_action', ['objectAction' => 'view', 'objectId' => $push->getId()]),
            'viewParameters' => [
                'push'         => $push,
                'trackables'  => $trackableLinks,
                'logs'        => $logs,
                'isEmbedded'  => $this->request->get('isEmbedded') ? $this->request->get('isEmbedded') : false,
                'permissions' => $security->isGranted([
                    'push:pushes:viewown',
                    'push:pushes:viewother',
                    'push:pushes:create',
                    'push:pushes:editown',
                    'push:pushes:editother',
                    'push:pushes:deleteown',
                    'push:pushes:deleteother',
                    'push:pushes:publishown',
                    'push:pushes:publishother',
                ], 'RETURN_ARRAY'),
                'security'    => $security,
                'entityViews' => $entityViews,
                'contacts'    => $this->forward(
                    'PushBundle:Push:contacts',
                    [
                        'objectId'   => $push->getId(),
                        'page'       => $this->get('session')->get('mautic.push.contact.page', 1),
                        'ignoreAjax' => true,
                    ]
                )->getContent(),
                'dateRangeForm' => $dateRangeForm->createView(),
            ],
            'contentTemplate' => 'PushBundle:Push:details.html.php',
            'passthroughVars' => [
                'activeLink'    => '#mautic_push_index',
                'mauticContent' => 'push',
            ],
        ]);
    }

    /**
     * Generates new form and processes post data.
     *
     * @param Push $entity
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function newAction($entity = null)
    {
        /** @var \Mautic\PushBundle\Model\PushModel $model */
        $model = $this->getModel('push');

        if (!$entity instanceof Push) {
            /** @var \Mautic\PushBundle\Entity\Push $entity */
            $entity = $model->getEntity();
        }

        $method  = $this->request->getMethod();
        $session = $this->get('session');

        if (!$this->get('mautic.security')->isGranted('push:pushes:create')) {
            return $this->accessDenied();
        }

        //set the page we came from
        $page   = $session->get('mautic.push.page', 1);
        $action = $this->generateUrl('mautic_push_action', ['objectAction' => 'new']);

        $updateSelect = ($method == 'POST')
            ? $this->request->request->get('push[updateSelect]', false, true)
            : $this->request->get('updateSelect', false);

        if ($updateSelect) {
            $entity->setPushType('template');
        }

        //create the form
        $form = $model->createForm($entity, $this->get('form.factory'), $action, ['update_select' => $updateSelect]);

        ///Check for a submitted form and process it
        if ($method == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    //form is valid so process the data
                    $model->saveEntity($entity);

                    $this->addFlash(
                        'mautic.core.notice.created',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_push_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_push_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ]
                    );

                    if ($form->get('buttons')->get('save')->isClicked()) {
                        $viewParameters = [
                            'objectAction' => 'view',
                            'objectId'     => $entity->getId(),
                        ];
                        $returnUrl = $this->generateUrl('mautic_push_action', $viewParameters);
                        $template  = 'PushBundle:Push:view';
                    } else {
                        //return edit view so that all the session stuff is loaded
                        return $this->editAction($entity->getId(), true);
                    }
                }
            } else {
                $viewParameters = ['page' => $page];
                $returnUrl      = $this->generateUrl('mautic_push_index', $viewParameters);
                $template       = 'PushBundle:Push:index';
                //clear any modified content
                $session->remove('mautic.push.'.$entity->getId().'.content');
            }

            $passthrough = [
                'activeLink'    => 'mautic_push_index',
                'mauticContent' => 'push',
            ];

            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                        'group'        => $entity->getLanguage(),
                    ]
                );
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                        'passthroughVars' => $passthrough,
                    ]
                );
            }
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form' => $this->setFormTheme($form, 'PushBundle:Push:form.html.php', 'MauticPushBundle:FormTheme\Push'),
                    'push'  => $entity,
                ],
                'contentTemplate' => 'PushBundle:Push:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_push_index',
                    'mauticContent' => 'push',
                    'updateSelect'  => InputHelper::clean($this->request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'mautic_push_action',
                        [
                            'objectAction' => 'new',
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * @param      $objectId
     * @param bool $ignorePost
     * @param bool $forceTypeSelection
     *
     * @return array|\Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function editAction($objectId, $ignorePost = false, $forceTypeSelection = false)
    {
        /** @var \Mautic\PushBundle\Model\PushModel $model */
        $model   = $this->getModel('push');
        $method  = $this->request->getMethod();
        $entity  = $model->getEntity($objectId);
        $session = $this->get('session');
        $page    = $session->get('mautic.push.page', 1);

        //set the return URL
        $returnUrl = $this->generateUrl('mautic_push_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'PushBundle:Push:index',
            'passthroughVars' => [
                'activeLink'    => 'mautic_push_index',
                'mauticContent' => 'push',
            ],
        ];

        //not found
        if ($entity === null) {
            return $this->postActionRedirect(
                array_merge(
                    $postActionVars,
                    [
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.push.error.notfound',
                                'msgVars' => ['%id%' => $objectId],
                            ],
                        ],
                    ]
                )
            );
        } elseif (!$this->get('mautic.security')->hasEntityAccess(
            'push:pushes:viewown',
            'push:pushes:viewother',
            $entity->getCreatedBy()
        )
        ) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            //deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, 'push');
        }

        //Create the form
        $action = $this->generateUrl('mautic_push_action', ['objectAction' => 'edit', 'objectId' => $objectId]);

        $updateSelect = ($method == 'POST')
            ? $this->request->request->get('push[updateSelect]', false, true)
            : $this->request->get('updateSelect', false);

        $form = $model->createForm($entity, $this->get('form.factory'), $action, ['update_select' => $updateSelect]);

        ///Check for a submitted form and process it
        if (!$ignorePost && $method == 'POST') {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    //form is valid so process the data
                    $model->saveEntity($entity, $form->get('buttons')->get('save')->isClicked());

                    $this->addFlash(
                        'mautic.core.notice.updated',
                        [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_push_index',
                            '%url%'       => $this->generateUrl(
                                'mautic_push_action',
                                [
                                    'objectAction' => 'edit',
                                    'objectId'     => $entity->getId(),
                                ]
                            ),
                        ],
                        'warning'
                    );
                }
            } else {
                //clear any modified content
                $session->remove('mautic.push.'.$objectId.'.content');
                //unlock the entity
                $model->unlockEntity($entity);
            }

            $passthrough = [
                'activeLink'    => 'mautic_push_index',
                'mauticContent' => 'push',
            ];

            $template = 'PushBundle:Push:view';

            // Check to see if this is a popup
            if (isset($form['updateSelect'])) {
                $template    = false;
                $passthrough = array_merge(
                    $passthrough,
                    [
                        'updateSelect' => $form['updateSelect']->getData(),
                        'id'           => $entity->getId(),
                        'name'         => $entity->getName(),
                        'group'        => $entity->getLanguage(),
                    ]
                );
            }

            if ($cancelled || ($valid && $form->get('buttons')->get('save')->isClicked())) {
                $viewParameters = [
                    'objectAction' => 'view',
                    'objectId'     => $entity->getId(),
                ];

                return $this->postActionRedirect(
                    array_merge(
                        $postActionVars,
                        [
                            'returnUrl'       => $this->generateUrl('mautic_push_action', $viewParameters),
                            'viewParameters'  => $viewParameters,
                            'contentTemplate' => $template,
                            'passthroughVars' => $passthrough,
                        ]
                    )
                );
            }
        } else {
            //lock the entity
            $model->lockEntity($entity);
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form'               => $this->setFormTheme($form, 'PushBundle:Push:form.html.php', 'PushBundle:FormTheme\Push'),
                    'push'                => $entity,
                    'forceTypeSelection' => $forceTypeSelection,
                ],
                'contentTemplate' => 'PushBundle:Push:form.html.php',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_push_index',
                    'mauticContent' => 'push',
                    'updateSelect'  => InputHelper::clean($this->request->query->get('updateSelect')),
                    'route'         => $this->generateUrl(
                        'mautic_push_action',
                        [
                            'objectAction' => 'edit',
                            'objectId'     => $entity->getId(),
                        ]
                    ),
                ],
            ]
        );
    }

    /**
     * Clone an entity.
     *
     * @param $objectId
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function cloneAction($objectId)
    {
        $model  = $this->getModel('push');
        $entity = $model->getEntity($objectId);

        if ($entity != null) {
            if (!$this->get('mautic.security')->isGranted('push:pushes:create')
                || !$this->get('mautic.security')->hasEntityAccess(
                    'push:pushes:viewown',
                    'push:pushes:viewother',
                    $entity->getCreatedBy()
                )
            ) {
                return $this->accessDenied();
            }

            $entity = clone $entity;
        }

        return $this->newAction($entity);
    }

    /**
     * Deletes the entity.
     *
     * @param   $objectId
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction($objectId)
    {
        $page      = $this->get('session')->get('mautic.push.page', 1);
        $returnUrl = $this->generateUrl('mautic_push_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'PushBundle:Push:index',
            'passthroughVars' => [
                'activeLink'    => 'mautic_push_index',
                'mauticContent' => 'push',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model  = $this->getModel('push');
            $entity = $model->getEntity($objectId);

            if ($entity === null) {
                $flashes[] = [
                    'type'    => 'error',
                    'msg'     => 'mautic.push.error.notfound',
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->get('mautic.security')->hasEntityAccess(
                'push:pushes:deleteown',
                'push:pushes:deleteother',
                $entity->getCreatedBy()
            )
            ) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'push');
            }

            $model->deleteEntity($entity);

            $flashes[] = [
                'type'    => 'notice',
                'msg'     => 'mautic.core.notice.deleted',
                'msgVars' => [
                    '%name%' => $entity->getName(),
                    '%id%'   => $objectId,
                ],
            ];
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                ['flashes' => $flashes]
            )
        );
    }

    /**
     * Deletes a group of entities.
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function batchDeleteAction()
    {
        $page      = $this->get('session')->get('mautic.push.page', 1);
        $returnUrl = $this->generateUrl('mautic_push_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'PushBundle:Push:index',
            'passthroughVars' => [
                'activeLink'    => '#mautic_push_index',
                'mauticContent' => 'push',
            ],
        ];

        if ($this->request->getMethod() == 'POST') {
            $model = $this->getModel('push');
            $ids   = json_decode($this->request->query->get('ids', '{}'));

            $deleteIds = [];

            // Loop over the IDs to perform access checks pre-delete
            foreach ($ids as $objectId) {
                $entity = $model->getEntity($objectId);

                if ($entity === null) {
                    $flashes[] = [
                        'type'    => 'error',
                        'msg'     => 'mautic.push.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->get('mautic.security')->hasEntityAccess(
                    'push:pushes:viewown',
                    'push:pushes:viewother',
                    $entity->getCreatedBy()
                )
                ) {
                    $flashes[] = $this->accessDenied(true);
                } elseif ($model->isLocked($entity)) {
                    $flashes[] = $this->isLocked($postActionVars, $entity, 'push', true);
                } else {
                    $deleteIds[] = $objectId;
                }
            }

            // Delete everything we are able to
            if (!empty($deleteIds)) {
                $entities = $model->deleteEntities($deleteIds);

                $flashes[] = [
                    'type'    => 'notice',
                    'msg'     => 'mautic.push.notice.batch_deleted',
                    'msgVars' => [
                        '%count%' => count($entities),
                    ],
                ];
            }
        } //else don't do anything

        return $this->postActionRedirect(
            array_merge(
                $postActionVars,
                ['flashes' => $flashes]
            )
        );
    }

    /**
     * @param $objectId
     *
     * @return JsonResponse|Response
     */
    public function previewAction($objectId)
    {
        /** @var \Mautic\PushBundle\Model\PushModel $model */
        $model    = $this->getModel('push');
        $push      = $model->getEntity($objectId);
        $security = $this->get('mautic.security');

        if ($push !== null && $security->hasEntityAccess('push:pushes:viewown', 'push:pushes:viewother')) {
            return $this->delegateView([
                'viewParameters' => [
                    'push' => $push,
                ],
                'contentTemplate' => 'PushBundle:Push:preview.html.php',
            ]);
        }

        return new Response('', Response::HTTP_NOT_FOUND);
    }

    /**
     * @param     $objectId
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function contactsAction($objectId, $page = 1)
    {
        return $this->generateContactsGrid(
            $objectId,
            $page,
            'push:pushes:view',
            'push',
            'push_message_stats',
            'push',
            'push_id'
        );
    }
}
