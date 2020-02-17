<?php

/**
 * Copyright 2014 SURFnet bv
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Surfnet\StepupSelfService\SelfServiceBundle\Form\Type;

use Surfnet\StepupBundle\Value\PhoneNumber\CountryCodeListing;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SendSmsChallengeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('country', ChoiceType::class, [
                'label'                          => /** @Ignore */ 'country code',
                'required'                       => true,
                'choices' => CountryCodeListing::asArray(),
                'preferred_choices'              =>
                    ['Surfnet\StepupBundle\Value\PhoneNumber\CountryCodeListing', 'isPreferredChoice'],
            ])
            ->add('subscriber', TextType::class, [
                'label'                          => /** @Ignore */ 'subscriberNumber',
                'required'                       => true,
                'attr'                           => [
                    'autofocus' => true,
                    'class' => 'pull-right',
                    'placeholder' => '612345678',
                ]
            ])
            ->add('sendChallenge', SubmitType::class, [
                'label' => 'ss.form.ss_send_sms_challenge.button.send_challenge',
                'attr' => [ 'class' => 'btn btn-primary pull-right' ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'attr' => ['class' => 'form-inline'],
            'data_class' => 'Surfnet\StepupSelfService\SelfServiceBundle\Command\SendSmsChallengeCommand',
        ]);
    }

    public function getBlockPrefix()
    {
        return 'ss_send_sms_challenge';
    }
}
