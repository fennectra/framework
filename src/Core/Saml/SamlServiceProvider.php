<?php

namespace Fennec\Core\Saml;

/**
 * SAML 2.0 Service Provider implementation.
 *
 * Handles:
 * - Generating AuthnRequest (HTTP-Redirect binding)
 * - Parsing and validating SAMLResponse (HTTP-POST binding)
 * - Generating SP metadata XML
 * - Single Logout (SLO) request generation
 *
 * Usage:
 *   $config = SamlConfig::fromEnv();
 *   $sp = new SamlServiceProvider($config);
 *
 *   // Redirect user to IdP
 *   $url = $sp->buildAuthnRequestUrl($relayState);
 *   header('Location: ' . $url);
 *
 *   // Handle callback (POST from IdP)
 *   $user = $sp->processResponse($_POST['SAMLResponse'], $requestId);
 */
class SamlServiceProvider
{
    private SamlConfig $config;

    public function __construct(SamlConfig $config)
    {
        $this->config = $config;

        if (empty($this->config->idpSsoUrl)) {
            throw new SamlException('idpSsoUrl is required');
        }
    }

    /**
     * Generate an AuthnRequest and return the redirect URL.
     *
     * The request ID is included in the returned array so the caller can
     * store it in session for InResponseTo validation.
     *
     * @return array{url: string, id: string} Redirect URL and request ID
     */
    public function buildAuthnRequestUrl(?string $relayState = null): array
    {
        $id = '_' . bin2hex(random_bytes(16));
        $issueInstant = gmdate('Y-m-d\TH:i:s\Z');

        $xml = <<<XML
<samlp:AuthnRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="{$id}"
    Version="2.0"
    IssueInstant="{$issueInstant}"
    Destination="{$this->config->idpSsoUrl}"
    AssertionConsumerServiceURL="{$this->config->spAcsUrl}"
    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
    <saml:Issuer>{$this->config->spEntityId}</saml:Issuer>
    <samlp:NameIDPolicy
        Format="urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress"
        AllowCreate="true"/>
</samlp:AuthnRequest>
XML;

        // Deflate + base64 encode for HTTP-Redirect binding
        $deflated = gzdeflate($xml);
        if ($deflated === false) {
            throw new SamlException('Failed to deflate AuthnRequest');
        }
        $encoded = base64_encode($deflated);

        $params = [
            'SAMLRequest' => $encoded,
        ];

        if ($relayState !== null) {
            $params['RelayState'] = $relayState;
        }

        // Sign the request if SP private key is available
        if ($this->config->spPrivateKey !== null) {
            $params['SigAlg'] = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
            $query = http_build_query($params);
            $signature = $this->signRedirectBinding($query);
            $params['Signature'] = $signature;
        }

        $url = $this->config->idpSsoUrl . '?' . http_build_query($params);

        return ['url' => $url, 'id' => $id];
    }

    /**
     * Process a SAMLResponse from the IdP (HTTP-POST binding).
     *
     * @param string      $samlResponse  Base64-encoded SAMLResponse from POST
     * @param string|null $expectedRequestId  Original AuthnRequest ID for InResponseTo validation
     */
    public function processResponse(string $samlResponse, ?string $expectedRequestId = null): SamlUser
    {
        $response = new SamlResponse($samlResponse, $this->config);

        return $response->validate($expectedRequestId);
    }

    /**
     * Generate SP metadata XML.
     *
     * This XML should be served at your metadata endpoint so that IdPs
     * can automatically configure the SP.
     */
    public function generateMetadata(): string
    {
        $entityId = htmlspecialchars($this->config->spEntityId, ENT_XML1);
        $acsUrl = htmlspecialchars($this->config->spAcsUrl, ENT_XML1);

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<md:EntityDescriptor
    xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
    entityID="{$entityId}">
    <md:SPSSODescriptor
        AuthnRequestsSigned="false"
        WantAssertionsSigned="{$this->boolToString($this->config->wantAssertionsSigned)}"
        protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        <md:NameIDFormat>urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress</md:NameIDFormat>
        <md:AssertionConsumerService
            Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
            Location="{$acsUrl}"
            index="1"
            isDefault="true"/>
XML;

        // SLO endpoint
        if ($this->config->spSloUrl !== null) {
            $sloUrl = htmlspecialchars($this->config->spSloUrl, ENT_XML1);
            $xml .= <<<XML

        <md:SingleLogoutService
            Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
            Location="{$sloUrl}"/>
XML;
        }

        // SP certificate for encryption
        if ($this->config->spCertificate !== null) {
            $certClean = $this->cleanCertificate($this->config->spCertificate);
            $xml .= <<<XML

        <md:KeyDescriptor use="encryption">
            <ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
                <ds:X509Data>
                    <ds:X509Certificate>{$certClean}</ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </md:KeyDescriptor>
XML;
        }

        $xml .= <<<XML

    </md:SPSSODescriptor>
</md:EntityDescriptor>
XML;

        return $xml;
    }

    /**
     * Build a Single Logout Request URL (HTTP-Redirect binding).
     *
     * @return array{url: string, id: string}
     */
    public function buildLogoutRequestUrl(string $nameId, ?string $sessionIndex = null): array
    {
        $sloUrl = $this->config->idpSloUrl;
        if ($sloUrl === null) {
            throw new SamlException('IdP SLO URL is not configured');
        }

        $id = '_' . bin2hex(random_bytes(16));
        $issueInstant = gmdate('Y-m-d\TH:i:s\Z');
        $nameIdEscaped = htmlspecialchars($nameId, ENT_XML1);

        $sessionIndexXml = '';
        if ($sessionIndex !== null) {
            $sessionIndexEscaped = htmlspecialchars($sessionIndex, ENT_XML1);
            $sessionIndexXml = <<<XML
    <samlp:SessionIndex>{$sessionIndexEscaped}</samlp:SessionIndex>
XML;
        }

        $xml = <<<XML
<samlp:LogoutRequest
    xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
    ID="{$id}"
    Version="2.0"
    IssueInstant="{$issueInstant}"
    Destination="{$sloUrl}">
    <saml:Issuer>{$this->config->spEntityId}</saml:Issuer>
    <saml:NameID>{$nameIdEscaped}</saml:NameID>
{$sessionIndexXml}
</samlp:LogoutRequest>
XML;

        $deflated = gzdeflate($xml);
        if ($deflated === false) {
            throw new SamlException('Failed to deflate LogoutRequest');
        }

        $params = ['SAMLRequest' => base64_encode($deflated)];

        $url = $sloUrl . '?' . http_build_query($params);

        return ['url' => $url, 'id' => $id];
    }

    /**
     * Get the current configuration.
     */
    public function getConfig(): SamlConfig
    {
        return $this->config;
    }

    // ─── Private helpers ─────────────────────────────────────────

    /**
     * Sign a query string for HTTP-Redirect binding.
     */
    private function signRedirectBinding(string $query): string
    {
        $privateKey = openssl_pkey_get_private($this->config->spPrivateKey);
        if ($privateKey === false) {
            throw new SamlException('Failed to load SP private key');
        }

        $signature = '';
        $signed = openssl_sign($query, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (!$signed) {
            throw new SamlException('Failed to sign AuthnRequest');
        }

        return base64_encode($signature);
    }

    /**
     * Strip PEM headers/footers and whitespace from certificate.
     */
    private function cleanCertificate(string $cert): string
    {
        return str_replace(
            [
                '-----BEGIN CERTIFICATE-----',
                '-----END CERTIFICATE-----',
                "\r",
                "\n",
                ' ',
            ],
            '',
            $cert
        );
    }

    private function boolToString(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
