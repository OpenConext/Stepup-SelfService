<?php

/**
 * Copyright 2020 SURFnet B.V.
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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Surfnet\StepupSelfService\SelfServiceBundle\Assert;

class ApplicationHelper
{
    private $kernelRootDir;

    /**
     * @param string $kernelRootDir
     */
    public function __construct($kernelRootDir)
    {
        Assert::string($kernelRootDir, 'Kernel root directory must have a string value');
        $this->kernelRootDir = $kernelRootDir;
    }

    /**
     * In stepup application, the installation path includes the software version. This is the version that can be
     * read on the `/info` endpoint.
     *
     * For development builds, this will probably simply be Stepup-SelfService
     *
     * @return string
     */
    public function getApplicationVersion()
    {
        // The buildPath (version string) is the installation directory of the project. And is derived from the
        // kernel.root_dir (which is the app folder).
        return basename(realpath($this->kernelRootDir . '/../'));
    }
}
