<?php

/**
 * Copyright 2018 SURFnet bv
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Controller;

use Exception;
use Surfnet\StepupBundle\Controller\ExceptionController as BaseExceptionController;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\MissingRequiredAttributeException;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class ExceptionController extends BaseExceptionController
{
    /**
     * @param Exception $exception
     * @return array View parameters 'title' and 'description'
     */
    protected function getPageTitleAndDescription(Exception $exception)
    {
        $translator = $this->getTranslator();

        if ($exception instanceof HttpException) {
            if ($exception instanceof MissingRequiredAttributeException) {
                $title = $translator->trans('stepup.error.missing_required_attribute.title');
                $description = $exception->getMessage();
            }
        }

        if (isset($title) && isset($description)) {
            return [
                'title' => $title,
                'description' => $description,
            ];
        }

        return parent::getPageTitleAndDescription($exception);
    }
}
