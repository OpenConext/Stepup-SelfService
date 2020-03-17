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

namespace Surfnet\StepupSelfService\SelfServiceBundle\Tests\Service\RemoteVetting\Encryption;

use Mockery as m;
use PHPUnit_Framework_TestCase as UnitTest;
use PHPUnit_Framework_Error_Warning as Warning;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Surfnet\StepupSelfService\SelfServiceBundle\Exception\InvalidArgumentException;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Configuration\RemoteVettingConfiguration;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Dto\AttributeListDto;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Encryption\IdentityEncrypter;
use Surfnet\StepupSelfService\SelfServiceBundle\Service\RemoteVetting\Encryption\IdentityWriterInterface;

class IdentityEncrypterTest extends UnitTest
{
    private $encrypter;

    private $config;

    private $writer;

    private $privateKey = <<<RSA_PRIVATE_KEY
-----BEGIN RSA PRIVATE KEY-----
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
5ni9gQKCAQEAzL+UiWPmRDFDA383Jlms9gYhbDR8FWiyW4KJNZhQq3IO6S7hqZct
HF2lG+qgKKGkm1+jFAaQiGbAFYLQIBtNodEGo5br8xYblnAV8obl/wM0uHbHNqSv
O5hg3oNOPyGTJu/YNDSeacPYLoRkayJyZ8NAnxBYWIEqngwFGGFwVreVcpFmOzCS
2KTi6LDyo1Kb1Z8Nm/pSZLqCHh7qGV1LY2I+mcXm8MPyNzX6R+PrGU0T9kjeRImY
N1a6TBJJ5vEkPB2AYAbXOVtunj7smQIQmS3tMY51oErSkYotZcyzkYaeG1TWpPh6
5Wdogou+q9B0LOZTn7Bjeq+s/n7Tq8BXCQKCAQBeJWBMQx2ehbJcd3L+uF88sAcZ
Kg2WBJCYwvEY3pOzysW6UA/7VjSfSuR8d1Zl9uDsZHH71+OB591+isPXR2h0/wWP
52iBW2HXVMlHks5DUEPGJSBypN7uCH6UEV0R8SxmJIj+qR3xd+asayTKdZ4Y2kC8
UWsuomapGnkW+o1huxwvFd19t415+at0eU0mazyOVS5wwPHPSbWlVOOG6fWEHeVj
KdTbCDiEEHMfLfPCr9I/EKrCahOIdAe0AVONvp6ko58DnRIIYSgOAb6F80K1PQ3v
L8Iy5XBC+r/6qs6davFMso5LqDzwaTnIqzD5CSaqy1/qgp03MNnzbpA4c2r2
-----END RSA PRIVATE KEY-----
RSA_PRIVATE_KEY;


// RSA Private key in PEM format
    private $cert = <<<RSA_PUBLIC_KEY
-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAu9Z0jMDV3KfMV2C9I3d7
9QDVuFtQTCIGHqZQJtraz6+6IEG6jUafNtDf3LYjJOBKB4QD1dASfzPZPP7d1pcs
+uof+SUAJ+UqtV5k/SbPEKQLUAQoetNMuA4JWr64Dna7Hiv3v/MjaAikYUwkbVOQ
e33V4BChrOQZTmAWrgy4F0e2s79CPzUL4C3OCHpgWq4zSEOHQDsNXHfnGNOHXN73
iearA+MvR601u5UXSKibOCQGRAdsGf+OaPyWT2k1U1uQ0yoBN3lBjgFU1YMHA1w+
AeIbS+a7Ddh/uEu5I6sk0P3D5yXTganoEtOkft6HKAan7unFb85qVKRTfBGwOwg+
xCD689KLhznu3u0z+4qnjtvFCRQyc0DMwTV+ITyYjp7rcXS/arTuJQ67QZIwevaE
o7EiEqNIC3RicPgd4y8Klt0E6SLMMkyFv/o+ez9Y/wNkrSDROaYwpEko7rgxtnPW
iujh4yz7FBq0ZrGRclfwinvm9GSqMVqB/reGRLeGKqJ1Z/CDCLx9432R1yKyTIa4
vmDkexbldg5pyyCwfYPgXKFfOepkfEMxrqVs4lTxDmvjOa2hHP4KUwOjZIAXpSnw
qbP8ufor/kz0/Yi3a7j/pkutXtjm6t4kAKwN/wcMcPAwBBeNfFiP75pqjcjYnSQE
ZveiwwfkcpIUZNWwrQJUiQkCAwEAAQ==
-----END PUBLIC KEY-----
RSA_PUBLIC_KEY;

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

        $data = new AttributeListDto(['email' => ['johndoe@example.com'], 'firstName' => ['John']], $nameId);
        $this->encrypter->encrypt($data->serialize());

        // Assert result
        $decryptedData = Decrypter::decrypt($encryptedData, $this->privateKey);
        $this->assertSame($data->serialize(), $decryptedData);
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

        $data = new AttributeListDto(['email' => ['johndoe@example.com'], 'firstName' => ['John']], $nameId, $raw);
        $this->encrypter->encrypt($data->serialize());

        // Assert result
        $decryptedData = Decrypter::decrypt($encryptedData, $this->privateKey);
        $this->assertSame($data->serialize(), $decryptedData);
    }

    /**
     * @test
     */
    public function an_invalid_key_should_fail_non_string()
    {
        $this->config
            ->shouldReceive('getPublicKey')
            ->andReturn(8373292782);

        $nameId = 'a-random-nameid@something.else';
        $raw = 'the raw message we could incorporate';
        $data = new AttributeListDto(['email' => ['johndoe@example.com'], 'firstName' => ['John']], $nameId, $raw);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid input was provided to the encrypt method');
        $this->encrypter->encrypt($data->serialize());
    }

    /**
     * @test
     */
    public function an_invalid_key_should_fail_bad_key_format()
    {
        $this->config
            ->shouldReceive('getPublicKey')
            ->andReturn('invalid key');

        $nameId = 'a-random-nameid@something.else';
        $raw = 'the raw message we could incorporate';

        $data = new AttributeListDto(['email' => ['johndoe@example.com'], 'firstName' => ['John']], $nameId, $raw);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Reading RSA public key failed');
        $this->encrypter->encrypt($data->serialize());
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
}
