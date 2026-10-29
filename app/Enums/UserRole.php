<?php

namespace App\Enums;

enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Cashier = 'cashier';

    public function canManageInventory(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function canApprovePin(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }
}

