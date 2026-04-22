<?php

namespace App\Services\Communication;

use Illuminate\Support\Facades\Blade;
use Throwable;

class TemplateParser
{
    /**
     * Render a Blade template string with given context.
     *
     * @throws Throwable
     */
    public function render(string $template, array $context = []): string
    {
        return Blade::render($template, $context);
    }
}
