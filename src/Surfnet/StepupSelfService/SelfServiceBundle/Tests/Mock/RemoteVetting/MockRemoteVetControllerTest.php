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

namespace Surfnet\Tests\Mock\RemoteVetting;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Mockery as m;
use Psr\Log\NullLogger;
use SAML2\Certificate\KeyLoader;
use SAML2\Configuration\PrivateKey;
use SAML2\Response\Processor;
use Surfnet\SamlBundle\Entity\IdentityProvider;
use Surfnet\SamlBundle\Entity\ServiceProvider;
use Surfnet\SamlBundle\Http\PostBinding;
use Surfnet\SamlBundle\SAML2\Attribute\AttributeSet;
use Surfnet\SamlBundle\Signing\SignatureVerifier;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupSelfService\SelfServiceBundle\Security\Authentication\Token\SamlToken;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\RemoteVettingTokenDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\IdentityProviderFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\SamlCalloutHelper;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\ServiceProviderFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVettingService;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Crawler;

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
    /**
     * @var SamlCalloutHelper
     */
    private $samlCalloutHelper;

    protected function setUp() {

        // This is a fix to prevent segfaults in php 5.6 on Travis
        if (version_compare(phpversion(), '7', '<')) {
               ini_set('zend.enable_gc', '0');
        }

        $this->client = static::createClient(['environment' => 'test']);
        $this->client->followRedirects(true);

        $container = static::$kernel->getContainer();
        $this->remoteVettingService = $container->get('Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVettingService');

        $projectDir = self::$kernel->getProjectDir();

        $keyPath =  '/src/Surfnet/StepupSelfService/SelfServiceBundle/Tests/Resources';

        $this->publicKey = $projectDir . $keyPath . '/test.crt';
        $this->privateKey = $projectDir . $keyPath . '/test.key';

        $this->samlCalloutHelper = $this->setupSamlCalloutHelper();
    }

    /**
     * @test
     * @group rv
     */
    public function the_mock_remote_vetting_idp_should_present_us_with_possible_results_for_testing_purposes()
    {
        $this->logIn();
        $this->remoteVettingService->start('IRMA',  RemoteVettingTokenDto::create('identity-id-123456', 'second-factor-id-56789'));
        $authnRequestUrl = $this->samlCalloutHelper->createAuthnRequest('MockIdP');

        $crawler = $this->client->request('GET', $authnRequestUrl);

        // Test if on decision page
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('Select response', $crawler->filter('h2')->text());
    }

    /**
     * @test
     * @group rv
     */
    public function a_succesful_response_from_a_remote_vetting_idp_should_succeed()
    {
        $this->logIn();
        $this->remoteVettingService->start('IRMA', RemoteVettingTokenDto::create('identity-id-123456', 'second-factor-id-56789'));
        $authnRequestUrl = $this->samlCalloutHelper->createAuthnRequest('MockIdP');

        $crawler = $this->client->request('GET', $authnRequestUrl);

        // Test valid response
        $this->postForm($crawler, 'success');

        // Test if on manual matching form
        $c = $this->client->getResponse()->getContent();
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertStringStartsWith('https://selfservice.stepup.example.com/second-factor/remote-vetting/match/', $this->client->getRequest()->getUri());
        $this->assertContains('Controleer informatie', $this->client->getResponse()->getContent());
    }

    /**
     * @test
     * @group rv
     */
    public function a_user_cancelled_response_from_a_remote_vetting_idp_should_fail()
    {
        $this->logIn();
        $this->remoteVettingService->start('IRMA', RemoteVettingTokenDto::create('identity-id-123456', 'second-factor-id-56789'));
        $authnRequestUrl = $this->samlCalloutHelper->createAuthnRequest('MockIdP');

        $crawler = $this->client->request('GET', $authnRequestUrl);

        // Test user cancelled response
        $this->postForm($crawler, 'user-cancelled');

        // Test if on sp acs
        //$this->assertEquals(200, $this->client->getResponse()->getStatusCode()); // this could be enabled if the request to MW are mocked
        $this->assertEquals('https://selfservice.stepup.example.com/overview', $this->client->getRequest()->getUri());
        $this->assertContains('De identiteitsinformatie kon niet worden gevalideerd', $this->client->getResponse()->getContent());
    }

    /**
     * @test
     * @group rv
     */
    public function an_unsuccessful_response_from_a_remote_vetting_idp_should_fail()
    {
        $this->logIn();
        $this->remoteVettingService->start('IRMA', RemoteVettingTokenDto::create('identity-id-123456', 'second-factor-id-56789'));
        $authnRequestUrl = $this->samlCalloutHelper->createAuthnRequest('MockIdP');

        $crawler = $this->client->request('GET', $authnRequestUrl);

        // Test unknown response
        $this->postForm($crawler, 'unknown');

        // Test if on sp acs
        //$this->assertEquals(200, $this->client->getResponse()->getStatusCode()); // this could be enabled if the request to MW are mocked
        $this->assertEquals('https://selfservice.stepup.example.com/overview', $this->client->getRequest()->getUri());
        $this->assertContains('De identiteitsinformatie kon niet worden gevalideerd', $this->client->getResponse()->getContent());
    }

    /**
     * @return SamlCalloutHelper
     */
    private function setupSamlCalloutHelper()
    {
        $identityProviderFactory = m::mock(IdentityProviderFactory::class);
        $identityProviderFactory->shouldReceive('create')
            ->with('MockIdP')
            ->once()
            ->andReturn($this->createIdentityProvider());

        $serviceProviderFactory = m::mock(ServiceProviderFactory::class);
        $serviceProviderFactory->shouldReceive('create')
            ->withNoArgs()
            ->once()
            ->andReturn($this->createServiceProvider());

        $logger = new NullLogger();

        $responseProcessor = new Processor($logger);
        $keyLoader = new KeyLoader();
        $signatureVerifier = new SignatureVerifier($keyLoader, $logger);
        $postBinding = new PostBinding($responseProcessor, $logger, $signatureVerifier);

        return new SamlCalloutHelper(
            $identityProviderFactory,
            $serviceProviderFactory,
            $postBinding,
            $this->remoteVettingService,
            $logger
        );
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
     * @param Crawler $crawler
     * @param string $state State button to press
     */
    private function postForm(Crawler $crawler, $state)
    {
        $c = $this->client->getResponse()->getContent();
        // Test if on decision page
        $this->assertEquals(200, $this->client->getResponse()->getStatusCode());
        $this->assertContains('Select response', $crawler->filter('h2')->text());

        // Test valid response
        $form = $crawler->selectButton($state)->form();
        $crawler = $this->client->submit($form);
        //$this->client->insulate();

        // Post response
        $form = $crawler->selectButton('Post')->form();

        $crawler = $this->client->submit($form);
    }

    private function logIn()
    {
        $session = $this->client->getContainer()->get('session');

        $firewallContext = 'saml_based';

        $user = Identity::fromData([
            'id' => '12345567890',
            'name_id' => 'name-id',
            'institution' => 'institution',
            'email' => 'name@institution.tld',
            'common_name' => 'Common Name',
            'preferred_locale' => 'nl_NL',
        ]);

        $token = new SamlToken(['ROLE_USER']);
        $token->setUser($user);

        // todo: inject attributes to match against, currently only idp attributes are used
        $token->setAttribute(SamlToken::ATTRIBUTE_SET, AttributeSet::create([]));

        $session->set('_security_'.$firewallContext, serialize($token));
        $session->save();

        $cookie = new Cookie($session->getName(), $session->getId());
        $this->client->getCookieJar()->set($cookie);
    }
}
