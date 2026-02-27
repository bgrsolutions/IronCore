<?php

namespace App\Support\Company;

use App\Models\User;

final class CompanyContext
{
    public const SESSION_KEY = 'ironcore.company_id';

    public static function get(): ?int
    {
        if (!app()->bound('request')) {
            return null;
        }

        $value = session(self::SESSION_KEY);

        if ($value === null && auth()->check()) {
            /** @var User $user */
            $user = auth()->user();
            $value = $user->companies()->value('companies.id');
            if ($value) {
                session([self::SESSION_KEY => (int) $value]);
            }
        }

        return $value === null ? null : (int) $value;
    }

    public static function set(?int $companyId): void
    {
        session([self::SESSION_KEY => $companyId]);
    }
}
