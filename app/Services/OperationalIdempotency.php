<?php

namespace App\Services;

use App\Exceptions\OperationalConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class OperationalIdempotency
{
    public function replay(Request $request): ?Response
    {
        if (! $this->enabled($request)) {
            return null;
        }
        $key = $request->header('Idempotency-Key');
        if (! preg_match('/^[\x21-\x7e]{1,128}$/D', $key)) {
            throw new OperationalConflict('Idempotency-Key harus berisi 1–128 karakter ASCII tanpa spasi.');
        }
        if (! Schema::hasTable('operational_requests')) {
            abort(503, 'Migrasi idempotency organisasi belum tersedia.');
        }
        $record = DB::table('operational_requests')->where('id', $this->id($request))->first();
        if (! $record) {
            return null;
        }
        if (! hash_equals($record->authorization, $this->authorization($request))) {
            abort(403, 'Otorisasi telah berubah. Permintaan sebelumnya tidak dapat diputar ulang.');
        }
        if (! hash_equals($record->fingerprint, $this->fingerprint($request))) {
            throw new OperationalConflict('Idempotency-Key sudah digunakan untuk data yang berbeda.');
        }

        return response()->json(json_decode($record->response, true, flags: JSON_THROW_ON_ERROR), $record->status)
            ->header('Idempotency-Replayed', 'true');
    }

    public function remember(Request $request, Response $response): void
    {
        if (! $this->enabled($request) || ! $response instanceof JsonResponse || ! $response->isSuccessful()) {
            return;
        }
        DB::table('operational_requests')->insert([
            'id' => $this->id($request), 'fingerprint' => $this->fingerprint($request),
            'authorization' => $this->authorization($request), 'status' => $response->getStatusCode(),
            'response' => $response->getContent(), 'created_at' => now(),
        ]);
    }

    private function enabled(Request $request): bool
    {
        return $request->is('api/*') && $request->hasHeader('Idempotency-Key');
    }

    private function id(Request $request): string
    {
        return hash('sha256', json_encode([$request->user()->id, $request->method(), $request->path(), $request->header('Idempotency-Key')], JSON_THROW_ON_ERROR));
    }

    private function fingerprint(Request $request): string
    {
        return hash('sha256', json_encode($this->normalize($request->all()), JSON_THROW_ON_ERROR));
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return ['name' => $value->getClientOriginalName(), 'sha256' => hash_file('sha256', $value->getPathname())];
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normalize($item), $value);
        }

        return $value;
    }

    private function authorization(Request $request): string
    {
        // A changed permission/role/user snapshot fails closed before any cached body is returned.
        $actor = $request->user()->fresh(['role.permissions']);
        $permissions = $actor->role?->permissions->sortBy('id')->map(fn ($permission): array => $permission->getAttributes())->values()->all();
        $owners = [];
        $models = [
            'wasteRecord' => \App\Models\WasteRecord::class,
            'wasteHauling' => \App\Models\WasteHauling::class,
            'production' => \App\Models\FabaMovement::class, 'utilization' => \App\Models\FabaMovement::class,
            'adjustment' => \App\Models\FabaMovement::class, 'movement' => \App\Models\FabaMovement::class,
        ];
        foreach ($models as $parameter => $model) {
            $value = $request->route($parameter);
            if ($value) {
                $id = $value instanceof \Illuminate\Database\Eloquent\Model ? $value->getKey() : $value;
                $query = $model::query();
                if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($model), true)) {
                    $query->withTrashed();
                }
                $resource = $query->find($id);
                $owners[$parameter] = $resource?->only(['id', 'created_by', 'submitted_by', 'waste_record_id']);
                if ($resource instanceof \App\Models\WasteHauling) {
                    $owners['parent'] = $resource->wasteRecord?->only(['id', 'created_by']);
                }
            }
        }
        if ($request->filled('waste_record_id')) {
            $owners['body_parent'] = \App\Models\WasteRecord::query()->find($request->input('waste_record_id'))?->only(['id', 'created_by']);
        }

        return hash('sha256', json_encode([$actor->only(['id', 'organization_id', 'role_id', 'is_super_admin']), $actor->role?->getAttributes(), $permissions, $owners], JSON_THROW_ON_ERROR));
    }
}
