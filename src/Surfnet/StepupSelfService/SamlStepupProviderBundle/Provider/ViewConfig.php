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
     *
     * @param string $loa
     * @param string $logo
     * @param string $androidUrl
     * @param string $iosUrl
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly RequestStack $requestStack,
        private $loa,
        private $logo,
        private $androidUrl,
        private $iosUrl,
        private readonly array $alt,
        private readonly array $title,
        private readonly array $description,
        private readonly array $buttonUse,
        private readonly array $initiateTitle,
        private readonly array $initiateButton,
        private readonly array $explanation,
        private readonly array $authnFailed,
        private readonly array $popFailed
    ) {
    }

    /**
     * @return string
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * @return string
     */
    public function getLoa()
    {
        return $this->loa;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->getTranslation($this->title);
    }

    /**
     * @return string
     */
    public function getAlt()
    {
        return $this->getTranslation($this->alt);
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->getTranslation($this->description);
    }

    /**
     * @return string
     */
    public function getButtonUse()
    {
        return $this->getTranslation($this->buttonUse);
    }

    /**
     * @return string
     */
    public function getInitiateTitle()
    {
        return $this->getTranslation($this->initiateTitle);
    }

    /**
     * @return string
     */
    public function getInitiateButton()
    {
        return $this->getTranslation($this->initiateButton);
    }

    /**
     * @return string
     */
    public function getExplanation()
    {
        return $this->getTranslation($this->explanation);
    }

    /**
     * @return string
     */
    public function getAuthnFailed()
    {
        return $this->getTranslation($this->authnFailed);
    }

    /**
     * @return string
     */
    public function getPopFailed()
    {
        return $this->getTranslation($this->popFailed);
    }

    /**
     * @return string
     */
    public function getAndroidUrl()
    {
        return $this->androidUrl;
    }

    /**
     * @return string
     */
    public function getIosUrl()
    {
        return $this->iosUrl;
    }

    /**
     * @return mixed
     * @throws LogicException
     */
    private function getTranslation(array $translations)
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
