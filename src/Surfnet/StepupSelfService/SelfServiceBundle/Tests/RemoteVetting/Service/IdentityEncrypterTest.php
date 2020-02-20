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
use PHPUnit_Framework_Error_Warning as Warning;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Configuration\RemoteVettingConfiguration;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Dto\AttributeLogDto;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Service\IdentityEncrypter;
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

    /**
     * @test
     */
    public function happy_flow_should_succeed()
    {
        $this->config
            ->shouldReceive('getPublicKey')
            ->andReturn($this->cert);

        $this->writer
            ->shouldReceive('write')
            ->withArgs(function ($data) use (&$encryptedData ){
                $encryptedData = $data;
                return true;
            });

        $nameId = 'a-random-nameid@something.else';
        $raw = 'the raw message we could incorporate';

        $data = new AttributeLogDto(['email' => 'johndoe@example.com', 'firstName' => 'John'], $nameId, $raw);
        $this->encrypter->encrypt($data);

        // Assert result
        $decryptedData = $this->decrypt($encryptedData, $this->cert);
        $this->assertSame(json_encode($data->jsonSerialize()), $decryptedData);
    }

    /**
     * @test
     */
    public function a_large_chunk_should_succeed()
    {
        $this->config
            ->shouldReceive('getPublicKey')
            ->andReturn($this->cert);

        $this->writer
            ->shouldReceive('write')
            ->withArgs(function ($data) use (&$encryptedData ){
                $encryptedData = $data;
                return true;
            });

        $nameId = 'a-random-nameid@something.else';
        $raw = $this->generateRandomString(5000);

        $data = new AttributeLogDto(['email' => 'johndoe@example.com', 'firstName' => 'John'], $nameId, $raw);
        $this->encrypter->encrypt($data);

        // Assert result
        $decryptedData = $this->decrypt($encryptedData, $this->cert);
        $this->assertSame(json_encode($data->jsonSerialize()), $decryptedData);
    }

    /**
     * @test
     */
    public function an_invalid_key_should_fail()
    {
        $this->expectException(Warning::class);
        $this->expectExceptionMessage('openssl_x509_read(): supplied parameter cannot be coerced into an X509 certificate!');

        $this->config
            ->shouldReceive('getPublicKey')
            ->andReturn('invalid key');

        $this->writer
            ->shouldReceive('write')
            ->withArgs(function ($data) use (&$encryptedData ){
                $encryptedData = $data;
                return true;
            });

        $nameId = 'a-random-nameid@something.else';
        $raw = 'the raw message we could incorporate';

        $data = new AttributeLogDto(['email' => 'johndoe@example.com', 'firstName' => 'John'], $nameId, $raw);
        $this->encrypter->encrypt($data);
    }

    private function generateRandomString($length)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"\\}\'';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * @param string $data
     * @param string $password
     * @return string
     * @throws \Exception
     */
    private function decrypt($data, $password)
    {
        $decrypter = new XMLSecurityKey(XMLSecurityKey::AES256_CBC);
        $decrypter->loadKey($password, false, true);
        return $decrypter->decryptData($data);
    }
}
