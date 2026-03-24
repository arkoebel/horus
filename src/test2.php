<?php

require_once 'lib/horus_common.php';
require_once 'lib/horus_xml.php';
$xml = file_get_contents('samples/xmldsig.xml');



$pk = 'MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC818lNInuf5vegxvwa7SQTt1mgs8sm8trx0+TdlalTqruZf10sdPm0KKyW2MBJ9ggaORtiNe0mbJuOLyxKfymmyirmEdfZUKaZJpNumzG/UBK+tp6qEzr0Ow8+ZpucYkUavpsx8MuH3uxlBekDqkVxeWbnzMWine1dFOU1mfHSj6oNSuxD18cTX5l7bo6zcpPgomb4VSjxSk0VKroNfNtLCXof+5ReI2maYnlgMhm/4eJ5v7/qPX4Q/r97glPLJJj8bEsv+RPaVdZzv/TBn51RVR+R1AlXgDrm8g9235XJhutGxEdBM0qszunAvWGyebr6eW7+9opKp9rUym9Agu9TAgMBAAECggEAeMMt0gv5LrqPJNvbIIUCCzG3OVOA2Ll5ViiBNUqd7AlEytZuCR4NCS7xn82guiuB5vMaFeYSb/4IRBbaphFH24dxg4tpk2lGAK5wnczVNVjJg/hY4r5FdyXFi8wmOw2Ez6OQr5EjNxJR7hCngFaE7hoKurVytZl0z4+rPGnkARgPfGK1kzecbV69KeyWzsnyFpPvHFTuiHZLOuwmeelGMWefdpJCIATe+pkro9veg/ocjTjF5cmhk3mAddicOxbCCu7ucFrAzDFzmDhM/tB6E2LSDDOl6lJCeBNkSiRiFXCKxsHbJtaP1diZritvhgnFg/XyEUhi8R7rtKeULqtUQQKBgQD0v7dbHRBPIiJVPtjY9Yy/w7f05kRKCZ0YFpv2V4MA37UH/Lo9mDgCVqsfNgStolSTZs+jTwivnpqGFVMWAnIZKLVqCWq7rLNCENAcIqNWF7qKF/i++5fuqRZFg1IEABk8UB1ULqfylL8Sgs8dXt+Y0Vm+5ITFcatXdPKkvvLCMQKBgQDFhiaLukzdRgQKXMC1ROZAM+UrfksBJueqY5S62nf6MM0OOo3Y/R7alKzR6xf3tDEVBkX87NEAEGtocxEKktcX8D8ceqLQAXInc9uMAOraEe5O3qVXS/Bt5X8P7mS6UqE/pkyh4FXqpHo574FVNpRh0sFc6Z+24xDisPplTUREwwKBgQDkEwQ61Aqus5Bq//XzuF9BFJIIlcxtcigCmo8cMNDTr6RznP+xBnirNTiiDSSu6ecGtXgpJy1g+tvkt1qF2CGbcGQePEhKO9WQazqD/YNYZyReK5iR4MLklI08mfOD5tOdcMrj99ZqKFMmXN/E7vRO5EhNq4ZOuG6DQWgcPhTbAQKBgGsVwYv7InTL4qDjjC45/kJMYC/mNi+Xsfz0I8vxaR4gmurd380F3VZPSCo+NC48aGenkQYANYa9YB2uVEzRMv9tZinAasguIH83Fo2eabR0CCiGGEltiBlsVCiE6+L/rR/evqj8AFhHd2Q1bn6OKn+mTOJcXhQ+ogbkP7vv2dUVAoGAOdCfPdrSWHRnguUPMDsdySE6PAh+c89L0osifWhUDz+5Y7lfJ23i6OvlnBu7XxDE1yr/6mrPVw56cl7YafYyMTjbxY3ugTDykRYImqc3w0aSwgcRye4Nz3i1/Z9tZtzyvPTazYZvVwt0DHiTGZ6wq1kQNQhR6LdSCQvV58xLNP4=';

$cert = 'MIIDXTCCAkWgAwIBAgIJAKxuEB5C5PirMA0GCSqGSIb3DQEBCwUAMEUxCzAJBgNVBAYTAkFVMRMwEQYDVQQIDApTb21lLVN0YXRlMSEwHwYDVQQKDBhJbnRlcm5ldCBXaWRnaXRzIFB0eSBMdGQwHhcNMjQwOTI0MTkyMTQzWhcNMjUwOTI0MTkyMTQzWjBFMQswCQYDVQQGEwJBVTETMBEGA1UECAwKU29tZS1TdGF0ZTEhMB8GA1UECgwYSW50ZXJuZXQgV2lkZ2l0cyBQdHkgTHRkMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvNfJTSJ7n+b3oMb8Gu0kE7dZoLPLJvLa8dPk3ZWpU6q7mX9dLHT5tCisltjASfYIGjkbYjXtJmybji8sSn8ppsoq5hHX2VCmmSaTbpsxv1ASvraeqhM69DsPPmabnGJFGr6bMfDLh97sZQXpA6pFcXlm58zFop3tXRTlNZnx0o+qDUrsQ9fHE1+Ze26Os3KT4KJm+FUo8UpNFSq6DXzbSwl6H/uUXiNpmmJ5YDIZv+Hieb+/6j1+EP6/e4JTyySY/GxLL/kT2lXWc7/0wZ+dUVUfkdQJV4A65vIPdt+VyYbrRsRHQTNKrM7pwL1hsnm6+nlu/vaKSqfa1MpvQILvUwIDAQABo1AwTjAdBgNVHQ4EFgQUMA6fuebK7no96qem9iHPixNKWLcwHwYDVR0jBBgwFoAUMA6fuebK7no96qem9iHPixNKWLcwDAYDVR0TBAUwAwEB/zANBgkqhkiG9w0BAQsFAAOCAQEAflpqU9v6T/pxzMg5qTons/euWGrelAU/UzCOqrTVmD/CZp1IdlE1XgJpsq+lZwaxX1JRKmcz2JIrsM9w+Lv/2Lio+usaquS8zvhxzoG2z10gG59iVcA9OiZxNOanoYmRqS4Llb5sutXsXlGMoVOS8k553syTMUV7mTCBCBwUjl5KZGHUqFYs0GVvK/fVB10EyvWv3Vs9FHeeTFXRHM5rtb1RxAl3ZVUiua4IVuBBP+Yw88ubMXn0c3owEbWWU2JccKZYlQMc7v9EIuWvyJVQc4Bd4CUguLZRpYHlanI1xHdzPql54I+4bqPiJef0vphkDTMmge1SKp0VH+Fn7oMdIA==';
//create new private and public key
$new_key_pair = openssl_pkey_new(array(
    "private_key_bits" => 2048,
    "private_key_type" => OPENSSL_KEYTYPE_RSA,
));
openssl_pkey_export($new_key_pair, $private_key_pem);

$details = openssl_pkey_get_details($new_key_pair);
$public_key_pem = $details['key'];

$rsa = 'MIIEowIBAAKCAQEAvNfJTSJ7n+b3oMb8Gu0kE7dZoLPLJvLa8dPk3ZWpU6q7mX9dLHT5tCisltjASfYIGjkbYjXtJmybji8sSn8ppsoq5hHX2VCmmSaTbpsxv1ASvraeqhM69DsPPmabnGJFGr6bMfDLh97sZQXpA6pFcXlm58zFop3tXRTlNZnx0o+qDUrsQ9fHE1+Ze26Os3KT4KJm+FUo8UpNFSq6DXzbSwl6H/uUXiNpmmJ5YDIZv+Hieb+/6j1+EP6/e4JTyySY/GxLL/kT2lXWc7/0wZ+dUVUfkdQJV4A65vIPdt+VyYbrRsRHQTNKrM7pwL1hsnm6+nlu/vaKSqfa1MpvQILvUwIDAQABAoIBAHjDLdIL+S66jyTb2yCFAgsxtzlTgNi5eVYogTVKnewJRMrWbgkeDQku8Z/NoLorgebzGhXmEm/+CEQW2qYRR9uHcYOLaZNpRgCucJ3M1TVYyYP4WOK+RXclxYvMJjsNhM+jkK+RIzcSUe4Qp4BWhO4aCrq1crWZdM+Pqzxp5AEYD3xitZM3nG1evSnsls7J8haT7xxU7oh2SzrsJnnpRjFnn3aSQiAE3vqZK6Pb3oP6HI04xeXJoZN5gHXYnDsWwgru7nBawMwxc5g4TP7QehNi0gwzpepSQngTZEokYhVwisbB2ybWj9XYma4rb4YJxYP18hFIYvEe67SnlC6rVEECgYEA9L+3Wx0QTyIiVT7Y2PWMv8O39OZESgmdGBab9leDAN+1B/y6PZg4AlarHzYEraJUk2bPo08Ir56ahhVTFgJyGSi1aglqu6yzQhDQHCKjVhe6ihf4vvuX7qkWRYNSBAAZPFAdVC6n8pS/EoLPHV7fmNFZvuSExXGrV3TypL7ywjECgYEAxYYmi7pM3UYEClzAtUTmQDPlK35LASbnqmOUutp3+jDNDjqN2P0e2pSs0esX97QxFQZF/OzRABBraHMRCpLXF/A/HHqi0AFyJ3PbjADq2hHuTt6lV0vwbeV/D+5kulKhP6ZMoeBV6qR6Oe+BVTaUYdLBXOmftuMQ4rD6ZU1ERMMCgYEA5BMEOtQKrrOQav/187hfQRSSCJXMbXIoApqPHDDQ06+kc5z/sQZ4qzU4og0krunnBrV4KSctYPrb5Ldahdghm3BkHjxISjvVkGs6g/2DWGckXiuYkeDC5JSNPJnzg+bTnXDK4/fWaihTJlzfxO70TuRITauGTrhug0FoHD4U2wECgYBrFcGL+yJ0y+Kg44wuOf5CTGAv5jYvl7H89CPL8WkeIJrq3d/NBd1WT0gqPjQuPGhnp5EGADWGvWAdrlRM0TL/bWYpwGrILiB/NxaNnmm0dAgohhhJbYgZbFQohOvi/60f3r6o/ABYR3dkNW5+jip/pkziXF4UPqIG5D+779nVFQKBgDnQnz3a0lh0Z4LlDzA7HckhOjwIfnPPS9KLIn1oVA8/uWO5Xydt4ujr5Zwbu18QxNcq/+pqz1cOenJe2Gn2MjE428WN7oEw8pEWCJqnN8NGksIHEcnuDc94tf2fbWbc8rz02s2Gb1cLdAx4kxmesKtZEDUIUei3UgkL1efMSzT+';
$params = array(
        'signatureMethod' => 'RSA',
        'key'=>$pk,
        'certificate' => $cert,
        'algorithm' => 'SHA256',
        'targetXpath'=> '//*[local-name()=\'LAU\']',
        'digests' => array(
            array(
                'targetURI'=>'',
                'sourceXpath' =>'//*'
            )
        ),
        "createTargetElement"=> array(
            'parentXpath'=>'//*[local-name()=\'LAU\']',
            'elementName'=>'LAU2',
            'elementNameSpace'=>'urn:swift:saa:xsd:saa.2.0'
        )
    );

echo HorusXml::sign($xml, $params) . "\n";
echo json_encode($params, JSON_PRETTY_PRINT);
