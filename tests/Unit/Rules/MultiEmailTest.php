<?php

namespace Tests\Unit\Rules;

use App\Rules\MultiEmail;
use Tests\TestCase;

class MultiEmailTest extends TestCase
{
    private function fails(string $value): bool
    {
        $failed = false;
        (new MultiEmail)->validate('email', $value, function () use (&$failed) {
            $failed = true;
        });

        return $failed;
    }

    public function test_single_valid_email_passes(): void
    {
        $this->assertFalse($this->fails('a@example.com'));
    }

    public function test_multiple_comma_separated_valid_emails_pass(): void
    {
        $this->assertFalse($this->fails('a@example.com, b@example.com'));
    }

    public function test_one_invalid_part_fails(): void
    {
        $this->assertTrue($this->fails('a@example.com, not-an-email'));
    }

    public function test_empty_value_passes(): void
    {
        $this->assertFalse($this->fails(''));
    }
}
