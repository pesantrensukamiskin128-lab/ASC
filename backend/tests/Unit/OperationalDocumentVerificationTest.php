<?php

namespace Tests\Unit;

use App\Support\OperationalDocumentVerification;
use Tests\TestCase;

class OperationalDocumentVerificationTest extends TestCase
{
    public function test_token_identifies_document_and_rejects_tampering(): void
    {
        config(['app.key' => 'base64:'.base64_encode(str_repeat('k', 32))]);

        $token = OperationalDocumentVerification::issue('pmb-card', 42, 'PMB-2026-00042');

        $this->assertSame(42, OperationalDocumentVerification::id($token));
        $this->assertTrue(OperationalDocumentVerification::matches($token, 'pmb-card', 42, 'PMB-2026-00042'));
        $this->assertFalse(OperationalDocumentVerification::matches($token, 'pmb-card', 42, 'PMB-2026-99999'));
        $this->assertNull(OperationalDocumentVerification::id('token-tidak-valid'));
    }
}
