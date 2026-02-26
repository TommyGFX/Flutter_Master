<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class CrudController
{
    public function index(Request $request, string $resource): void
    {
        $tenantId = $this->tenantId($request);
        $pdo = Database::connection();
        $stmt = $pdo->prepare(sprintf('SELECT * FROM %s WHERE tenant_id = :tenant_id ORDER BY id DESC LIMIT 100', preg_replace('/[^a-z_]/', '', $resource)));
        $stmt->execute(['tenant_id' => $tenantId]);

        Response::json(['data' => $stmt->fetchAll() ?: []]);
    }

    public function store(Request $request, string $resource): void
    {
        $tenantId = $this->tenantId($request);
        $data = $request->json();
        $name = $data['name'] ?? 'New Item';

        $pdo = Database::connection();
        $table = preg_replace('/[^a-z_]/', '', $resource);
        $stmt = $pdo->prepare("INSERT INTO {$table} (tenant_id, name) VALUES (:tenant_id, :name)");
        $stmt->execute(['tenant_id' => $tenantId, 'name' => $name]);

        Response::json(['created' => true], 201);
    }

    public function update(Request $request, string $resource, string $id): void
    {
        $tenantId = $this->tenantId($request);
        $data = $request->json();

        $pdo = Database::connection();
        $table = preg_replace('/[^a-z_]/', '', $resource);
        $stmt = $pdo->prepare("UPDATE {$table} SET name = :name WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute([
            'id' => (int) $id,
            'tenant_id' => $tenantId,
            'name' => $data['name'] ?? 'Updated',
        ]);

        Response::json(['updated' => true]);
    }

    public function destroy(Request $request, string $resource, string $id): void
    {
        $tenantId = $this->tenantId($request);
        $pdo = Database::connection();
        $table = preg_replace('/[^a-z_]/', '', $resource);
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['id' => (int) $id, 'tenant_id' => $tenantId]);

        Response::json(['deleted' => true]);
    }

    private function tenantId(Request $request): string
    {
        return $request->header('X-Tenant-Id') ?? 'default_tenant';
    }
}
