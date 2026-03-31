<?php

namespace Fennec\Core\Saml;

/**
 * Parses and validates a SAML 2.0 Response.
 *
 * Handles:
 * - XML signature verification (RSA-SHA256)
 * - Assertion conditions (NotBefore, NotOnOrAfter, Audience)
 * - NameID and attribute extraction
 * - InResponseTo validation
 */
class SamlResponse
{
    private \DOMDocument $document;
    private \DOMXPath $xpath;
    private SamlConfig $config;

    private const NS_SAMLP = 'urn:oasis:names:tc:SAML:2.0:protocol';
    private const NS_SAML = 'urn:oasis:names:tc:SAML:2.0:assertion';
    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    public function __construct(string $samlResponse, SamlConfig $config)
    {
        $this->config = $config;

        $xml = base64_decode($samlResponse, true);
        if ($xml === false) {
            throw new SamlException('Invalid base64 SAML response');
        }

        $this->document = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $this->document->loadXML($xml);
        libxml_use_internal_errors($previousErrors);

        if (!$loaded) {
            throw new SamlException('Invalid XML in SAML response');
        }

        $this->xpath = new \DOMXPath($this->document);
        $this->xpath->registerNamespace('samlp', self::NS_SAMLP);
        $this->xpath->registerNamespace('saml', self::NS_SAML);
        $this->xpath->registerNamespace('ds', self::NS_DS);
    }

    /**
     * Validate the SAML response and return the authenticated user.
     *
     * @param string|null $expectedRequestId  The AuthnRequest ID to validate InResponseTo
     */
    public function validate(?string $expectedRequestId = null): SamlUser
    {
        $this->validateStatus();
        $this->validateDestination();

        if ($expectedRequestId !== null) {
            $this->validateInResponseTo($expectedRequestId);
        }

        if ($this->config->wantAssertionsSigned) {
            $this->validateSignature();
        }

        $this->validateConditions();

        return $this->extractUser();
    }

    /**
     * Check the SAML status code is Success.
     */
    private function validateStatus(): void
    {
        $statusCode = $this->xpath->query('//samlp:Response/samlp:Status/samlp:StatusCode/@Value');

        if ($statusCode === false || $statusCode->length === 0) {
            throw new SamlException('Missing StatusCode in SAML response');
        }

        $value = $statusCode->item(0)->nodeValue;
        if ($value !== 'urn:oasis:names:tc:SAML:2.0:status:Success') {
            $message = $this->xpath->query('//samlp:Response/samlp:Status/samlp:StatusMessage');
            $detail = ($message !== false && $message->length > 0) ? $message->item(0)->nodeValue : $value;
            throw new SamlException('SAML authentication failed: ' . $detail);
        }
    }

    /**
     * Validate the Destination attribute matches our ACS URL.
     */
    private function validateDestination(): void
    {
        $response = $this->xpath->query('//samlp:Response');
        if ($response === false || $response->length === 0) {
            throw new SamlException('Missing Response element');
        }

        /** @var \DOMElement $responseElement */
        $responseElement = $response->item(0);
        $destination = $responseElement->getAttribute('Destination');
        if ($destination !== '' && $destination !== $this->config->spAcsUrl) {
            throw new SamlException(
                'Destination mismatch: expected ' . $this->config->spAcsUrl . ', got ' . $destination
            );
        }
    }

    /**
     * Validate InResponseTo matches the original AuthnRequest ID.
     */
    private function validateInResponseTo(string $expectedRequestId): void
    {
        $response = $this->xpath->query('//samlp:Response');
        if ($response === false || $response->length === 0) {
            return;
        }

        /** @var \DOMElement $responseElement */
        $responseElement = $response->item(0);
        $inResponseTo = $responseElement->getAttribute('InResponseTo');
        if ($inResponseTo !== '' && $inResponseTo !== $expectedRequestId) {
            throw new SamlException(
                'InResponseTo mismatch: expected ' . $expectedRequestId . ', got ' . $inResponseTo
            );
        }
    }

    /**
     * Validate XML signature on the Response or Assertion.
     */
    private function validateSignature(): void
    {
        $idpCert = $this->config->idpCertificate;
        if ($idpCert === null) {
            throw new SamlException('IdP certificate is required when wantAssertionsSigned is true');
        }

        // Find the Signature element (on Response or Assertion)
        $signatureNodes = $this->xpath->query(
            '//samlp:Response/ds:Signature | //saml:Assertion/ds:Signature'
        );

        if ($signatureNodes === false || $signatureNodes->length === 0) {
            throw new SamlException('No signature found in SAML response');
        }

        /** @var \DOMElement $signatureNode */
        $signatureNode = $signatureNodes->item(0);

        // Extract SignedInfo, SignatureValue, DigestValue
        $signedInfo = $this->xpath->query('ds:SignedInfo', $signatureNode);
        $signatureValue = $this->xpath->query('ds:SignatureValue', $signatureNode);

        if (
            $signedInfo === false || $signedInfo->length === 0 ||
            $signatureValue === false || $signatureValue->length === 0
        ) {
            throw new SamlException('Malformed XML signature');
        }

        // Get the canonicalized SignedInfo
        /** @var \DOMElement $signedInfoElement */
        $signedInfoElement = $signedInfo->item(0);
        $canonicalSignedInfo = $signedInfoElement->C14N(true, false);

        // Decode signature
        $signature = base64_decode(
            preg_replace('/\s+/', '', $signatureValue->item(0)->nodeValue),
            true
        );
        if ($signature === false) {
            throw new SamlException('Invalid base64 signature value');
        }

        // Determine algorithm
        $algorithmNode = $this->xpath->query('ds:SignedInfo/ds:SignatureMethod/@Algorithm', $signatureNode);
        $algorithm = ($algorithmNode !== false && $algorithmNode->length > 0)
            ? $algorithmNode->item(0)->nodeValue
            : '';

        $opensslAlgo = match ($algorithm) {
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256' => OPENSSL_ALGO_SHA256,
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha384' => OPENSSL_ALGO_SHA384,
            'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512' => OPENSSL_ALGO_SHA512,
            'http://www.w3.org/2000/09/xmldsig#rsa-sha1' => OPENSSL_ALGO_SHA1,
            default => throw new SamlException('Unsupported signature algorithm: ' . $algorithm),
        };

        // Load IdP certificate
        $publicKey = openssl_pkey_get_public($idpCert);
        if ($publicKey === false) {
            throw new SamlException('Failed to load IdP certificate');
        }

        // Verify signature
        $result = openssl_verify($canonicalSignedInfo, $signature, $publicKey, $opensslAlgo);
        if ($result !== 1) {
            throw new SamlException('XML signature verification failed');
        }

        // Validate digest
        $this->validateDigest($signatureNode);
    }

    /**
     * Validate the digest value in the signature reference.
     */
    private function validateDigest(\DOMElement $signatureNode): void
    {
        $referenceNodes = $this->xpath->query('ds:SignedInfo/ds:Reference', $signatureNode);
        if ($referenceNodes === false || $referenceNodes->length === 0) {
            return;
        }

        /** @var \DOMElement $reference */
        $reference = $referenceNodes->item(0);
        $uri = $reference->getAttribute('URI');

        // Find the referenced element
        $referencedId = ltrim($uri, '#');
        $referencedElement = null;

        if ($referencedId !== '') {
            $elements = $this->xpath->query("//*[@ID='{$referencedId}']");
            if ($elements !== false && $elements->length > 0) {
                $referencedElement = $elements->item(0);
            }
        } else {
            // Empty URI means the root document
            $referencedElement = $this->document->documentElement;
        }

        if ($referencedElement === null) {
            throw new SamlException('Signature reference target not found: ' . $uri);
        }

        // Get expected digest
        $digestValueNode = $this->xpath->query('ds:DigestValue', $reference);
        if ($digestValueNode === false || $digestValueNode->length === 0) {
            return;
        }
        $expectedDigest = base64_decode(
            preg_replace('/\s+/', '', $digestValueNode->item(0)->nodeValue),
            true
        );

        // Get digest algorithm
        $digestMethodNode = $this->xpath->query('ds:DigestMethod/@Algorithm', $reference);
        $digestAlgo = ($digestMethodNode !== false && $digestMethodNode->length > 0)
            ? $digestMethodNode->item(0)->nodeValue
            : 'http://www.w3.org/2001/04/xmlenc#sha256';

        $hashAlgo = match ($digestAlgo) {
            'http://www.w3.org/2001/04/xmlenc#sha256' => 'sha256',
            'http://www.w3.org/2001/04/xmlenc#sha512' => 'sha512',
            'http://www.w3.org/2000/09/xmldsig#sha1' => 'sha1',
            default => 'sha256',
        };

        // Canonicalize the referenced element (excluding the Signature)
        /** @var \DOMElement $referencedElement */
        $canonical = $referencedElement->C14N(true, false);

        $actualDigest = hash($hashAlgo, $canonical, true);

        if ($expectedDigest !== $actualDigest) {
            throw new SamlException('Digest value mismatch — response may have been tampered with');
        }
    }

    /**
     * Validate assertion conditions (time bounds and audience).
     */
    private function validateConditions(): void
    {
        $conditions = $this->xpath->query('//saml:Assertion/saml:Conditions');
        if ($conditions === false || $conditions->length === 0) {
            return; // Conditions are optional
        }

        /** @var \DOMElement $cond */
        $cond = $conditions->item(0);
        $now = time();
        $skew = 120; // 2 minutes clock skew tolerance

        $notBefore = $cond->getAttribute('NotBefore');
        if ($notBefore !== '') {
            $notBeforeTs = strtotime($notBefore);
            if ($notBeforeTs !== false && $now < $notBeforeTs - $skew) {
                throw new SamlException('Assertion is not yet valid (NotBefore: ' . $notBefore . ')');
            }
        }

        $notOnOrAfter = $cond->getAttribute('NotOnOrAfter');
        if ($notOnOrAfter !== '') {
            $notOnOrAfterTs = strtotime($notOnOrAfter);
            if ($notOnOrAfterTs !== false && $now >= $notOnOrAfterTs + $skew) {
                throw new SamlException('Assertion has expired (NotOnOrAfter: ' . $notOnOrAfter . ')');
            }
        }

        // Audience restriction
        $audiences = $this->xpath->query(
            '//saml:Assertion/saml:Conditions/saml:AudienceRestriction/saml:Audience'
        );
        if ($audiences !== false && $audiences->length > 0) {
            $found = false;
            foreach ($audiences as $audience) {
                if ($audience->nodeValue === $this->config->spEntityId) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new SamlException('Audience restriction does not include our entity ID');
            }
        }
    }

    /**
     * Extract user data from the assertion.
     */
    private function extractUser(): SamlUser
    {
        // NameID
        $nameIdNode = $this->xpath->query('//saml:Assertion/saml:Subject/saml:NameID');
        if ($nameIdNode === false || $nameIdNode->length === 0) {
            throw new SamlException('No NameID found in assertion');
        }

        /** @var \DOMElement $nameIdElement */
        $nameIdElement = $nameIdNode->item(0);
        $nameId = $nameIdElement->nodeValue;
        $nameIdFormat = $nameIdElement->getAttribute('Format') ?: null;

        // SessionIndex
        $sessionIndexNode = $this->xpath->query(
            '//saml:Assertion/saml:AuthnStatement/@SessionIndex'
        );
        $sessionIndex = ($sessionIndexNode !== false && $sessionIndexNode->length > 0)
            ? $sessionIndexNode->item(0)->nodeValue
            : null;

        // Attributes
        $attributes = $this->extractAttributes();

        return SamlUser::fromAssertion($nameId, $nameIdFormat, $sessionIndex, $attributes);
    }

    /**
     * Extract all SAML attributes from the assertion.
     *
     * @return array<string, string[]>
     */
    private function extractAttributes(): array
    {
        $attributes = [];
        $attrNodes = $this->xpath->query(
            '//saml:Assertion/saml:AttributeStatement/saml:Attribute'
        );

        if ($attrNodes === false) {
            return $attributes;
        }

        /** @var \DOMElement $attr */
        foreach ($attrNodes as $attr) {
            $name = $attr->getAttribute('Name');
            if ($name === '') {
                continue;
            }

            $values = [];
            $valueNodes = $this->xpath->query('saml:AttributeValue', $attr);
            if ($valueNodes !== false) {
                foreach ($valueNodes as $valueNode) {
                    $values[] = $valueNode->nodeValue;
                }
            }

            $attributes[$name] = $values;
        }

        return $attributes;
    }

    /**
     * Get the raw parsed XML document.
     */
    public function getDocument(): \DOMDocument
    {
        return $this->document;
    }
}
