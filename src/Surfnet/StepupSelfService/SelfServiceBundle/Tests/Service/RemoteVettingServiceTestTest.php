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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service;

use DateTime;
use Mockery as m;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use Surfnet\StepupBundle\Tests\DateTimeHelper;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\Identity;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\ApplicationHelper;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\AttributeMapper;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Configuration\RemoteVettingConfiguration;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\AttributeListDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\RemoteVettingTokenDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Encryption\IdentityEncrypter;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\IdentityProviderFactory;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\RemoteVettingContext;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Value\ProcessId;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVettingService;
use Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\RemoteVetting\Encryption\Decrypter;
use Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\RemoteVetting\Encryption\FakeIdentityWriter;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;

class RemoteVettingServiceTest extends TestCase
{
    /**
     * @var RemoteVettingService
     */
    private $service;


    private $publicKey = <<<CERT
-----BEGIN CERTIFICATE-----
MIIC6jCCAdICCQC9cRx5wiwWOjANBgkqhkiG9w0BAQsFADA3MRwwGgYDVQQDDBNT
ZWxmU2VydmljZSBTQU1MIFNQMRcwFQYDVQQKDA5EZXZlbG9wbWVudCBWTTAeFw0x
ODA3MzAxMjMwNDdaFw0yMzA3MjkxMjMwNDdaMDcxHDAaBgNVBAMME1NlbGZTZXJ2
aWNlIFNBTUwgU1AxFzAVBgNVBAoMDkRldmVsb3BtZW50IFZNMIIBIjANBgkqhkiG
9w0BAQEFAAOCAQ8AMIIBCgKCAQEAqhbI0Xy682DuvWchg6FYnI+DNwLXef2XExM4
YVRBaMMsOZ3rBtQUTMSqYan6SK/BOEXLs0rNiJjyM0dn+F98wg3fv5zIADlvfk3L
BVdcGsrpVfFUWtSa73yMgbROy8/RJADbUJE/HUB3ZmdjdiuD2Cui2aoWwT2HR8uk
Jwmoxiu45IWFPbqPQ7/1mH644JPOWTPLTv4OGGLQo8MNrP1oRCiZ0IEL4CQeGOOj
u5rfIJ0bTVm0UmelT4hGaqZovBMwXp3QV41akJ7UEMEBK2YMnLQy47Xuzi7aTDhJ
lvHcJ8mfH2NbjRh7hJoACVRTvQloxajgkr1iGMiWiiqT0e+YYwIDAQABMA0GCSqG
SIb3DQEBCwUAA4IBAQBwZ0gRHvR8B8KivrXrhWNL9uLvWhEAH7OiDqo+fywkBp5K
EuDJcbbvEPftHunSAGylg7M2xKuBIGamFpp74WDJccrtZ1jJ4qqnacUDRQrTLqqM
ZKqGpFOU0xjKkSxSGRuMtGN9/7er/TeonjQ0XBvjYvTomy3b5aCLVWRvEfKu2g1s
Dd8uhr62RY/HfMgidEt7LHDolkCVg+6JzY3OTcgeHga3cvYObOYPplxw1YPq5+Bq
qxaUW4nfb5DtK33bZBYMeyV6BZtSggc5Z/19aPx/s0bf6ySTUyB3lRqe5d3etCns
4bGidORCl/6EZiXwVcPvmYmxYXqmuNWfps7isUvo
-----END CERTIFICATE-----
CERT;

    private $privateKey = <<<KEY
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEAqhbI0Xy682DuvWchg6FYnI+DNwLXef2XExM4YVRBaMMsOZ3r
BtQUTMSqYan6SK/BOEXLs0rNiJjyM0dn+F98wg3fv5zIADlvfk3LBVdcGsrpVfFU
WtSa73yMgbROy8/RJADbUJE/HUB3ZmdjdiuD2Cui2aoWwT2HR8ukJwmoxiu45IWF
PbqPQ7/1mH644JPOWTPLTv4OGGLQo8MNrP1oRCiZ0IEL4CQeGOOju5rfIJ0bTVm0
UmelT4hGaqZovBMwXp3QV41akJ7UEMEBK2YMnLQy47Xuzi7aTDhJlvHcJ8mfH2Nb
jRh7hJoACVRTvQloxajgkr1iGMiWiiqT0e+YYwIDAQABAoIBAF+J5Msm0Kwcan2h
DEYvvuJSClZAFmDDfLSOO0EQXp1F4/WJKpbvUWe9oCazn45sio/dRIo1HjX4EzOS
jGgK2rz1phSvL/hQSrwbXkplw6qZB2/q2oMaoNycjR/d89Svqr4abRZYP6diqq6u
rEOYNbqa6CJzU8y/jtlZHZ9/4XlN8035QNJ3YIi3qVe3cCr6IOahUGOayWNaW+0q
vLBhWdbaER5aHiUdcZPrJfNhepb2Ob9djizqpWo8u9WyYNpiExjm1Ov6IAQhxkc7
uAvJIE7W39Ag4wHNHHj+WkctG+KBEym3/i2SDAddUP5H6FGMzPQPdoJK2XArrE0B
p5Tun0ECgYEA1ot9Vz7YbMOqGvok/GQyVuV8MTRC12iPlwoOV3HKNG9TfclglRzg
csp83rJ13tz8NyN93GQpjOkCvdQinJGk/kR6h9eCi2l2HPGNMrZH7qY+2cQvf6J5
KTGI1sAi4DqHJ9u0AyaQdu2ieh3HwgI8+PWBFn3dBR5xKeHIh/59hRsCgYEAyvRG
W+xpVRlM1XoLPMn5Z2yUpI6mieaD3jmNQSC0OuxdxlIZVtyqBF3rFQw1V/74bS3X
aOxtwelGQ2PfWnjo4uLoWqUoIN0ZAn+9yKzMla/5y1jEhyFcaUQc8QGmp+wOjDgQ
NHM23VSAr7Q+G3EMQmjlURC45Il66mnrkcZUFlkCgYEAoAMzPZHauuwH/8zXLwLP
5K2Nvej7fUs35O+UGLX+mLL7M1KxXSVHZXYOQc4aSVjKJ5mp8mkl8DmNWOVR1zJt
O1L5jD042R+T/yxNIih/Z8fIEoTW5DvaX9XY+Eoe+NvOF/UtwjfOAVVlG+0AInum
3AvG9m5zHLFCt3j1JjCxj0cCgYEAv4IrFjiJ2DwsbVBhZDYt+nLR/EmDSqLTEhH6
gVcr2mIJxsbXlEhawg4hctX3TBaTMurL1f0rQIwvug12yDdJgjadDFPF/uTC4cHK
Qp8T2beZHVGg+OX4/nfAW4a0TMYJoDSSzftd7RH88E9DP7+30r6KjKkb3sL/0kyq
df7Qf9kCgYAi1vf0bc6GgWf0CA+7NtZivl4Pw1aZEZI7tKY2cC95KKTycPhxSpq5
g72XdHAp+gaJoSBledEYMJfE5Xsdf5r0F1v5xDe87Dn+zT7UXpw4JrDE16jBKwv1
pTLyJ51aerY27qJEtZ3JqbCux853aa2cxLIoje+5Kxso33bPe0EXGg==
-----END RSA PRIVATE KEY-----
KEY;
    /**
     * @var RemoteVettingContext
     */
    private $remoteVettingContext;
    /**
     * @var AttributeMapper
     */
    private $attributeMapper;
    /**
     * @var m\LegacyMockInterface|m\MockInterface|IdentityProviderFactory
     */
    private $identityProviderFactory;
    /**
     * @var m\LegacyMockInterface|m\MockInterface|ApplicationHelper
     */
    private $applicationHelper;
    /**
     * @var m\LegacyMockInterface|m\MockInterface|RemoteVettingConfiguration
     */
    private $remoteVettingConfiguration;
    /**
     * @var FakeIdentityWriter
     */
    private $fakeIdentityWriter;
    /**
     * @var IdentityEncrypter
     */
    private $identityEncrypter;
    /**
     * @var TestLogger
     */
    private $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->remoteVettingConfiguration = m::mock(RemoteVettingConfiguration::class);
        $this->applicationHelper = m::mock(ApplicationHelper::class);
        $this->fakeIdentityWriter = new FakeIdentityWriter();
        $this->logger = new TestLogger();
        $session = new Session(new MockFileSessionStorage());

        $this->identityEncrypter = new IdentityEncrypter($this->remoteVettingConfiguration, $this->fakeIdentityWriter);
        $this->attributeMapper = new AttributeMapper($this->remoteVettingConfiguration);
        $this->remoteVettingContext = new RemoteVettingContext($session);

        $now =  new DateTime();
        $mockTime = new DateTime('@1614004537',$now->getTimezone());
        DateTimeHelper::setCurrentTime($mockTime);

        $this->service = new RemoteVettingService(
            $this->remoteVettingContext,
            $this->attributeMapper,
            $this->identityEncrypter,
            $this->applicationHelper,
            $this->logger
        );
    }

    public function test_happy_flow()
    {
        $processId = '4a46493c-7387-4c04-b491-a41f7323d73a';
        $identityId = '95515f44-71a2-4dc1-8e8f-e7e4021ee65b';
        $secondFactorId = 'dace3819-35e9-4205-8538-04bb09bdd479';

        $nameId = "john.doe@example.com";
        $institution = "stepup.example.com";
        $email = "johndoe@example.com";
        $commonName = "John Doe";
        $preferredLocale = "nl_NL";
        
        $processId = ProcessId::create($processId);

        $identity = Identity::fromData([
             'id' => $identityId,
            'name_id' => $nameId,
            'institution' => $institution,
            'email' => $email,
            'common_name' => $commonName,
            'preferred_locale' => $preferredLocale,
        ]);

        $localAttributes = new AttributeListDto(['email' => ['john@example.com'], 'firstName' => ['Johnie'], "familyName" => ["Doe"], "telephone" => ["0612345678"]], $nameId);
        $externalAttributes = new AttributeListDto(['emailAddress' => ['johndoe@example.com'], 'givenName' => ['John'], "familyName" => ["Doe"], "fullName" => ["John Doe"]], $nameId);
        $remarks = "This seems a pretty decent match";

        $this->remoteVettingConfiguration->shouldReceive("getAttributeMapping")
            ->with('mock')
            ->andReturn([
                'email' => 'emailAddress',
                'firstName' => 'givenName',
                'familyName' => 'familyName',
            ]);

        $this->applicationHelper->shouldReceive('getApplicationVersion')
            ->andReturn('1.0.0');

        $this->remoteVettingConfiguration->shouldReceive("getPublicKey")
            ->andReturn($this->publicKey);

        $matches = $this->attributeMapper->map('mock', $localAttributes, $externalAttributes);

        // todo: add matching result!

        $token = RemoteVettingTokenDto::create($identityId, $secondFactorId);
        $this->service->start('mock', $token);
        $this->service->startValidation($processId);
        $this->service->finishValidation($processId, $externalAttributes);
        $remoteVettingToken = $this->service->done($processId, $identity, $localAttributes, $matches, $remarks);

        // test token result
        $this->assertSame($identityId, $remoteVettingToken->getIdentityId());
        $this->assertSame($secondFactorId, $remoteVettingToken->getSecondFactorId());

        // test encrypted result
        $result = Decrypter::decrypt($this->fakeIdentityWriter->getData(), $this->privateKey);
        $this->assertSame('{"attribute-data":{"local-attributes":{"nameId":"john.doe@example.com","attributes":{"email":["john@example.com"],"firstName":["Johnie"],"familyName":["Doe"],"telephone":["0612345678"]}},"remote-attributes":{"nameId":"john.doe@example.com","attributes":{"emailAddress":["johndoe@example.com"],"givenName":["John"],"familyName":["Doe"],"fullName":["John Doe"]}},"matching-results":{"email":{"local":{"name":"email","value":["john@example.com"]},"remote":{"name":"emailAddress","value":["johndoe@example.com"]},"is-valid":false,"remarks":""},"firstName":{"local":{"name":"firstName","value":["Johnie"]},"remote":{"name":"givenName","value":["John"]},"is-valid":false,"remarks":""},"familyName":{"local":{"name":"familyName","value":["Doe"]},"remote":{"name":"familyName","value":["Doe"]},"is-valid":false,"remarks":""}}},"remarks":"This seems a pretty decent match","name-id":"john.doe@example.com","institution":"stepup.example.com","remote-vetting-source":"mock","application-version":"1.0.0","time":"2021-02-22T14:35:37+00:00"}', $result);

        // test logs
        $this->assertSame([
            [
                'level' => 'info',
                'message' => 'Starting an remote vetting process for the provided token',
                'context' => [],
            ],[
                'level' => 'info',
                'message' => 'Starting an remote vetting authentication for the current process',
                'context' => [],
            ],[
                'level' => 'info',
                'message' => 'Finishing a remote vetting authentication for the current process',
                'context' => [],
            ],[
                'level' => 'info',
                'message' => 'Saving the encrypted assertion to the filesystem',
                'context' => [],
            ],[
                'level' => 'info',
                'message' => 'Finished the remote vetting process for the current process',
                'context' => [],
            ],
        ], $this->logger->records);
    }
}
