<?php

namespace Tests\Unit\Services;

use App\Services\Funnels\PageSanitizer;
use Tests\TestCase;

class PageSanitizerTest extends TestCase
{
    public function test_it_removes_script_tags_inline_handlers_and_javascript_urls(): void
    {
        $sanitizer = new PageSanitizer();

        $schema = [
            'html' => '<div onclick="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)">Click</a></div>',
            'css' => '.x{background:url("javascript:alert(4)");} .y{width:expression(alert(5));}',
            'components' => [
                ['content' => '<img src="x" onerror="alert(9)">'],
            ],
        ];

        $sanitized = $sanitizer->sanitize($schema);

        $this->assertStringNotContainsString('<script', strtolower((string) $sanitized['html']));
        $this->assertStringNotContainsString('onclick=', strtolower((string) $sanitized['html']));
        $this->assertStringNotContainsString('javascript:', strtolower((string) $sanitized['html']));
        $this->assertStringNotContainsString('expression(', strtolower((string) $sanitized['css']));
        $this->assertStringNotContainsString('onerror=', strtolower((string) $sanitized['components'][0]['content']));
    }
}
