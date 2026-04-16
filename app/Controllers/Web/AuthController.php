<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Requests\LoginRequest;
use App\Services\AuthService;

final class AuthController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly AuthService $authService
    ) {
        parent::__construct($view);
    }

    public function loginForm(Request $request): Response
    {
        if ($this->authService->user() !== null) {
            return $this->redirect('/dashboard');
        }

        return $this->render('auth.login', [
            'errors' => [],
            'old' => [],
            'message' => null,
        ], 'guest');
    }

    public function login(Request $request): Response
    {
        $input = $request->body();
        $errors = LoginRequest::validate($input);
        if (count($errors) > 0) {
            return $this->render('auth.login', [
                'errors' => $errors,
                'old' => $input,
                'message' => null,
            ], 'guest');
        }

        $user = $this->authService->attempt((string) $input['email'], (string) $input['password']);
        if ($user === null) {
            return $this->render('auth.login', [
                'errors' => ['auth' => 'Invalid email or password.'],
                'old' => $input,
                'message' => null,
            ], 'guest');
        }

        $this->authService->login($user);
        return $this->redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        $this->authService->logout();
        return $this->redirect('/login');
    }
}
