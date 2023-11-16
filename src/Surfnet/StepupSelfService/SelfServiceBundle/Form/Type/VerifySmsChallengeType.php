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

use Surfnet\StepupSelfService\SelfServiceBundle\Command\SmsVerificationCommandInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function array_key_exists;

class VerifySmsChallengeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('challenge', TextType::class, [
            'label' => 'ss.form.ss_verify_sms_challenge.text.challenge',
            'required' => true,
            'attr' => [
                'autofocus' => true,
                'autocomplete' => 'one-time-code',
            ],
            'label_attr' => ['class' => 'pull-right'],
        ]);
        $builder->add('resendChallenge', AnchorType::class, [
            'label' => 'ss.form.ss_verify_sms_challenge.button.resend_challenge',
            'attr' => [ 'class' => 'btn btn-default' ],
            'route' => $options['data']->resendRoute,
            'route_parameters' => $options['data']->resendRouteParameters,
        ]);
        $builder->add('verifyChallenge', SubmitType::class, [
            'label' => 'ss.form.ss_verify_sms_challenge.button.verify_challenge',
            'attr' => [ 'class' => 'btn btn-primary pull-right' ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SmsVerificationCommandInterface::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'ss_verify_sms_challenge';
    }
}
