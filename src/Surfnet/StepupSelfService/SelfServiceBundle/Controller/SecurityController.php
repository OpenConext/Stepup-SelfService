<?php

/**
 * Copyright 2016 SURFnet bv
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

use Symfony\Component\HttpFoundation\Request;

class SecurityController extends Controller
{
    public function sessionExpiredAction(Request $request)
    {
        $redirectToUrl = $this
            ->get('self_service.security.authentication.session.session_storage')
            ->getCurrentRequestUri();

        return $this->render(
            'SurfnetStepupSelfServiceSelfServiceBundle:security:session_expired.html.twig',
            ['redirect_to_url' => $redirectToUrl]
        );
    }
}
