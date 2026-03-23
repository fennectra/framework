<?php

namespace App\Controllers\Auth;

use App\Dto\Auth\AuthResponse;
use App\Dto\Auth\ForgotPasswordRequest;
use App\Dto\Auth\LoginRequest;
use App\Dto\Auth\RegisterRequest;
use App\Dto\Auth\ResetPasswordRequest;
use App\Dto\Auth\UserResponse;
use App\Models\User;
use Fennec\Attributes\ApiDescription;
use Fennec\Attributes\ApiStatus;
use Fennec\Core\Env;
use Fennec\Core\HttpException;
use Fennec\Core\JwtService;
use Fennec\Core\Validator;

class AuthController
{
    public function __construct(
        private readonly JwtService $jwtService,
    ) {
    }

    #[ApiDescription('Register a new user', 'Creates an inactive account and sends an activation email.')]
    #[ApiStatus(201, 'User registered successfully')]
    #[ApiStatus(422, 'Validation error')]
    #[ApiStatus(409, 'Email already in use')]
    public function register(RegisterRequest $request): array
    {
        // DTO validated automatically by Router injection

        $existing = User::findByEmail($request->email);
        if ($existing) {
            throw new HttpException(409, 'Email already in use.');
        }

        $activationToken = bin2hex(random_bytes(32));

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => password_hash($request->password, PASSWORD_BCRYPT),
            'is_active' => 0,
            'activation_token' => $activationToken,
            'role_id' => 3,
        ]);

        // Send activation email (if mailer is configured)
        $appUrl = Env::get('APP_URL', 'http://localhost');
        $activationUrl = "{$appUrl}/auth/activate/{$activationToken}";

        try {
            \Fennec\Core\Mail\Mailer::sendTemplate($request->email, 'account_activation', ['name' => $request->name, 'activation_url' => $activationUrl]);
        } catch (\Throwable $e) {
            // Silently fail — user is created, activation link is in DB
        }

        return [
            'status' => 'ok',
            'message' => 'Registration successful. Please check your email to activate your account.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }

    #[ApiDescription('Authenticate user', 'Verifies credentials and returns a JWT token.')]
    #[ApiStatus(200, 'Login successful')]
    #[ApiStatus(401, 'Invalid credentials')]
    #[ApiStatus(403, 'Account not activated')]
    public function login(LoginRequest $request): array
    {
        // DTO validated automatically by Router injection

        $user = User::findByEmail($request->email);

        if (!$user || !password_verify($request->password, $user->password)) {
            throw new HttpException(401, 'Invalid credentials.');
        }

        if (!$user->is_active) {
            throw new HttpException(403, 'Account not activated. Please check your email.');
        }

        $accessToken = $this->jwtService->generateAccessToken($user->email);
        $refreshToken = $this->jwtService->generateRefreshToken($user->email, $accessToken['rand']);

        $role = $user->role;

        return [
            'status' => 'ok',
            'data' => [
                'access_token' => $accessToken['token'],
                'refresh_token' => $refreshToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role ? $role->name : null,
                    'is_active' => (bool) $user->is_active,
                    'created_at' => $user->created_at,
                ],
            ],
        ];
    }

    #[ApiDescription('Activate user account', 'Activates the account using the activation token.')]
    #[ApiStatus(200, 'Account activated')]
    #[ApiStatus(404, 'Invalid activation token')]
    public function activate(string $token): array
    {
        $user = User::findByActivationToken($token);

        if (!$user) {
            throw new HttpException(404, 'Invalid or expired activation token.');
        }

        $user->update([
            'is_active' => 1,
            'activation_token' => null,
            'activated_at' => date('Y-m-d H:i:s'),
        ]);

        // Send welcome email
        try {
            $serviceName = Env::get('APP_NAME', 'Fennectra');
            \Fennec\Core\Mail\Mailer::sendTemplate($user->email, 'welcome', ['name' => $user->name, 'service' => $serviceName]);
        } catch (\Throwable $e) {
            // Silently fail
        }

        return [
            'status' => 'ok',
            'message' => 'Account activated successfully. You can now log in.',
        ];
    }

    #[ApiDescription('Request password reset', 'Sends a password reset email with a token.')]
    #[ApiStatus(200, 'Reset email sent')]
    #[ApiStatus(422, 'Validation error')]
    public function forgotPassword(ForgotPasswordRequest $request): array
    {
        // DTO validated automatically by Router injection

        $user = User::findByEmail($request->email);

        if ($user) {
            $resetToken = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $user->update([
                'reset_token' => $resetToken,
                'reset_token_expires_at' => $expiresAt,
            ]);

            $appUrl = Env::get('APP_URL', 'http://localhost');
            $resetUrl = "{$appUrl}/auth/reset-password?token={$resetToken}";

            try {
                \Fennec\Core\Mail\Mailer::sendTemplate($user->email, 'password_reset', ['name' => $user->name, 'reset_url' => $resetUrl]);
            } catch (\Throwable $e) {
                // Silently fail
            }
        }

        // Always return success to prevent email enumeration
        return [
            'status' => 'ok',
            'message' => 'If the email exists, a password reset link has been sent.',
        ];
    }

    #[ApiDescription('Reset password', 'Sets a new password using the reset token.')]
    #[ApiStatus(200, 'Password reset successful')]
    #[ApiStatus(400, 'Invalid or expired token')]
    #[ApiStatus(422, 'Validation error')]
    public function resetPassword(ResetPasswordRequest $request): array
    {
        // DTO validated automatically by Router injection

        $user = User::findByResetToken($request->token);

        if (!$user) {
            throw new HttpException(400, 'Invalid or expired reset token.');
        }

        if ($user->reset_token_expires_at && strtotime($user->reset_token_expires_at) < time()) {
            throw new HttpException(400, 'Reset token has expired.');
        }

        $user->update([
            'password' => password_hash($request->password, PASSWORD_BCRYPT),
            'reset_token' => null,
            'reset_token_expires_at' => null,
        ]);

        return [
            'status' => 'ok',
            'message' => 'Password has been reset successfully.',
        ];
    }

    #[ApiDescription('Logout', 'Invalidates the current session.')]
    #[ApiStatus(200, 'Logged out')]
    public function logout(): array
    {
        $user = $_REQUEST['__auth_user'] ?? null;

        if ($user instanceof User) {
            $user->update(['token' => null]);
        }

        return [
            'status' => 'ok',
            'message' => 'Logged out successfully.',
        ];
    }

    #[ApiDescription('Get current user', 'Returns the authenticated user profile.')]
    #[ApiStatus(200, 'User profile')]
    #[ApiStatus(401, 'Not authenticated')]
    public function me(): array
    {
        $user = $_REQUEST['__auth_user'] ?? null;

        if (!$user instanceof User) {
            throw new HttpException(401, 'Not authenticated.');
        }

        $role = $user->role;

        return [
            'status' => 'ok',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role ? $role->name : null,
                'is_active' => (bool) $user->is_active,
                'created_at' => $user->created_at,
            ],
        ];
    }
}