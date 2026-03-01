<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory;
    use Notifiable;
    use HasRoles;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'user_company')->withTimestamps();
    }

    public function storeLocations(): BelongsToMany
    {
        return $this->belongsToMany(StoreLocation::class, 'user_store_locations')->withTimestamps();
    }

    

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin');
    }
public function isManagerOrAdmin(): bool
    {
        return $this->hasAnyRole(['manager', 'admin']);
    }

    /** @return array<int,int> */
    public function assignedStoreLocationIds(): array
    {
        return $this->storeLocations()->pluck('store_locations.id')->map(fn ($id): int => (int) $id)->all();
    }
}
