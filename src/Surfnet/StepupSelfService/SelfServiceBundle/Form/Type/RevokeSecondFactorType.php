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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RevokeSecondFactorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('revoke', SubmitType::class, [
            'label' => 'ss.form.ss_revoke_second_factor.revoke',
            'attr' => [ 'class' => 'btn btn-danger pull-right' ],
        ]);
        $builder->add('cancel', AnchorType::class, [
            'label' => 'ss.form.ss_revoke_second_factor.cancel',
            'attr' => [ 'class' => 'btn pull-right' ],
            'route' => 'ss_second_factor_list',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \Surfnet\StepupSelfService\SelfServiceBundle\Command\RevokeCommand::class,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'ss_revoke_second_factor';
    }
}
