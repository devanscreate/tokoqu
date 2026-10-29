<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Register;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;

class SystemController
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('manage-system');

        return response()->json([
            'settings' => Setting::getMap(),
            'registers' => Register::query()->orderBy('code')->get(),
            'users' => User::query()->orderBy('name')->get()->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'is_active' => $u->is_active,
                'has_pin' => ! empty($u->pin_hash),
            ]),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        Gate::authorize('manage-system');

        $data = $request->validate([
            'store_name' => ['required', 'string', 'max:150'],
            'store_address' => ['nullable', 'string', 'max:255'],
            'store_phone' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'string', 'max:60'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'tax_mode' => ['required', 'in:off,inclusive,exclusive'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'return_window_days' => ['required', 'integer', 'min:1', 'max:365'],
            'cashier_discount_limit_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'allow_negative_stock' => ['required', 'in:0,1'],
        ]);

        $before = Setting::getMap();

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value, 'updated_at' => now()]
            );
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'settings_updated',
            'entity' => 'settings',
            'before' => $before,
            'after' => Setting::getMap(),
            'created_at' => now(),
        ]);

        return response()->json(['settings' => Setting::getMap()]);
    }

    public function storeRegister(Request $request): JsonResponse
    {
        Gate::authorize('manage-system');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:registers,code'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $register = Register::create([
            'code' => strtoupper(trim($data['code'])),
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['register' => $register], 201);
    }

    public function updateRegister(Request $request, Register $register): JsonResponse
    {
        Gate::authorize('manage-system');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
        ]);

        $register->update($data);

        return response()->json(['register' => $register]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        Gate::authorize('manage-system');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:owner,manager,cashier'],
            'pin' => ['nullable', 'string', 'min:4', 'max:12'],
            'is_active' => ['boolean'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'pin_hash' => ! empty($data['pin']) ? Hash::make($data['pin']) : null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['user' => $user], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        Gate::authorize('manage-system');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'in:owner,manager,cashier'],
            'password' => ['nullable', 'string', 'min:6'],
            'pin' => ['nullable', 'string', 'min:4', 'max:12'],
            'is_active' => ['required', 'boolean'],
        ]);

        $updates = [
            'name' => $data['name'],
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ];

        if (! empty($data['password'])) {
            $updates['password'] = Hash::make($data['password']);
        }

        if (! empty($data['pin'])) {
            $updates['pin_hash'] = Hash::make($data['pin']);
        }

        $user->update($updates);

        return response()->json(['user' => $user]);
    }
}

