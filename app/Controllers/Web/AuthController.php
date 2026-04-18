<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Requests\LoginRequest;
use App\Services\AuthService;
use App\Services\LoginSecurityService;
use Throwable;

final class AuthController extends BaseWebController
{
    public function __construct(
        \App\Core\View $view,
        private readonly AuthService $authService,
        private readonly LoginSecurityService $loginSecurity
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
        $identifier = strtolower(trim((string) ($input['email'] ?? '')));
        $ip = $this->clientIp();
        $userAgent = $this->userAgent();

        $errors = LoginRequest::validate($input);
        if (count($errors) > 0) {
            return $this->render('auth.login', [
                'errors' => $errors,
                'old' => $input,
                'message' => null,
            ], 'guest');
        }

        try {
            $this->loginSecurity->assertAllowed($identifier, $ip);
        } catch (Throwable $e) {
            return $this->render('auth.login', [
                'errors' => ['auth' => $e->getMessage()],
                'old' => $input,
                'message' => null,
            ], 'guest');
        }

        $user = $this->authService->attempt($identifier, (string) $input['password']);
        if ($user === null) {
            $this->loginSecurity->recordResult($identifier, $ip, $userAgent, false, null);
            return $this->render('auth.login', [
                'errors' => ['auth' => 'Invalid email or password.'],
                'old' => $input,
                'message' => null,
            ], 'guest');
        }

        $this->loginSecurity->recordResult($identifier, $ip, $userAgent, true, $user);
        $this->authService->login($user);
        return $this->redirect('/dashboard');
    }

    public function logout(Request $request): Response
    {
        $this->authService->logout();
        return $this->redirect('/login');
    }

    private function clientIp(): string
    {
        return (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function userAgent(): string
    {
        return (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown-agent');
    }
}
