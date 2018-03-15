<?php

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
     * @var string
     */
    private $loa;

    /**
     * @var string
     */
    private $logo;

    /**
     * @var array
     */
    private $alt;

    /**
     * @var array
     */
    private $title;

    /**
     * @var array
     */
    private $description;

    /**
     * @var array
     */
    private $buttonUse;

    /**
     * @var array
     */
    private $initiateTitle;

    /**
     * @var array
     */
    private $initiateButton;

    /**
     * @var array
     */
    private $explanation;

    /**
     * @var array
     */
    private $authnFailed;

    /**
     * @var array
     */
    private $popFailed;

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $androidUrl;

    /**
     * @var string
     */
    private $iosUrl;

    /**
     * The arrays are arrays of translated text, indexed on locale.
     *
     * @param RequestStack $requestStack
     * @param string $loa
     * @param string $logo
     * @param string $androidUrl
     * @param string $iosUrl
     * @param array $alt
     * @param array $title
     * @param array $description
     * @param array $buttonUse
     * @param array $initiateTitle
     * @param array $initiateButton
     * @param array $explanation
     * @param array $authnFailed
     * @param array $popFailed
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        RequestStack $requestStack,
        $loa,
        $logo,
        $androidUrl,
        $iosUrl,
        array $alt,
        array $title,
        array $description,
        array $buttonUse,
        array $initiateTitle,
        array $initiateButton,
        array $explanation,
        array $authnFailed,
        array $popFailed
    ) {
        $this->loa = $loa;
        $this->logo = $logo;
        $this->androidUrl = $androidUrl;
        $this->iosUrl = $iosUrl;
        $this->alt = $alt;
        $this->title = $title;
        $this->description = $description;
        $this->buttonUse = $buttonUse;
        $this->initiateTitle = $initiateTitle;
        $this->initiateButton = $initiateButton;
        $this->explanation = $explanation;
        $this->authnFailed = $authnFailed;
        $this->popFailed = $popFailed;
        $this->requestStack = $requestStack;
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
    public function getTitle()
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
     * @param array $translations
     * @return mixed
     * @throws LogicException
     */
    private function getTranslation(array $translations)
    {
        $currentLocale = $this->requestStack->getCurrentRequest()->getLocale();
        if (is_null($currentLocale)) {
            throw new LogicException('The current language is not set');
        }
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
