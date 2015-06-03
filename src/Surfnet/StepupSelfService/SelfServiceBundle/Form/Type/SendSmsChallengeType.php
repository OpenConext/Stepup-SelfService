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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SendSmsChallengeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('countryCode', 'choice', [
                'label'                          => /** @Ignore */ 'country code',
                'horizontal_label_class'         => 'sr-only',
                'required'                       => true,
                'choice_list'                    => CountryCodeListing::asChoiceList(),
                'preferred_choices'              => [CountryCodeListing::PREFERRED_CHOICE],
                'horizontal_input_wrapper_class' => 'foo',
            ])
            ->add('subscriber', 'text', [
                'label'                          => /** @Ignore */ 'subscriberNumber',
                'horizontal_label_class' => 'sr-only',
                'required'                       => true,
                'horizontal_input_wrapper_class' => 'foo',
                'attr'                           => [
                    'autofocus' => true,
                    'placeholder' => '612345678',
                ]
            ])
            ->add('sendChallenge', 'submit', [
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

    public function getName()
    {
        return 'ss_send_sms_challenge';
    }
}
