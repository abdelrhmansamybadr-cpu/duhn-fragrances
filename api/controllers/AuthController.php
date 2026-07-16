<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/ResponseHelper.php';
require_once __DIR__ . '/../helpers/ValidationHelper.php';
require_once __DIR__ . '/../helpers/JwtHelper.php';

/**
 * DUHN FRAGRANCES — Auth Controller
 */
class AuthController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** POST /api/auth/register */
    public function register(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = (new ValidationHelper($body))
            ->required('name',     'Full Name')
            ->required('email',    'Email')
            ->required('password', 'Password')
            ->required('phone',    'Phone')
            ->email('email')
            ->minLength('password', 8, 'Password')
            ->egyptPhone('phone');

        if (!$v->passes()) {
            ResponseHelper::error('VALIDATION_ERROR', 'Please fix the errors below.', 422, $v->errors());
        }

        // Check email uniqueness
        $check = $this->db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $check->execute([':email' => strtolower(trim($body['email']))]);
        if ($check->fetch()) {
            ResponseHelper::error('EMAIL_TAKEN', 'This email is already registered.', 409);
        }

        $hash = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        $ins = $this->db->prepare("
            INSERT INTO users (name, email, phone, password_hash, role)
            VALUES (:name, :email, :phone, :hash, 'customer')
        ");
        $ins->execute([
            ':name'  => ValidationHelper::sanitize($body['name']),
            ':email' => strtolower(trim($body['email'])),
            ':phone' => ValidationHelper::sanitize($body['phone']),
            ':hash'  => $hash,
        ]);

        $userId = (int)$this->db->lastInsertId();
        $token  = JwtHelper::generate(['user_id' => $userId, 'role' => 'customer']);

        ResponseHelper::success([
            'token' => $token,
            'user'  => ['id' => $userId, 'name' => $body['name'], 'email' => strtolower($body['email']), 'role' => 'customer'],
        ], 'Registration successful!', 201);
    }

    /** POST /api/auth/login */
    public function login(): void
    {
        $this->checkRateLimit();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = (new ValidationHelper($body))
            ->required('email',    'Email')
            ->required('password', 'Password')
            ->email('email');

        if (!$v->passes()) {
            ResponseHelper::error('VALIDATION_ERROR', 'Please fix the errors.', 422, $v->errors());
        }

        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => strtolower(trim($body['email']))]);
        $user = $stmt->fetch();

        // Account exists but password was never set (auto-created via checkout)
        if ($user && str_starts_with($user['password_hash'], '*LOCKED*')) {
            ResponseHelper::error('PASSWORD_NOT_SET',
                'Your account was created automatically. Please set your password first.',
                401, ['needs_password' => true, 'email' => $user['email']]);
        }

        if (!$user || !password_verify($body['password'], $user['password_hash'])) {
            $this->recordFailedAttempt($body['email'] ?? '');
            ResponseHelper::error('INVALID_CREDENTIALS', 'Incorrect email or password.', 401);
        }

        $this->clearFailedAttempts();

        $token = JwtHelper::generate(['user_id' => $user['id'], 'role' => $user['role']]);

        ResponseHelper::success([
            'token' => $token,
            'user'  => [
                'id'    => (int)$user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
                'phone' => $user['phone'],
            ],
        ], 'Login successful!');
    }

    /** GET /api/auth/profile */
    public function profile(): void
    {
        $payload = JwtHelper::fromRequest();
        if (!$payload) ResponseHelper::unauthorized();

        $stmt = $this->db->prepare("SELECT id, name, email, phone, role, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $payload['user_id']]);
        $user = $stmt->fetch();
        if (!$user) ResponseHelper::unauthorized();

        ResponseHelper::success($user);
    }

    /** PUT /api/auth/profile */
    public function updateProfile(): void
    {
        $payload = JwtHelper::fromRequest();
        if (!$payload) ResponseHelper::unauthorized();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $v    = (new ValidationHelper($body))->egyptPhone('phone');
        if (!$v->passes()) {
            ResponseHelper::error('VALIDATION_ERROR', 'Validation failed.', 422, $v->errors());
        }

        $upd = $this->db->prepare("UPDATE users SET name = :name, phone = :phone WHERE id = :id");
        $upd->execute([
            ':name'  => ValidationHelper::sanitize($body['name'] ?? ''),
            ':phone' => ValidationHelper::sanitize($body['phone'] ?? ''),
            ':id'    => $payload['user_id'],
        ]);
        ResponseHelper::success(null, 'Profile updated.');
    }

    /** POST /api/auth/first-login — Set password + login for auto-created accounts (no prior auth needed) */
    public function firstLogin(): void
    {
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = (new ValidationHelper($body))
            ->required('email',            'Email')
            ->required('password',         'Password')
            ->required('password_confirm', 'Confirm Password')
            ->email('email')
            ->minLength('password', 8, 'Password');

        if (!$v->passes()) {
            ResponseHelper::error('VALIDATION_ERROR', 'Please fix the errors.', 422, $v->errors());
        }

        if ($body['password'] !== $body['password_confirm']) {
            ResponseHelper::error('VALIDATION_ERROR', 'Passwords do not match.', 422,
                ['password_confirm' => ['Passwords do not match.']]);
        }

        // Find account with locked hash
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => strtolower(trim($body['email']))]);
        $user = $stmt->fetch();

        if (!$user || !str_starts_with($user['password_hash'], '*LOCKED*')) {
            ResponseHelper::error('INVALID_ACCOUNT', 'No pending account found for this email.', 400);
        }

        // Set the password
        $hash = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
                 ->execute([':h' => $hash, ':id' => $user['id']]);

        // Return JWT — user is now logged in
        $token = JwtHelper::generate(['user_id' => (int)$user['id'], 'role' => $user['role']]);

        ResponseHelper::success([
            'token' => $token,
            'user'  => [
                'id'    => (int)$user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
                'phone' => $user['phone'],
            ],
        ], 'Password set! You are now signed in.', 200);
    }

    /** POST /api/auth/set-password — Set password for auto-created account (auth required) */
    public function setPassword(): void
    {
        $payload = JwtHelper::fromRequest();
        if (!$payload) ResponseHelper::unauthorized();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        $v = (new ValidationHelper($body))
            ->required('password',         'Password')
            ->required('password_confirm', 'Confirm Password')
            ->minLength('password', 8, 'Password');

        if (!$v->passes()) {
            ResponseHelper::error('VALIDATION_ERROR', 'Please fix the errors.', 422, $v->errors());
        }

        if ($body['password'] !== $body['password_confirm']) {
            ResponseHelper::error('VALIDATION_ERROR', 'Passwords do not match.', 422,
                ['password_confirm' => ['Passwords do not match.']]);
        }

        $hash = password_hash($body['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        $this->db->prepare("UPDATE users SET password_hash = :h WHERE id = :id")
                 ->execute([':h' => $hash, ':id' => $payload['user_id']]);

        ResponseHelper::success(null, 'Password set successfully. You can now sign in anytime.');
    }

    private function checkRateLimit(): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $cutoff = date('Y-m-d H:i:s', strtotime('-' . LOGIN_LOCKOUT_MINUTES . ' minutes'));

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM login_attempts
            WHERE ip_address = :ip AND attempted_at > :cutoff
        ");
        $stmt->execute([':ip' => $ip, ':cutoff' => $cutoff]);
        $attempts = (int)$stmt->fetchColumn();

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            ResponseHelper::error(
                'TOO_MANY_ATTEMPTS',
                "Too many login attempts. Please wait " . LOGIN_LOCKOUT_MINUTES . " minutes.",
                429
            );
        }
    }

    private function recordFailedAttempt(string $email): void
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ins = $this->db->prepare("INSERT INTO login_attempts (ip_address, email) VALUES (:ip, :email)");
        $ins->execute([':ip' => $ip, ':email' => $email]);
    }

    private function clearFailedAttempts(): void
    {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $del = $this->db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
        $del->execute([':ip' => $ip]);
    }
}
