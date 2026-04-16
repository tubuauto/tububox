<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = [], ?string $layout = 'layout'): string
    {
        $templateFile = $this->basePath . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($templateFile)) {
            throw new \RuntimeException(sprintf('View not found: %s', $templateFile));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutFile = $this->basePath . '/' . str_replace('.', '/', $layout) . '.php';
        if (!is_file($layoutFile)) {
            throw new \RuntimeException(sprintf('Layout not found: %s', $layoutFile));
        }

        ob_start();
        require $layoutFile;
        return (string) ob_get_clean();
    }
}

