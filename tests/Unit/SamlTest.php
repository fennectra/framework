<?php

namespace Tests\Unit;

use Fennec\Core\Saml\SamlConfig;
use Fennec\Core\Saml\SamlException;
use Fennec\Core\Saml\SamlResponse;
use Fennec\Core\Saml\SamlServiceProvider;
use Fennec\Core\Saml\SamlUser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the SAML 2.0 Service Provider module.
 */
class SamlTest extends TestCase
{
    private \OpenSSLAsymmetricKey $idpPrivateKey;
    private string $idpCertificate = '';

    protected function setUp(): void
    {
        // Generate a self-signed certificate for the test IdP
        $config = $this->opensslConfig();
        $keyPair = openssl_pkey_new($config);
        if ($keyPair === false) {
            $this->markTestSkipped('OpenSSL key generation not available: ' . openssl_error_string());
        }
        $this->idpPrivateKey = $keyPair;

        $csr = openssl_csr_new(['commonName' => 'Test IdP'], $keyPair, $config);
        $cert = openssl_csr_sign($csr, null, $keyPair, 365, $config);
        openssl_x509_export($cert, $this->idpCertificate);
    }

    /**
     * @return array<string, mixed>
     */
    private function opensslConfig(): array
    {
        $config = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        // Windows needs explicit openssl.cnf path
        if (PHP_OS_FAMILY === 'Windows') {
            $iniDir = dirname((string) php_ini_loaded_file());
            $cnf = $iniDir . '/extras/ssl/openssl.cnf';
            if (is_file($cnf)) {
                $config['config'] = $cnf;
            }
        }

        return $config;
    }

    // ─── SamlConfig ──────────────────────────────────────────────

    public function testConfigFromArray(): void
    {
        $config = SamlConfig::fromArray([
            'sp_entity_id' => 'https://myapp.com',
            'sp_acs_url' => 'https://myapp.com/saml/acs',
            'idp_entity_id' => 'https://idp.example.com',
            'idp_sso_url' => 'https://idp.example.com/sso',
            'idp_certificate' => $this->idpCertificate,
        ]);

        $this->assertSame('https://myapp.com', $config->spEntityId);
        $this->assertSame('https://myapp.com/saml/acs', $config->spAcsUrl);
        $this->assertSame('https://idp.example.com', $config->idpEntityId);
        $this->assertSame('https://idp.example.com/sso', $config->idpSsoUrl);
        $this->assertTrue($config->wantAssertionsSigned);
    }

    public function testConfigDefaultValues(): void
    {
        $config = SamlConfig::fromArray([
            'sp_entity_id' => 'https://myapp.com',
            'sp_acs_url' => 'https://myapp.com/saml/acs',
        ]);

        $this->assertNull($config->spSloUrl);
        $this->assertNull($config->spPrivateKey);
        $this->assertNull($config->spCertificate);
        $this->assertNull($config->idpSloUrl);
        $this->assertNull($config->idpCertificate);
        $this->assertTrue($config->wantAssertionsSigned);
    }

    public function testConfigWantSignedCanBeDisabled(): void
    {
        $config = SamlConfig::fromArray([
            'sp_entity_id' => 'https://myapp.com',
            'sp_acs_url' => 'https://myapp.com/saml/acs',
            'want_assertions_signed' => false,
        ]);

        $this->assertFalse($config->wantAssertionsSigned);
    }

    // ─── SamlUser ────────────────────────────────────────────────

    public function testUserFromAssertionExtractsEmail(): void
    {
        $user = SamlUser::fromAssertion(
            'user@example.com',
            'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress',
            'session-123',
            [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => ['user@example.com'],
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname' => ['John'],
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname' => ['Doe'],
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => ['John Doe'],
            ],
        );

        $this->assertSame('user@example.com', $user->nameId);
        $this->assertSame('user@example.com', $user->email);
        $this->assertSame('John', $user->firstName);
        $this->assertSame('Doe', $user->lastName);
        $this->assertSame('John Doe', $user->displayName);
        $this->assertSame('session-123', $user->sessionIndex);
    }

    public function testUserFromAssertionFallbackAttributeNames(): void
    {
        $user = SamlUser::fromAssertion(
            'user123',
            null,
            null,
            [
                'email' => ['fallback@example.com'],
                'givenName' => ['Jane'],
                'sn' => ['Smith'],
            ],
        );

        $this->assertSame('fallback@example.com', $user->email);
        $this->assertSame('Jane', $user->firstName);
        $this->assertSame('Smith', $user->lastName);
    }

    public function testUserFromAssertionHandlesMissingAttributes(): void
    {
        $user = SamlUser::fromAssertion('user123', null, null, []);

        $this->assertSame('user123', $user->nameId);
        $this->assertNull($user->email);
        $this->assertNull($user->firstName);
        $this->assertNull($user->lastName);
        $this->assertNull($user->displayName);
    }

    public function testUserToOAuthUser(): void
    {
        $user = SamlUser::fromAssertion(
            'user@example.com',
            null,
            null,
            [
                'email' => ['user@example.com'],
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname' => ['John'],
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname' => ['Doe'],
            ],
        );

        $oauthUser = $user->toOAuthUser();

        $this->assertSame('user@example.com', $oauthUser->id);
        $this->assertSame('user@example.com', $oauthUser->email);
        $this->assertSame('John Doe', $oauthUser->name);
        $this->assertNull($oauthUser->avatar);
        $this->assertSame('saml', $oauthUser->provider);
    }

    // ─── SamlServiceProvider ─────────────────────────────────────

    public function testBuildAuthnRequestUrlContainsRequiredParams(): void
    {
        $sp = $this->createServiceProvider();
        $result = $sp->buildAuthnRequestUrl('https://myapp.com/dashboard');

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('id', $result);

        $url = $result['url'];
        $this->assertStringStartsWith('https://idp.example.com/sso?', $url);
        $this->assertStringContainsString('SAMLRequest=', $url);
        $this->assertStringContainsString('RelayState=', $url);

        // ID must start with underscore (SAML spec)
        $this->assertStringStartsWith('_', $result['id']);
    }

    public function testBuildAuthnRequestUrlWithoutRelayState(): void
    {
        $sp = $this->createServiceProvider();
        $result = $sp->buildAuthnRequestUrl();

        $this->assertStringNotContainsString('RelayState=', $result['url']);
    }

    public function testAuthnRequestXmlContainsIssuerAndAcs(): void
    {
        $sp = $this->createServiceProvider();
        $result = $sp->buildAuthnRequestUrl();

        // Decode the SAMLRequest param
        $parsed = [];
        parse_str(parse_url($result['url'], PHP_URL_QUERY), $parsed);
        $xml = gzinflate(base64_decode($parsed['SAMLRequest']));

        $this->assertStringContainsString('https://myapp.com', $xml);
        $this->assertStringContainsString('https://myapp.com/saml/acs', $xml);
        $this->assertStringContainsString($result['id'], $xml);
    }

    public function testGenerateMetadataContainsRequiredElements(): void
    {
        $sp = $this->createServiceProvider();
        $metadata = $sp->generateMetadata();

        $this->assertStringContainsString('EntityDescriptor', $metadata);
        $this->assertStringContainsString('SPSSODescriptor', $metadata);
        $this->assertStringContainsString('AssertionConsumerService', $metadata);
        $this->assertStringContainsString('https://myapp.com', $metadata);
        $this->assertStringContainsString('https://myapp.com/saml/acs', $metadata);
        $this->assertStringContainsString('urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST', $metadata);
    }

    public function testGenerateMetadataIsValidXml(): void
    {
        $sp = $this->createServiceProvider();
        $metadata = $sp->generateMetadata();

        $doc = new \DOMDocument();
        $loaded = $doc->loadXML($metadata);
        $this->assertTrue($loaded, 'Generated metadata is not valid XML');
    }

    public function testConstructorRequiresIdpSsoUrl(): void
    {
        $config = SamlConfig::fromArray([
            'sp_entity_id' => 'https://myapp.com',
            'sp_acs_url' => 'https://myapp.com/saml/acs',
            'idp_sso_url' => '', // empty
        ]);

        $this->expectException(SamlException::class);
        $this->expectExceptionMessage('idpSsoUrl is required');

        new SamlServiceProvider($config);
    }

    public function testBuildLogoutRequestUrlRequiresSloConfig(): void
    {
        $sp = $this->createServiceProvider();

        $this->expectException(SamlException::class);
        $this->expectExceptionMessage('SLO URL is not configured');

        $sp->buildLogoutRequestUrl('user@example.com');
    }

    public function testBuildLogoutRequestUrlWithSloConfig(): void
    {
        $config = SamlConfig::fromArray([
            'sp_entity_id' => 'https://myapp.com',
            'sp_acs_url' => 'https://myapp.com/saml/acs',
            'idp_entity_id' => 'https://idp.example.com',
            'idp_sso_url' => 'https://idp.example.com/sso',
            'idp_slo_url' => 'https://idp.example.com/slo',
        ]);

        $sp = new SamlServiceProvider($config);
        $result = $sp->buildLogoutRequestUrl('user@example.com', 'session-123');

        $this->assertStringStartsWith('https://idp.example.com/slo?', $result['url']);
        $this->assertStringStartsWith('_', $result['id']);
        $this->assertStringContainsString('SAMLRequest=', $result['url']);
    }

    // ─── SamlResponse ────────────────────────────────────────────

    public function testProcessResponseRejectsInvalidBase64(): void
    {
        $sp = $this->createServiceProvider();

        $this->expectException(SamlException::class);
        $this->expectExceptionMessage('Invalid base64');

        $sp->processResponse('!!!not-base64!!!');
    }

    public function testProcessResponseRejectsInvalidXml(): void
    {
        $sp = $this->createServiceProvider();

        $this->expectException(SamlException::class);
        $this->expectExceptionMessage('Invalid XML');

        $sp->processResponse(base64_encode('not xml'));
    }

    public function testProcessResponseRejectsFailedStatus(): void
    {
        $xml = $this->buildSamlResponse(
            status: 'urn:oasis:names:tc:SAML:2.0:status:Requester',
            statusMessage: 'Authentication failed',
        );

        $config = $this->createConfig(wantSigned: false);
        $sp = new SamlServiceProvider($config);

        $this->expectException(SamlException::class);
        $this->expectExceptionMessage('SAML authentication failed');

        $sp->processResponse(base64_encode($xml));
    }

    public function testProcessResponseRejectsWrongDestination(): void
    {
        $xml = $this->buildSamlResponse(
            destination: 'https://wrong-app.com/saml/acs',
        );

        $config = $this->createConfig(wantSigned: false);
        $sp = new SamlServiceProvider($config);

        $this->expectException(SamlException::class);
        $this->expectExceptionMessage('Destination mismatch');

        $sp->processResponse(base64_encode($xml));
    }

    public function testProcessResponseRejectsExpiredAssertion(): void
    {
        $xml = $this->buildSamlResponse(
            notOnOrAfter: gmdate('Y-m-d\TH:i:s\Z', time() - 3600),
        );

        $config = $this->createConfig(wantSigned: false);
        $sp = new SamlServiceProvider($config);

        $this->expectException(SamlException::class);
        $this->expectExceptionMessage('Assertion has expired');

        $sp->processResponse(base64_encode($xml));
    }

    public function testProcessResponseRejectsWrongAudience(): void
    {
        $xml = $this->buildSamlResponse(
            audience: 'https://wrong-audience.com',
        );

        $config = $this->createConfig(wantSigned: false);
        $sp = new SamlServiceProvider($config);

        $this->expectException(SamlException::class);
        $this->expectExceptionMessage('Audience restriction does not include our entity ID');

        $sp->processResponse(base64_encode($xml));
    }

    public function testProcessResponseExtractsUserSuccessfully(): void
    {
        $xml = $this->buildSamlResponse();

        $config = $this->createConfig(wantSigned: false);
        $sp = new SamlServiceProvider($config);
        $user = $sp->processResponse(base64_encode($xml));

        $this->assertInstanceOf(SamlUser::class, $user);
        $this->assertSame('user@example.com', $user->nameId);
        $this->assertSame('user@example.com', $user->email);
        $this->assertSame('John', $user->firstName);
        $this->assertSame('Doe', $user->lastName);
    }

    public function testProcessResponseValidatesInResponseTo(): void
    {
        $xml = $this->buildSamlResponse(inResponseTo: '_request-abc');

        $config = $this->createConfig(wantSigned: false);
        $sp = new SamlServiceProvider($config);

        $this->expectException(SamlException::class);
        $this->expectExceptionMessage('InResponseTo mismatch');

        $sp->processResponse(base64_encode($xml), '_request-different');
    }

    public function testProcessResponseAcceptsMatchingInResponseTo(): void
    {
        $xml = $this->buildSamlResponse(inResponseTo: '_request-abc');

        $config = $this->createConfig(wantSigned: false);
        $sp = new SamlServiceProvider($config);
        $user = $sp->processResponse(base64_encode($xml), '_request-abc');

        $this->assertSame('user@example.com', $user->nameId);
    }

    public function testProcessResponseRequiresSignatureWhenConfigured(): void
    {
        $xml = $this->buildSamlResponse();

        $config = $this->createConfig(wantSigned: true);
        $sp = new SamlServiceProvider($config);

        $this->expectException(SamlException::class);
        $this->expectExceptionMessage('No signature found');

        $sp->processResponse(base64_encode($xml));
    }

    // ─── Helpers ─────────────────────────────────────────────────

    private function createServiceProvider(): SamlServiceProvider
    {
        return new SamlServiceProvider($this->createConfig());
    }

    private function createConfig(bool $wantSigned = false): SamlConfig
    {
        return SamlConfig::fromArray([
            'sp_entity_id' => 'https://myapp.com',
            'sp_acs_url' => 'https://myapp.com/saml/acs',
            'idp_entity_id' => 'https://idp.example.com',
            'idp_sso_url' => 'https://idp.example.com/sso',
            'idp_certificate' => $this->idpCertificate,
            'want_assertions_signed' => $wantSigned,
        ]);
    }

    /**
     * Build a minimal valid SAML Response XML for testing.
     */
    private function buildSamlResponse(
        string $status = 'urn:oasis:names:tc:SAML:2.0:status:Success',
        ?string $statusMessage = null,
        string $destination = 'https://myapp.com/saml/acs',
        string $audience = 'https://myapp.com',
        ?string $notOnOrAfter = null,
        ?string $inResponseTo = null,
    ): string {
        $id = '_' . bin2hex(random_bytes(8));
        $assertionId = '_' . bin2hex(random_bytes(8));
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $notOnOrAfter = $notOnOrAfter ?? gmdate('Y-m-d\TH:i:s\Z', time() + 3600);
        $inResponseToAttr = $inResponseTo ? " InResponseTo=\"{$inResponseTo}\"" : '';
        $statusMessageXml = $statusMessage
            ? "<samlp:StatusMessage>{$statusMessage}</samlp:StatusMessage>"
            : '';

        return <<<XML
<samlp:Response
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="{$id}"
    Version="2.0"
    IssueInstant="{$now}"
    Destination="{$destination}"{$inResponseToAttr}>
    <saml:Issuer>https://idp.example.com</saml:Issuer>
    <samlp:Status>
        <samlp:StatusCode Value="{$status}"/>
        {$statusMessageXml}
    </samlp:Status>
    <saml:Assertion ID="{$assertionId}" Version="2.0" IssueInstant="{$now}">
        <saml:Issuer>https://idp.example.com</saml:Issuer>
        <saml:Subject>
            <saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress">user@example.com</saml:NameID>
            <saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
                <saml:SubjectConfirmationData NotOnOrAfter="{$notOnOrAfter}" Recipient="{$destination}"/>
            </saml:SubjectConfirmation>
        </saml:Subject>
        <saml:Conditions NotBefore="{$now}" NotOnOrAfter="{$notOnOrAfter}">
            <saml:AudienceRestriction>
                <saml:Audience>{$audience}</saml:Audience>
            </saml:AudienceRestriction>
        </saml:Conditions>
        <saml:AuthnStatement AuthnInstant="{$now}" SessionIndex="session-xyz">
            <saml:AuthnContext>
                <saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef>
            </saml:AuthnContext>
        </saml:AuthnStatement>
        <saml:AttributeStatement>
            <saml:Attribute Name="http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress">
                <saml:AttributeValue>user@example.com</saml:AttributeValue>
            </saml:Attribute>
            <saml:Attribute Name="http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname">
                <saml:AttributeValue>John</saml:AttributeValue>
            </saml:Attribute>
            <saml:Attribute Name="http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname">
                <saml:AttributeValue>Doe</saml:AttributeValue>
            </saml:Attribute>
        </saml:AttributeStatement>
    </saml:Assertion>
</samlp:Response>
XML;
    }
}
