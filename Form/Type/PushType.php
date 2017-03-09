<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\PushBundle\Form\Type;

use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Form\EventListener\CleanFormSubscriber;
use Mautic\CoreBundle\Form\EventListener\FormExitSubscriber;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class PushType.
 */
class PushType extends AbstractType
{
    private $translator;
    private $em;
    private $request;

    /**
     * @param MauticFactory $factory
     */
    public function __construct(MauticFactory $factory)
    {
        $this->translator = $factory->getTranslator();
        $this->em         = $factory->getEntityManager();
        $this->request    = $factory->getRequest();
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new CleanFormSubscriber(['content' => 'html', 'customHtml' => 'html']));
        $builder->addEventSubscriber(new FormExitSubscriber('push.push', $options));

        $builder->add(
            'name',
            'text',
            [
                'label'      => 'mautic.push.form.internal.name',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control'],
            ]
        );
        
        $builder->add(
        	'title',
        	'text',
        	[
        		'label'      => 'mautic.push.form.title',
        		'label_attr' => ['class' => 'control-label'],
        		'attr'       => ['class' => 'form-control'],
        	]
        );	
        
        $builder->add(
        	'subtitle',
        	'text',
        	[
        		'label'      => 'mautic.push.form.subtitle',
        		'label_attr' => ['class' => 'control-label'],
        		'attr'       => ['class' => 'form-control'],
        	]
        );

        $builder->add(
            'description',
            'textarea',
            [
                'label'      => 'mautic.push.form.internal.description',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => ['class' => 'form-control'],
                'required'   => false,
            ]
        );

        $builder->add(
            'message',
            'textarea',
            [
                'label'      => 'mautic.push.form.message',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class' => 'form-control',
                    'rows'  => 6,
                ],
            ]
        );
        
        $builder->add(
        		'tickerText',
        		'textarea',
        		[
       				'label'      => 'mautic.push.form.tickertext',
       				'label_attr' => ['class' => 'control-label'],
       				'attr'       => [
       					'class' => 'form-control',
        				'rows'  => 6,
        			],
        		]
        );
        
        $builder->add(
        		'pageto',
        		'text',
        		[
        				'label'      => 'mautic.push.form.pageto',
        				'label_attr' => ['class' => 'control-label'],
        				'attr'       => ['class' => 'form-control'],
        		]
        );
        

        $builder->add('isPublished', 'yesno_button_group');

        $builder->add(
            'publishUp',
            'datetime',
            [
                'widget'     => 'single_text',
                'label'      => 'mautic.core.form.publishup',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'data-toggle' => 'datetime',
                ],
                'format'   => 'yyyy-MM-dd HH:mm',
                'required' => false,
            ]
        );

        $builder->add(
            'publishDown',
            'datetime',
            [
                'widget'     => 'single_text',
                'label'      => 'mautic.core.form.publishdown',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'       => 'form-control',
                    'data-toggle' => 'datetime',
                ],
                'format'   => 'yyyy-MM-dd HH:mm',
                'required' => false,
            ]
        );

        //add category
        $builder->add(
             'category',
             'category',
             [
                 'bundle' => 'push',
             ]
         );

        $builder->add(
            'language',
            'locale',
            [
                'label'      => 'mautic.core.language',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class' => 'form-control',
                ],
                'required' => false,
            ]
        );

        $builder->add('buttons', 'form_buttons');

        if (!empty($options['update_select'])) {
            $builder->add(
                'buttons',
                'form_buttons',
                [
                    'apply_text' => false,
                ]
            );
            $builder->add(
                'updateSelect',
                'hidden',
                [
                    'data'   => $options['update_select'],
                    'mapped' => false,
                ]
            );
        } else {
            $builder->add(
                'buttons',
                'form_buttons'
            );
        }

        if (!empty($options['action'])) {
            $builder->setAction($options['action']);
        }
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => 'MauticPlugin\PushBundle\Entity\Push',
            ]
        );

        $resolver->setOptional(['update_select']);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'push';
    }
}
