<?php

/**
 * Copyright 2022 SURFnet bv
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

use Surfnet\StepupSelfService\SelfServiceBundle\Command\SafeStoreAuthenticationCommand;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AuthenticateSafeStoreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('secret', PasswordType::class, [
            'label' => 'ss.form.ss_authenticate_safe_store_type.text.secret',
            'required' => true,
            'attr' => ['autofocus' => true],
        ]);
        $builder->add('verifyPassword', SubmitType::class, [
            'label' => 'ss.form.ss_authenticate_safe_store_type.button.continue',
            'attr' => [ 'class' => 'btn btn-primary pull-right' ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => SafeStoreAuthenticationCommand::class,
        ]);
    }

    public function getBlockPrefix()
    {
        return 'ss_authenticate_safe_store';
    }
}
