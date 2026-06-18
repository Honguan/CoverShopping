<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogService
{
    public function writeLog(string $action, ?Model $auditable = null, array $payload = [], ?Request $request = null): void
    {
        AuditLog::create([
            'user_id' => $request && $request->user() ? $request->user()->id : null,
            'action' => $action,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable ? $auditable->getKey() : null,
            'payload' => $payload ?: null,
            'ip' => $request ? $request->ip() : null,
        ]);
    }
}
