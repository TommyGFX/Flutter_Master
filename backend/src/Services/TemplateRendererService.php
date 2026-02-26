<?php

declare(strict_types=1);

namespace App\Services;

final class TemplateRendererService
{
    public function render(string $template, array $context): string
    {
        $flatContext = $this->flattenContext($context);
        $rendered = $template;

        foreach ($flatContext as $key => $value) {
            $rendered = str_replace('{{' . $key . '}}', (string) $value, $rendered);
        }

        return $rendered;
    }

    private function flattenContext(array $context, string $prefix = ''): array
    {
        $flat = [];

        foreach ($context as $key => $value) {
            $compositeKey = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $flat += $this->flattenContext($value, $compositeKey);
                continue;
            }

            $flat[$compositeKey] = is_scalar($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR);
        }

        return $flat;
    }
}
