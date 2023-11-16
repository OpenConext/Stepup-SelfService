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

use Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider\ViewConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class StatusGssfType extends AbstractType
{
    public function __construct(private readonly RouterInterface $router)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $action = $this->router->generate('ss_registration_gssf_authenticate', ['provider' => $options['provider']]);
        /** @var ViewConfig $secondFactorConfig */
        $builder
            ->add('submit', SubmitType::class, [
                'attr'  => ['class' => 'btn btn-primary'],
                /** @Ignore from translation message extraction */
                'label' => $options['label']
            ])
            ->setAction($action);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired(['provider']);
    }

    public function getBlockPrefix(): string
    {
        return 'ss_status_gssf';
    }
}
