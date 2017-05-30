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

use Surfnet\StepupSelfService\SelfServiceBundle\Exception\LogicException;

class ViewConfig
{
    /**
     * @var string
     */
    public $loa;

    /**
     * @var string
     */
    public $logo;

    /**
     * @var array
     */
    public $alt;

    /**
     * @var array
     */
    public $title;

    /**
     * @var array
     */
    public $description;

    /**
     * @var array
     */
    public $buttonUse;

    /**
     * @var null
     */
    public $currentLanguage = null;

    /**
     * The alt, title, description and buttonUse are arrays of translated text.
     *
     * @param string $loa
     * @param string $logo
     * @param array $alt
     * @param array $title
     * @param array $description
     * @param array $buttonUse
     */
    public function __construct($loa, $logo, array $alt, array $title, array $description, array $buttonUse)
    {
        $this->loa = $loa;
        $this->logo = $logo;
        $this->alt = $alt;
        $this->title = $title;
        $this->description = $description;
        $this->buttonUse = $buttonUse;
    }

    /**
     * @return array
     */
    public function getTitle()
    {
        return $this->getTranslation($this->title);
    }

    /**
     * @return array
     */
    public function getAlt()
    {
        return $this->getTranslation($this->alt);
    }

    /**
     * @return array
     */
    public function getDescription()
    {
        return $this->getTranslation($this->description);
    }

    /**
     * @return array
     */
    public function getButtonUse()
    {
        return $this->getTranslation($this->buttonUse);
    }

    /**
     * @param array $translations
     * @return mixed
     * @throws LogicException
     */
    private function getTranslation(array $translations)
    {
        if (is_null($this->currentLanguage)) {
            throw new LogicException('The current language is not set');
        }
        if (isset($translations[$this->currentLanguage])) {
            return $translations[$this->currentLanguage];
        }
        throw new LogicException(
            sprintf(
                'The requested translation is not available in this language: %s. Available languages: %s',
                $this->currentLanguage,
                implode(', ', array_keys($translations))
            )
        );
    }
}

