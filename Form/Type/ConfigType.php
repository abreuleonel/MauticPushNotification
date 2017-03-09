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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * Class ConfigType.
 */
class ConfigType extends AbstractType
{
    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'push_enabled',
            'yesno_button_group',
            [
                'label' => 'mautic.push.config.form.push.enabled',
                'data'  => (bool) $options['data']['push_enabled'],
                'attr'  => [
                    'tooltip' => 'mautic.push.config.form.push.enabled.tooltip',
                ],
            ]
        );

        $formModifier = function (FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            // Add required restraints if push is enabled
            $constraints = (empty($data['push_enabled'])) ?
                [] :
                [
                    new NotBlank(
                        [
                            'message' => 'mautic.core.value.required',
                        ]
                    ),
                ];

            $form->add(
                'push_username',
                'text',
                [
                    'label' => 'mautic.push.config.form.push.username',
                    'attr'  => [
                        'tooltip'      => 'mautic.push.config.form.push.username.tooltip',
                        'class'        => 'form-control',
                        'data-show-on' => '{"config_pushconfig_push_enabled_1":"checked"}',
                    ],
                    'constraints' => $constraints,
                ]
            );
        };
        // Before submit
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            $formModifier
        );

        // After submit
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            $formModifier
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'pushconfig';
    }
}
