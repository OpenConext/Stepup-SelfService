<?php

declare(strict_types = 1);

/**
 * Copyright 2017 SURFnet bv
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

namespace Surfnet\StepupSelfService\SamlStepupProviderBundle\Provider;

use Surfnet\StepupBundle\Value\Provider\ViewConfigInterface;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;
use Symfony\Component\HttpFoundation\RequestStack;

class ViewConfig implements ViewConfigInterface
{
    /**
     * The arrays are arrays of translated text, indexed on locale.
     * @SuppressWarnings("PHPMD.ExcessiveParameterList")
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly string       $loa,
        private readonly string       $logo,
        private readonly string       $androidUrl,
        private readonly string       $iosUrl,
        private readonly array        $alt,
        private readonly array        $title,
        private readonly array        $description,
        private readonly array        $buttonUse,
        private readonly array        $initiateTitle,
        private readonly array        $initiateButton,
        private readonly array        $explanation,
        private readonly array        $authnFailed,
        private readonly array        $popFailed
    ) {
    }

    public function getLogo(): string
    {
        return $this->logo;
    }

    public function getLoa(): string
    {
        return $this->loa;
    }

    public function getTitle(): string
    {
        return $this->getTranslation($this->title);
    }

    public function getAlt(): string
    {
        return $this->getTranslation($this->alt);
    }

    public function getDescription(): string
    {
        return $this->getTranslation($this->description);
    }

    public function getButtonUse(): string
    {
        return $this->getTranslation($this->buttonUse);
    }

    public function getInitiateTitle(): string
    {
        return $this->getTranslation($this->initiateTitle);
    }

    public function getInitiateButton(): string
    {
        return $this->getTranslation($this->initiateButton);
    }

    public function getExplanation(): string
    {
        return $this->getTranslation($this->explanation);
    }

    public function getAuthnFailed(): string
    {
        return $this->getTranslation($this->authnFailed);
    }

    public function getPopFailed(): string
    {
        return $this->getTranslation($this->popFailed);
    }

    public function getAndroidUrl(): string
    {
        return $this->androidUrl;
    }

    public function getIosUrl(): string
    {
        return $this->iosUrl;
    }

    /**
     * @throws LogicException
     */
    private function getTranslation(array $translations): mixed
    {
        $currentLocale = $this->requestStack->getCurrentRequest()->getLocale();
        if (isset($translations[$currentLocale])) {
            return $translations[$currentLocale];
        }
        throw new LogicException(
            sprintf(
                'The requested translation is not available in this language: %s. Available languages: %s',
                $currentLocale,
                implode(', ', array_keys($translations))
            )
        );
    }
}
