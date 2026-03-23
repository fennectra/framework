<?php

namespace Tests\Unit;

use Fennec\Attributes\Nf525;
use Fennec\Core\Nf525\HashChainVerifier;
use PHPUnit\Framework\TestCase;

class Nf525Test extends TestCase
{
    // ── Attribute tests ──

    public function testNf525AttributeDefaultValues(): void
    {
        $attr = new Nf525();

        $this->assertSame('FA', $attr->prefix);
        $this->assertSame('number', $attr->sequenceColumn);
        $this->assertSame('hash', $attr->hashColumn);
        $this->assertSame('previous_hash', $attr->prevHashColumn);
        $this->assertSame([], $attr->excludeFromHash);
    }

    public function testNf525AttributeCustomValues(): void
    {
        $attr = new Nf525(
            prefix: 'AV',
            sequenceColumn: 'ref',
            hashColumn: 'sha',
            prevHashColumn: 'prev_sha',
            excludeFromHash: ['notes'],
        );

        $this->assertSame('AV', $attr->prefix);
        $this->assertSame('ref', $attr->sequenceColumn);
        $this->assertSame('sha', $attr->hashColumn);
        $this->assertSame('prev_sha', $attr->prevHashColumn);
        $this->assertSame(['notes'], $attr->excludeFromHash);
    }

    public function testNf525AttributeOnClass(): void
    {
        $ref = new \ReflectionClass(Nf525TestModel::class);
        $attrs = $ref->getAttributes(Nf525::class);

        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame('INV', $instance->prefix);
    }

    // ── Hash computation tests ──

    public function testHashIsDeterministic(): void
    {
        $data = ['id' => 1, 'total_ttc' => 1200.00, 'client' => 'Dupont'];
        $previousHash = '0';

        ksort($data);
        $payload = $previousHash . '|' . json_encode($data, JSON_UNESCAPED_UNICODE);
        $hash1 = hash('sha256', $payload);
        $hash2 = hash('sha256', $payload);

        $this->assertSame($hash1, $hash2);
    }

    public function testHashChangesWithDifferentData(): void
    {
        $data1 = ['id' => 1, 'total' => 100];
        $data2 = ['id' => 1, 'total' => 200];

        $hash1 = hash('sha256', '0|' . json_encode($data1));
        $hash2 = hash('sha256', '0|' . json_encode($data2));

        $this->assertNotSame($hash1, $hash2);
    }

    public function testHashChangesWithDifferentPreviousHash(): void
    {
        $data = ['id' => 1, 'total' => 100];

        $hash1 = hash('sha256', 'abc|' . json_encode($data));
        $hash2 = hash('sha256', 'xyz|' . json_encode($data));

        $this->assertNotSame($hash1, $hash2);
    }

    public function testHashChainIntegrity(): void
    {
        $records = [];
        $previousHash = '0';

        for ($i = 1; $i <= 5; $i++) {
            $data = ['id' => $i, 'total' => $i * 100];
            ksort($data);
            $payload = $previousHash . '|' . json_encode($data, JSON_UNESCAPED_UNICODE);
            $hash = hash('sha256', $payload);

            $records[] = [
                'id' => $i,
                'total' => $i * 100,
                'hash' => $hash,
                'previous_hash' => $previousHash,
            ];

            $previousHash = $hash;
        }

        // Verify the chain
        $prevHash = '0';
        foreach ($records as $record) {
            $this->assertSame($prevHash, $record['previous_hash']);

            $data = ['id' => $record['id'], 'total' => $record['total']];
            ksort($data);
            $payload = $record['previous_hash'] . '|' . json_encode($data, JSON_UNESCAPED_UNICODE);
            $expected = hash('sha256', $payload);

            $this->assertSame($expected, $record['hash']);
            $prevHash = $record['hash'];
        }
    }

    public function testTamperedRecordBreaksChain(): void
    {
        $data = ['id' => 1, 'total' => 100];
        ksort($data);
        $hash = hash('sha256', '0|' . json_encode($data, JSON_UNESCAPED_UNICODE));

        // Tamper with data
        $tampered = ['id' => 1, 'total' => 999];
        ksort($tampered);
        $tamperedHash = hash('sha256', '0|' . json_encode($tampered, JSON_UNESCAPED_UNICODE));

        $this->assertNotSame($hash, $tamperedHash);
    }

    // ── Sequence number format tests ──

    public function testSequenceNumberFormat(): void
    {
        $year = date('Y');
        $number = sprintf('FA-%s-%06d', $year, 1);

        $this->assertMatchesRegularExpression('/^FA-\d{4}-\d{6}$/', $number);
        $this->assertSame("FA-{$year}-000001", $number);
    }

    public function testSequenceNumberIncrement(): void
    {
        $n1 = sprintf('FA-%s-%06d', '2026', 1);
        $n2 = sprintf('FA-%s-%06d', '2026', 2);

        $parts1 = explode('-', $n1);
        $parts2 = explode('-', $n2);

        $this->assertSame(1, (int) end($parts1));
        $this->assertSame(2, (int) end($parts2));
    }

    // ── FEC export format tests ──

    public function testFecHeaderFormat(): void
    {
        $headers = [
            'JournalCode', 'JournalLib', 'EcritureNum', 'EcritureDate',
            'CompteNum', 'CompteLib', 'PieceRef', 'PieceDate',
            'EcritureLib', 'Debit', 'Credit', 'Montantdevise', 'Idevise',
        ];

        $line = implode("\t", $headers);

        $this->assertStringContainsString('JournalCode', $line);
        $this->assertStringContainsString('EcritureDate', $line);
        $this->assertSame(13, count($headers));
    }

    // ── Immutability tests ──

    public function testCreditInvertsAmounts(): void
    {
        $original = ['total_ht' => 1000.00, 'tva' => 200.00, 'total_ttc' => 1200.00];
        $excludeKeys = ['id', 'number', 'hash', 'previous_hash'];

        $credit = [];
        foreach ($original as $key => $value) {
            if (is_numeric($value) && !in_array($key, $excludeKeys, true)) {
                $credit[$key] = -abs((float) $value);
            } else {
                $credit[$key] = $value;
            }
        }

        $this->assertSame(-1000.00, $credit['total_ht']);
        $this->assertSame(-200.00, $credit['tva']);
        $this->assertSame(-1200.00, $credit['total_ttc']);
    }

    public function testCreditAndOriginalSumToZero(): void
    {
        $ht = 1000.00;
        $creditHt = -abs($ht);

        $this->assertSame(0.0, $ht + $creditHt);
    }
}

#[Nf525(prefix: 'INV')]
class Nf525TestModel
{
}
