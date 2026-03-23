<?php

namespace Tests\Unit;

use Fennec\Attributes\Auditable;
use PHPUnit\Framework\TestCase;

class AuditTrailTest extends TestCase
{
    public function testAuditableAttributeDefaultValues(): void
    {
        $attr = new Auditable();

        $this->assertSame([], $attr->only);
        $this->assertSame([], $attr->except);
    }

    public function testAuditableAttributeWithOnly(): void
    {
        $attr = new Auditable(only: ['name', 'email']);

        $this->assertSame(['name', 'email'], $attr->only);
        $this->assertSame([], $attr->except);
    }

    public function testAuditableAttributeWithExcept(): void
    {
        $attr = new Auditable(except: ['password', 'token']);

        $this->assertSame([], $attr->only);
        $this->assertSame(['password', 'token'], $attr->except);
    }

    public function testAuditableAttributeOnClass(): void
    {
        $ref = new \ReflectionClass(AuditableTestModel::class);
        $attrs = $ref->getAttributes(Auditable::class);

        $this->assertCount(1, $attrs);

        $instance = $attrs[0]->newInstance();
        $this->assertSame(['password'], $instance->except);
    }

    public function testFilterFieldsRespectsExcept(): void
    {
        $data = ['name' => 'John', 'email' => 'j@ex.com', 'password' => 'hash'];
        $except = ['password'];

        $filtered = array_diff_key($data, array_flip($except));

        $this->assertArrayHasKey('name', $filtered);
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayNotHasKey('password', $filtered);
    }

    public function testFilterFieldsRespectsOnly(): void
    {
        $data = ['name' => 'John', 'email' => 'j@ex.com', 'password' => 'hash'];
        $only = ['name', 'email'];

        $filtered = array_intersect_key($data, array_flip($only));

        $this->assertArrayHasKey('name', $filtered);
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayNotHasKey('password', $filtered);
    }

    public function testFilterFieldsEmptyConfigReturnsAll(): void
    {
        $data = ['name' => 'John', 'email' => 'j@ex.com'];
        $only = [];
        $except = [];

        $filtered = $data;
        if (!empty($only)) {
            $filtered = array_intersect_key($filtered, array_flip($only));
        }
        if (!empty($except)) {
            $filtered = array_diff_key($filtered, array_flip($except));
        }

        $this->assertSame($data, $filtered);
    }
}

#[Auditable(except: ['password'])]
class AuditableTestModel
{
}
