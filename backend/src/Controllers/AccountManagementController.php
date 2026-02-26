<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use PDO;

final class AccountManagementController
{
    public function listUsers(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($tenantId, $request, 'users.read')) {
            return;
        }

        $this->listByType($tenantId, ['admin', 'user']);
    }

    public function createUser(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($tenantId, $request, 'users.manage')) {
            return;
        }

        $payload = $request->json();
        $payload['account_type'] = in_array($payload['account_type'] ?? 'user', ['admin', 'user'], true) ? $payload['account_type'] : 'user';
        $this->createAccount($tenantId, $payload);
    }

    public function updateUser(Request $request, string $idRaw): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($tenantId, $request, 'users.manage')) {
            return;
        }

        $id = (int) $idRaw;
        if ($id <= 0) {
            Response::json(['error' => 'invalid_id'], 422);
            return;
        }

        $this->updateAccount($tenantId, $id, $request->json(), ['admin', 'user']);
    }

    public function deleteUser(Request $request, string $idRaw): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($tenantId, $request, 'users.manage')) {
            return;
        }

        $this->softDelete($tenantId, (int) $idRaw, ['admin', 'user']);
    }

    public function listCustomers(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($tenantId, $request, 'customers.read')) {
            return;
        }

        $actor = $this->actor($tenantId, $request);
        if ($actor === null) {
            return;
        }

        if (($actor['account_type'] ?? '') === 'customer') {
            $this->listSelfAsCustomer($tenantId, (int) $actor['id']);
            return;
        }

        $this->listByType($tenantId, ['customer']);
    }

    public function createCustomer(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($tenantId, $request, 'customers.manage')) {
            return;
        }

        $payload = $request->json();
        $payload['account_type'] = 'customer';
        $this->createAccount($tenantId, $payload);
    }

    public function updateCustomer(Request $request, string $idRaw): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($tenantId, $request, 'customers.manage')) {
            return;
        }

        $id = (int) $idRaw;
        if ($id <= 0) {
            Response::json(['error' => 'invalid_id'], 422);
            return;
        }

        $actor = $this->actor($tenantId, $request);
        if ($actor === null) {
            return;
        }

        if (($actor['account_type'] ?? '') === 'customer' && (int) $actor['id'] !== $id) {
            Response::json(['error' => 'forbidden_self_only'], 403);
            return;
        }

        $this->updateAccount($tenantId, $id, $request->json(), ['customer']);
    }

    public function deleteCustomer(Request $request, string $idRaw): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null || !$this->authorize($tenantId, $request, 'customers.manage')) {
            return;
        }

        $this->softDelete($tenantId, (int) $idRaw, ['customer']);
    }

    public function selfProfile(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $actor = $this->actor($tenantId, $request);
        if ($actor === null) {
            return;
        }

        Response::json(['data' => $this->normalizeAccount($actor)]);
    }

    public function updateSelfProfile(Request $request): void
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === null) {
            return;
        }

        $actor = $this->actor($tenantId, $request);
        if ($actor === null) {
            return;
        }

        $payload = $request->json();
        unset($payload['tenant_id'], $payload['role_id'], $payload['account_type'], $payload['is_active'], $payload['email_confirmed']);

        $this->updateAccount($tenantId, (int) $actor['id'], $payload, [(string) ($actor['account_type'] ?? '')]);
    }

    private function listByType(string $tenantId, array $types): void
    {
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $stmt = Database::connection()->prepare(
            "SELECT * FROM tenant_accounts WHERE tenant_id = ? AND account_type IN ({$placeholders}) AND deleted_at IS NULL ORDER BY id DESC"
        );
        $stmt->execute(array_merge([$tenantId], $types));

        $rows = $stmt->fetchAll() ?: [];
        Response::json(['data' => array_map([$this, 'normalizeAccount'], $rows)]);
    }

    private function listSelfAsCustomer(string $tenantId, int $id): void
    {
        $stmt = Database::connection()->prepare(
            'SELECT * FROM tenant_accounts WHERE tenant_id = :tenant_id AND id = :id AND account_type = :account_type AND deleted_at IS NULL'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id, 'account_type' => 'customer']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        Response::json(['data' => $row === false ? [] : [$this->normalizeAccount($row)]]);
    }

    private function createAccount(string $tenantId, array $payload): void
    {
        $email = trim((string) ($payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($email === '' || $password === '') {
            Response::json(['error' => 'email_and_password_required'], 422);
            return;
        }

        $stmt = Database::connection()->prepare(
            'INSERT INTO tenant_accounts
            (tenant_id, account_type, role_id, first_name, last_name, company, street, house_number, postal_code, city, country, phone, email, password_hash, vat_number, email_confirmed, is_active)
            VALUES
            (:tenant_id, :account_type, :role_id, :first_name, :last_name, :company, :street, :house_number, :postal_code, :city, :country, :phone, :email, :password_hash, :vat_number, :email_confirmed, :is_active)'
        );

        $stmt->execute($this->accountWriteModel($tenantId, $payload, true));

        Response::json(['created' => true, 'id' => (int) Database::connection()->lastInsertId()], 201);
    }

    private function updateAccount(string $tenantId, int $id, array $payload, array $allowedTypes): void
    {
        $existing = $this->loadAccount($tenantId, $id);
        if ($existing === null || !in_array($existing['account_type'], $allowedTypes, true)) {
            Response::json(['error' => 'not_found'], 404);
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE tenant_accounts
            SET role_id = :role_id,
                first_name = :first_name,
                last_name = :last_name,
                company = :company,
                street = :street,
                house_number = :house_number,
                postal_code = :postal_code,
                city = :city,
                country = :country,
                phone = :phone,
                email = :email,
                password_hash = :password_hash,
                vat_number = :vat_number,
                email_confirmed = :email_confirmed,
                is_active = :is_active,
                updated_at = CURRENT_TIMESTAMP
            WHERE tenant_id = :tenant_id AND id = :id AND deleted_at IS NULL'
        );

        $model = $this->accountWriteModel($tenantId, array_merge($existing, $payload), false);
        $model['id'] = $id;
        $stmt->execute($model);

        Response::json(['updated' => true]);
    }

    private function softDelete(string $tenantId, int $id, array $allowedTypes): void
    {
        if ($id <= 0) {
            Response::json(['error' => 'invalid_id'], 422);
            return;
        }

        $existing = $this->loadAccount($tenantId, $id);
        if ($existing === null || !in_array($existing['account_type'], $allowedTypes, true)) {
            Response::json(['error' => 'not_found'], 404);
            return;
        }

        $stmt = Database::connection()->prepare(
            'UPDATE tenant_accounts SET deleted_at = CURRENT_TIMESTAMP, is_active = 0 WHERE tenant_id = :tenant_id AND id = :id AND deleted_at IS NULL'
        );
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);

        Response::json(['deleted' => true]);
    }

    private function accountWriteModel(string $tenantId, array $payload, bool $isCreate): array
    {
        $password = (string) ($payload['password'] ?? '');
        $passwordHash = ($password === '' && !$isCreate)
            ? (string) ($payload['password_hash'] ?? '')
            : password_hash($password, PASSWORD_DEFAULT);

        return [
            'tenant_id' => $tenantId,
            'account_type' => (string) ($payload['account_type'] ?? 'user'),
            'role_id' => isset($payload['role_id']) ? (int) $payload['role_id'] : null,
            'first_name' => trim((string) ($payload['first_name'] ?? '')),
            'last_name' => trim((string) ($payload['last_name'] ?? '')),
            'company' => $this->nullableString($payload['company'] ?? null),
            'street' => $this->nullableString($payload['street'] ?? null),
            'house_number' => $this->nullableString($payload['house_number'] ?? null),
            'postal_code' => $this->nullableString($payload['postal_code'] ?? null),
            'city' => $this->nullableString($payload['city'] ?? null),
            'country' => $this->nullableString($payload['country'] ?? null),
            'phone' => $this->nullableString($payload['phone'] ?? null),
            'email' => trim((string) ($payload['email'] ?? '')),
            'password_hash' => $passwordHash,
            'vat_number' => $this->nullableString($payload['vat_number'] ?? null),
            'email_confirmed' => (int) ((bool) ($payload['email_confirmed'] ?? false)),
            'is_active' => (int) ((bool) ($payload['is_active'] ?? true)),
        ];
    }

    private function loadAccount(string $tenantId, int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM tenant_accounts WHERE tenant_id = :tenant_id AND id = :id AND deleted_at IS NULL');
        $stmt->execute(['tenant_id' => $tenantId, 'id' => $id]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        return $account === false ? null : $account;
    }

    private function normalizeAccount(array $row): array
    {
        unset($row['password_hash']);
        return $row;
    }

    private function tenantId(Request $request): ?string
    {
        $tenantId = $request->header('X-Tenant-Id');
        if (!is_string($tenantId) || trim($tenantId) === '') {
            Response::json(['error' => 'missing_tenant_header'], 422);
            return null;
        }

        return trim($tenantId);
    }

    private function actor(string $tenantId, Request $request): ?array
    {
        $actorId = (int) ($request->header('X-User-Id') ?? 0);
        if ($actorId <= 0) {
            Response::json(['error' => 'missing_user_header', 'required_header' => 'X-User-Id'], 422);
            return null;
        }

        $actor = $this->loadAccount($tenantId, $actorId);
        if ($actor === null || !(bool) $actor['is_active']) {
            Response::json(['error' => 'actor_not_found_or_inactive'], 401);
            return null;
        }

        return $actor;
    }

    private function authorize(string $tenantId, Request $request, string $requiredPermission): bool
    {
        $actor = $this->actor($tenantId, $request);
        if ($actor === null) {
            return false;
        }

        $permissions = $this->permissionsForActor($tenantId, $actor);
        if (in_array('*', $permissions, true) || in_array($requiredPermission, $permissions, true)) {
            return true;
        }

        Response::json(['error' => 'forbidden', 'required_permission' => $requiredPermission], 403);
        return false;
    }

    private function permissionsForActor(string $tenantId, array $actor): array
    {
        $type = (string) ($actor['account_type'] ?? '');

        if ($type === 'admin') {
            return ['*'];
        }

        if ($type === 'user') {
            return ['customers.read', 'customers.manage', 'self.manage'];
        }

        if ($type === 'customer') {
            return ['customers.read', 'self.manage'];
        }

        $roleId = (int) ($actor['role_id'] ?? 0);
        if ($roleId <= 0) {
            return [];
        }

        $roleStmt = Database::connection()->prepare('SELECT role_key FROM roles WHERE tenant_id = :tenant_id AND id = :role_id');
        $roleStmt->execute(['tenant_id' => $tenantId, 'role_id' => $roleId]);
        $role = $roleStmt->fetch(PDO::FETCH_ASSOC);
        if ($role === false) {
            return [];
        }

        $permStmt = Database::connection()->prepare('SELECT permission_key FROM role_permissions WHERE tenant_id = :tenant_id AND role_key = :role_key');
        $permStmt->execute(['tenant_id' => $tenantId, 'role_key' => $role['role_key']]);

        return array_values(array_map(static fn (array $row): string => (string) $row['permission_key'], $permStmt->fetchAll() ?: []));
    }

    private function nullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);
        return $value === '' ? null : $value;
    }
}
