<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Response;
use App\Core\View;

abstract class BaseWebController
{
    public function __construct(protected readonly View $view)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], ?string $layout = 'layout'): Response
    {
        return Response::html($this->view->render($template, $data, $layout));
    }

    protected function redirect(string $to): Response
    {
        return Response::redirect($to);
    }
}
