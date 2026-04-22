<?php

namespace Tests\Unit;

use App\Services\Communication\EmailParserService;
use App\Services\Communication\HtmlPurifier;
use Tests\TestCase;

class EmailParserServiceTest extends TestCase
{
    protected EmailParserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailParserService(new HtmlPurifier);
    }

    public function test_extracts_subject_and_removes_prefixes()
    {
        $this->assertEquals('Hello World', $this->service->extractSubject('Re: Hello World'));
        $this->assertEquals('Hello World', $this->service->extractSubject('Fwd: Hello World'));
        $this->assertEquals('Hello World', $this->service->extractSubject('Odp: Hello World'));
        $this->assertEquals('Hello World', $this->service->extractSubject('Aw: Hello World'));
        $this->assertEquals('(Brak tematu)', $this->service->extractSubject(null));
    }

    public function test_extracts_order_number_from_subject()
    {
        $this->assertEquals('DRK-2026-12345', $this->service->extractOrderNumber('Update for DRK-2026-12345'));
        $this->assertEquals('DRK-2026-12345', $this->service->extractOrderNumber('DRK-2026-12345: New files'));
        $this->assertNull($this->service->extractOrderNumber('No order number here'));
    }

    public function test_parses_content_and_purifies_html_but_keeps_newlines()
    {
        $html = "<div>Hello World</div>\n<p>Next line</p><script>alert('xss')</script>";
        $result = $this->service->parseContent($html);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('Hello World', $result);
        $this->assertStringContainsString('Next line', $result);
    }
}
