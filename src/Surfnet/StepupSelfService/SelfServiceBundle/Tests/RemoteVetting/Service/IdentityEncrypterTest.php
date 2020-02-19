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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\RemoteVetting\Service;

use Mockery as m;
use PHPUnit_Framework_TestCase as UnitTest;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Configuration\RemoteVettingConfiguration;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Dto\AttributeLogDto;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Service\IdentityEncrypter;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Service\IdentityFilesystemWriter;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Service\IdentityWriterInterface;

class IdentityEncrypterTest extends UnitTest
{
    private $encrypter;

    private $config;

    private $writer;

    private $cert = <<<CERT
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


    protected function setUp()
    {
        $this->config = m::mock(RemoteVettingConfiguration::class);
        $this->writer = m::mock(IdentityWriterInterface::class);
        $this->encrypter = new IdentityEncrypter($this->config, $this->writer);
    }

    public function test_happy_flow()
    {
        $this->config
            ->shouldReceive('getPublicKey')
            ->andReturn($this->cert);

        $this->config
            ->shouldReceive('getVersion')
            ->andReturn('v0.0');

        $this->writer
            ->shouldReceive('write');

        $data = new AttributeLogDto(['email' => 'johndoe@example.com', 'firstName' => 'John']);
        $this->encrypter->encrypt($data, RemoteVettingConfiguration::SOURCE_IRMA);
    }
}
