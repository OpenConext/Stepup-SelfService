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

use PHPUnit_Framework_TestCase as UnitTest;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Configuration\RemoteVettingConfiguration;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Dto\AttributeLogDto;
use Surfnet\StepupSelfService\SelfServiceBundle\RemoteVetting\Service\IdentityEncrypter;

/**
 * By using a fake IdentityWriter we are able to intercept the encrypted data (that would otherwise
 * be stored to a backend) and perform some sanity checks on it.
 */
class IdentityEncrypterIntegrationTest extends UnitTest
{
    private $encrypter;

    private $config;

    private $writer;

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


    protected function setUp()
    {
        $config = [
            'encryption_public_key' => $this->publicKey,
            'storage_location' => '/tmp',
        ];
        $this->config = new RemoteVettingConfiguration($config, 'v0.0');
        $this->writer = new FakeIdentityWriter();
        $this->encrypter = new IdentityEncrypter($this->config, $this->writer);
    }

    /**
     * Create a simple identity DTO, encrypt and write it. The decrypted data should match that
     * of the data set on the DTO.
     */
    public function test_happy_flow()
    {
        $nameId = 'a-random-nameid@something.else';
        $raw = 'the raw message we could incorporate';

        $data = new AttributeLogDto(['email' => 'johndoe@example.com', 'firstName' => 'John'], $nameId, $raw);
        $this->encrypter->encrypt($data);

        $writtenData = $this->writer->getData();

        // Now decrypt the data with the private key to prove the data is actually retrievable
        $result = $this->decrypt($writtenData, $this->publicKey);

        $serialized = json_decode($result, true);

        $this->assertEquals('johndoe@example.com', $serialized['attributes']['email']);
        $this->assertEquals('John', $serialized['attributes']['firstName']);
    }

    public function test_valid_private_key_required_to_decrypt()
    {
        $nameId = 'a-random-nameid@something.else';
        $raw = 'the raw message we could incorporate';

        $data = new AttributeLogDto(['email' => 'johndoe@example.com', 'firstName' => 'John'], $nameId, $raw);
        $this->encrypter->encrypt($data);

        $writtenData = $this->writer->getData();
        // Now decrypt the data with the private key to prove the data is actually retrievable
        $result = '';

        $invalidPrivateKey = <<<KEY
-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEAyOZnmunnDgF4ivLai3zA7BeI7RDgP1cWp6TLgaLKHnomzE/y
tn93SOl7fnw+vPLdQ3jNIbOiULos9Lt0vyJqhduC5ykPwC/C+oJqM4YBDUvOdtyK
rvKs4/IplUibeB0NC1f/JvayfkT9da+Dx1656PndKDeUHUpho1EsK5ylKYFQCsQj
ktIhb1Z6WEUdDMZee+syPzefSSVaJMiZIRbFfvbcqoQMtmR+1Ua/HuXdBFk53+7G
qWzF/0Rx/Lcf8u97TIAiYTA/4g1sM3KW8YOMCRXjEAAMc04Ok2KnFgFsjefEOLDW
lPOueTp31+HShsjEkhwZ2RMXMV18bPV3wD7XlwIDAQABAoIBAF14teOhyFr/3DHp
5DzaAnjWSmLiYzOVNXkyd20NbLTZaIvO9sJqc/l8iHTFIfuPk9r8rZRsm6yrspb7
UMpNHPrCsxkSRb3JexJK81adloe1KY6r9eqIBmDYHvjSG2O7QIJyoshiJ5IQT00e
KFXugNkgyAEaOtOV8++kqMRx0XaJXKNpx1iJVKJkoFH97FN/bgXNwyh3g3/fV82O
HXhxRFo0shxxIxxQWXZwSNJv2hEkEjPQf0IJYWINX2nrNq/MP2Qacy9ifwvY8RrD
TNLLOq9sPzB0cZcaya+gXHjaHUcZznl7I/xyRIFqcB1NpR9px5kPOpesIdFSbKCY
Glpw5QECgYEA61fNYBt6ALJRbbVD3TljDcl4oWcB9xVvQq4PX75FxrtWocBQ2NF5
ZSyeXZc+nfLfsm2DDwE8pAd64s4j+4kr6c6td3YeehzxdJVr0lKKDTwOTNVT276L
C885u3KS2325RdWU8EbFO6ZjitMTHBME7bl4AKudwaT4VI8qSMyNYbcCgYEA2oip
xOQLne/Anm4pID8FrJorS6VPP0uQjI8FbmQ0MQIUqITSDVRIYLI2z5c63wOFHt8s
DXkIYDOB8u2aoEYkSv+r2idNbanF67KtI74sIBv9wkAD7KNUSEul+y1M3Y3a9enc
wIdJVxZIQRQBb4RUlqVnYkvLSwZdcu6C/VzYuSECgYBwFQTuvKdCJaq6QRXtCiOu
sUEOPWymLEMs0pDn3EqG9zmvF1RprnnOQY26rQlZWA5Jf/Zm8wPaauWPlvV0GkQb
uh7LQioJpjcoevE63ax52RxGY2LoyLqYCT1JlKt0dVADflDMjifo3LmTqCr1t+kk
/qu0Rgd/7f2G6BgPS7wZyQKBgQDLJ7MTW6DFEUYywlwvIwdmXugS6bnTMK9PTAIT
jsnKKZIE23zecVTbt7EmqQNMoVwWyAeO+s5jiF6s9GAD0VEZ+7bKFjFv2h0psTAu
AzPIId10wMglK+1FTDYgXAuSB7bfV6olvV7bu9Yt0ahIAHAWn+uNQMLbmy4ykzJo
0PCUoQKBgQDHfIlHx/0LsZS2z+wqeWO/gSxEJPkmCBq9dm+oJo48vVFPiotXjgyc
qRG2YXye1GXbbgqR8Wj81kWe7X3FWD/SCFY/PQhmxwzjQhkrvgyVGiMr2SkdPwtz
O+A1YzoLHWIeDK53JUjH4kbZejf9Du5m/cegl3FFgP45t2xNHE4nmQ==
-----END RSA PRIVATE KEY-----
KEY;

        $this->assertFalse(openssl_private_decrypt($writtenData, $result, $invalidPrivateKey));
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
