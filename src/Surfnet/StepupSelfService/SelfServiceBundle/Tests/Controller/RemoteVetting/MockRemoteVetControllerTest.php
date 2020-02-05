<?php
/**
 * Copyright 2019 SURFnet B.V.
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

namespace Surfnet\Tests\Contrtoller\RemoteVetting;

use Exception;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SAML2\Certificate\PrivateKeyLoader;
use SAML2\Configuration\PrivateKey;
use SAML2\Constants;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\SAML2\AuthnRequest;
use Surfnet\SamlBundle\SAML2\AuthnRequestFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Token\SamlToken;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\RemoteVettingTokenDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVettingService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

class MockRemoteVetControllerTest extends WebTestCase
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var string
     */
    private $publicKey;
    /**
     * @var string
     */
    private $privateKey;

    /**
     * @var RemoteVettingService
     */
    private $remoteVettingService;

    protected function setUp()
    {
        $this->client = static::createClient();
        $this->client->followRedirects(true);

        $container = static::$kernel->getContainer();
        $this->remoteVettingService = $container->get('self_service.remote_vetting.service');

        $projectDir = self::$kernel->getProjectDir();

        $this->publicKey = $projectDir . '/app/config/sp.crt';
        $this->privateKey = $projectDir . '/app/config/sp.key';
    }

    public function testDecisionPage()
    {
        $this->logIn();
        $authnRequestUrl = $this->createAuthnRequestUrl($this->createServiceProvider(), $this->createIdentityProvider(), 'IdentityId', 'SecondFactorId');

        $crawler = $this->client->request('GET', $authnRequestUrl);

        // Test if on decision page
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('success', $crawler->filter('h2')->text());
        $this->assertContains('One moment please...', $this->client->getResponse()->getContent());
    }

    public function testSuccessfulResponse()
    {
        $this->logIn();
        $authnRequestUrl = $this->createAuthnRequestUrl($this->createServiceProvider(), $this->createIdentityProvider(), 'IdentityId', 'SecondFactorId');

        $crawler = $this->client->request('GET', $authnRequestUrl);

        // Test if on decision page
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('success', $crawler->filter('h2')->text());

        // Return success response
        $form = $crawler->selectButton('Submit-success')->form();
        $crawler = $this->client->submit($form);

        // Test if on sp acs
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('Demo Service provider ConsumerAssertionService endpoint', $crawler->filter('h2')->text());
    }

    public function testUserCancelledResponse()
    {
        $authnRequestUrl = $this->createAuthnRequestUrl($this->createServiceProvider(), $this->createIdentityProvider(), 'IdentityId', 'SecondFactorId');

        $crawler = $this->client->request('GET', $authnRequestUrl);

        // Test if on decision page
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('success', $crawler->filter('h2')->text());

        // Return success response
        $form = $crawler->selectButton('Submit-user-cancelled')->form();
        $crawler = $this->client->submit($form);

        // Test if on sp acs
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('Demo Service provider ConsumerAssertionService endpoint', $crawler->filter('h2')->text());
        $this->assertContains('Error SAMLResponse', $this->client->getResponse());
        $this->assertContains('Responder/AuthnFailed Authentication cancelled by user', $this->client->getResponse());
    }

    public function testUnsuccessfulResponse()
    {
        $authnRequestUrl = $this->createAuthnRequestUrl($this->createServiceProvider(), $this->createIdentityProvider(), 'IdentityId', 'SecondFactorId');

        $crawler = $this->client->request('GET', $authnRequestUrl);

        // Test if on decision page
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('success', $crawler->filter('h2')->text());

        // Return success response
        $form = $crawler->selectButton('Submit-unknown')->form();
        $crawler = $this->client->submit($form);

        // Test if on sp acs
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('Demo Service provider ConsumerAssertionService endpoint', $crawler->filter('h2')->text());
        $this->assertContains('Error SAMLResponse', $this->client->getResponse());
        $this->assertContains('Responder/AuthnFailed', $this->client->getResponse());
    }

    /**
     * @param ServiceProvider $serviceProvider
     * @param IdentityProvider $identityProvider
     * @param string $url
     * @param string $emailAddress
     * @return string
     */
    private function createAuthnRequestUrl(ServiceProvider $serviceProvider, IdentityProvider $identityProvider, $identityId, $secondFactorId)
    {
        $authnRequest = AuthnRequestFactory::createNewRequest($serviceProvider, $identityProvider);

        // Set NameId
        $authnRequest->setSubject('', Constants::NAMEID_UNSPECIFIED);

        // Set AuthnContextClassRef
        $authnRequest->setAuthenticationContextClassRef(Constants::AC_UNSPECIFIED);

        // Build request query parameters.
        $requestAsXml = $authnRequest->getUnsignedXML();
        $encodedRequest = base64_encode(gzdeflate($requestAsXml));
        $queryParams = [AuthnRequest::PARAMETER_REQUEST => $encodedRequest];

        // Create redirect response.
        $query = $this->signRequestQuery($queryParams, $serviceProvider);
        $url = sprintf('%s?%s', $identityProvider->getSsoUrl(), $query);

        // Set session specific data
        $token = new RemoteVettingTokenDto($identityId, $secondFactorId);
        $token->setRequestId($authnRequest->getRequestId());
        $this->remoteVettingService->startAuthentication($token);

        return $url;
    }

    private function createServiceProvider()
    {
        return new ServiceProvider(
            [
                'entityId' => 'https://selfservice.stepup.example.com/saml/metadata',
                'assertionConsumerUrl' => 'https://selfservice.stepup.example.com/second-factor/acs',
                'certificateFile' => $this->publicKey,
                'privateKeys' => [
                    new PrivateKey(
                        $this->privateKey,
                        'default'
                    ),
                ],
                'sharedKey' => $this->publicKey,
            ]
        );
    }

    private function createIdentityProvider()
    {
        return new IdentityProvider(
            [
                'entityId' => 'https://selfservice.stepup.example.com/mock/idp/metadata',
                'ssoUrl' => 'https://selfservice.stepup.example.com/second-factor/mock/sso',
                'certificateFile' => $this->publicKey,
                'privateKeys' => [
                    new PrivateKey(
                        $this->privateKey,
                        'default'
                    ),
                ],
                'sharedKey' => $this->publicKey
            ]
        );
    }

    /**
     * Sign AuthnRequest query parameters.
     *
     * @param array $queryParams
     * @param ServiceProvider $serviceProvider
     * @return string
     *
     * @throws Exception
     */
    private function signRequestQuery(array $queryParams, ServiceProvider $serviceProvider)
    {
        /** @var  $securityKey */
        $securityKey = $this->loadServiceProviderPrivateKey($serviceProvider);
        $queryParams[AuthnRequest::PARAMETER_SIGNATURE_ALGORITHM] = $securityKey->type;
        $toSign = http_build_query($queryParams);
        $signature = $securityKey->signData($toSign);

        return $toSign . '&Signature=' . urlencode(base64_encode($signature));
    }

    /**
     * Loads the private key from the service provider.
     *
     * @param ServiceProvider $serviceProvider
     * @return XMLSecurityKey
     *
     * @throws Exception
     */
    private function loadServiceProviderPrivateKey(ServiceProvider $serviceProvider)
    {
        $keyLoader = new PrivateKeyLoader();
        $privateKey = $keyLoader->loadPrivateKey(
            $serviceProvider->getPrivateKey(PrivateKey::NAME_DEFAULT)
        );
        $key = new XMLSecurityKey(XMLSecurityKey::RSA_SHA256, ['type' => 'private']);
        $key->loadKey($privateKey->getKeyAsString());

        return $key;
    }

    private function logIn()
    {
        $session = $this->client->getContainer()->get('session');

        $firewallName = 'saml_based';
        // if you don't define multiple connected firewalls, the context defaults to the firewall name
        // See https://symfony.com/doc/current/reference/configuration/security.html#firewall-context
        $firewallContext = 'saml_based';

        // you may need to use a different token class depending on your application.
        // for example, when using Guard authentication you must instantiate PostAuthenticationGuardToken
        $token = new SamlToken(['ROLE_USER']);
        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }
}
