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

use Surfnet\StepupSelfService\SelfServiceBundle\Command\RemoteVetValidationCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RemoteVetValidationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('matches', CollectionType::class, [
            // each entry in the array will be an "email" field
            'entry_type' => RemoteVetAssertionMatchType::class,
            // these options are passed to each "email" type
            'entry_options' => [
                'attr' => ['class' => 'assertion_match'],
                'label' => false,
            ],
        ]);

        $builder->add('valid', CheckboxType::class, [
            'label'    => 'VALID',
            'required' => true,
        ]);

        $builder->add('remarks', TextareaType::class, [
            'label'    => 'Opmerkingen?',
            'required' => false,
        ]);

        $builder->add('validate', SubmitType::class, [
            'label' => 'ss.form.ss_remote_vet_second_factor.validate',
            'attr' => [ 'class' => 'btn btn-primary pull-right' ],
        ]);
        $builder->add('cancel', AnchorType::class, [
            'label' => 'ss.form.ss_remote_vet_second_factor.cancel',
            'attr' => [ 'class' => 'btn pull-right' ],
            'route' => 'ss_second_factor_list',
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => RemoteVetValidationCommand::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'ss_remote_vet_validation';
    }
}
