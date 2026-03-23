<?php

namespace Tests\Unit;

use Fennec\Core\Encryption\Encrypter;
use PHPUnit\Framework\TestCase;

class EncrypterTest extends TestCase
{
    private string $testKey;

    protected function setUp(): void
    {
        // 32 bytes for AES-256
        $this->testKey = random_bytes(32);
        Encrypter::setKey($this->testKey);
    }

    protected function tearDown(): void
    {
        // Reset static key
        $ref = new \ReflectionClass(Encrypter::class);
        $prop = $ref->getProperty('key');
        $prop->setValue(null, null);
    }

    public function testEncryptReturnsEncPrefix(): void
    {
        $encrypted = Encrypter::encrypt('hello');

        $this->assertStringStartsWith('enc:', $encrypted);
    }

    public function testDecryptReturnsOriginalValue(): void
    {
        $original = 'sensitive data 123';
        $encrypted = Encrypter::encrypt($original);
        $decrypted = Encrypter::decrypt($encrypted);

        $this->assertSame($original, $decrypted);
    }

    public function testDecryptNonEncryptedReturnsAsIs(): void
    {
        $plain = 'not encrypted';
        $result = Encrypter::decrypt($plain);

        $this->assertSame($plain, $result);
    }

    public function testIsEncryptedDetectsPrefix(): void
    {
        $this->assertTrue(Encrypter::isEncrypted('enc:abc123'));
        $this->assertFalse(Encrypter::isEncrypted('plain text'));
        $this->assertFalse(Encrypter::isEncrypted(''));
    }

    public function testEncryptProducesDifferentCiphertexts(): void
    {
        $value = 'same input';
        $a = Encrypter::encrypt($value);
        $b = Encrypter::encrypt($value);

        // Different IVs should produce different ciphertexts
        $this->assertNotSame($a, $b);

        // But both decrypt to the same value
        $this->assertSame($value, Encrypter::decrypt($a));
        $this->assertSame($value, Encrypter::decrypt($b));
    }

    public function testDecryptWithWrongKeyFails(): void
    {
        $encrypted = Encrypter::encrypt('secret');

        // Change key
        Encrypter::setKey(random_bytes(32));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed');
        Encrypter::decrypt($encrypted);
    }

    public function testDecryptTamperedDataFails(): void
    {
        $encrypted = Encrypter::encrypt('secret');

        // Tamper with the base64 payload
        $tampered = 'enc:' . base64_encode(random_bytes(50));

        $this->expectException(\RuntimeException::class);
        Encrypter::decrypt($tampered);
    }

    public function testGenerateKeyProduces44CharBase64(): void
    {
        $key = Encrypter::generateKey();

        // 32 bytes → 44 chars in base64
        $this->assertSame(44, strlen($key));
        $this->assertNotFalse(base64_decode($key, true));
        $this->assertSame(32, strlen(base64_decode($key, true)));
    }

    public function testEncryptHandlesUtf8(): void
    {
        $original = 'Données sensibles: émojis 🔒 accents éàü';
        $encrypted = Encrypter::encrypt($original);
        $decrypted = Encrypter::decrypt($encrypted);

        $this->assertSame($original, $decrypted);
    }

    public function testEncryptHandlesEmptyString(): void
    {
        $encrypted = Encrypter::encrypt('');
        $decrypted = Encrypter::decrypt($encrypted);

        $this->assertSame('', $decrypted);
    }

    public function testDecryptInvalidBase64Fails(): void
    {
        $this->expectException(\RuntimeException::class);
        Encrypter::decrypt('enc:not-valid-base64!!!');
    }
}
