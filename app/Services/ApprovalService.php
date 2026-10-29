<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\ManagerApproval;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    public function verifyPinAndCreateToken(User $requester, string $pin, string $action, string $reason, ?int $managerId = null): ManagerApproval
    {
        $query = User::query()
            ->where('is_active', true)
            ->whereIn('role', [UserRole::Manager->value, UserRole::Owner->value])
            ->whereNotNull('pin_hash');

        if ($managerId) {
            $query->where('id', $managerId);
        }

        $managers = $query->get();
        $matchedManager = null;

        foreach ($managers as $manager) {
            if (Hash::check($pin, $manager->pin_hash)) {
                $matchedManager = $manager;
                break;
            }
        }

        if (! $matchedManager) {
            throw ValidationException::withMessages([
                'pin' => ['PIN Manager/Owner tidak valid.'],
            ]);
        }

        $approval = ManagerApproval::create([
            'token' => Str::random(48),
            'manager_id' => $matchedManager->id,
            'requested_by' => $requester->id,
            'action' => $action,
            'reason' => $reason,
            'expires_at' => now()->addMinutes(10),
            'created_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $requester->id,
            'action' => 'manager_pin_approved',
            'entity' => 'manager_approval',
            'entity_id' => $approval->id,
            'after' => ['action' => $action, 'manager_id' => $matchedManager->id],
            'reason' => $reason,
            'approved_by' => $matchedManager->id,
            'created_at' => now(),
        ]);

        return $approval;
    }

    public function resolveApproverId(User $actor, string $action, ?string $approvalToken = null): int
    {
        if ($actor->isManagerOrOwner()) {
            return $actor->id;
        }

        if (! $approvalToken) {
            throw ValidationException::withMessages([
                'approval_token' => ['Tindakan ini memerlukan otorisasi PIN Manager.'],
            ]);
        }

        $approval = ManagerApproval::query()
            ->where('token', $approvalToken)
            ->where('action', $action)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->lockForUpdate()
            ->first();

        if (! $approval) {
            throw ValidationException::withMessages([
                'approval_token' => ['Token persetujuan Manager tidak valid atau sudah kedaluwarsa.'],
            ]);
        }

        $approval->update(['used_at' => now()]);

        return $approval->manager_id;
    }
}

