<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $attributes = ['active' => true, 'payroll_authorized' => false, 'must_change_password' => false, 'is_superadmin' => false];

    /** @use HasFactory<UserFactory> */
    use Audited, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'team_id', 'active', 'payroll_authorized',
        'phone', 'must_change_password', 'password_changed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
            'payroll_authorized' => 'boolean',
            'must_change_password' => 'boolean',
            'is_superadmin' => 'boolean',
            'password_changed_at' => 'datetime',
        ];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function isAdmin(): bool
    {
        return $this->inTeam('admin');
    }

    public function isSuperAdmin(): bool
    {
        return $this->active && $this->is_superadmin;
    }

    public function inTeam(string ...$slugs): bool
    {
        // The system role grants explicit workspace access independently of membership.
        if ($this->isSuperAdmin() && (in_array('admin', $slugs, true) || Team::whereIn('slug', $slugs)->exists())) {
            return true;
        }
        return $this->teams()->whereIn('slug', $slugs)->exists()
            || $this->team()->whereIn('slug', $slugs)->exists();
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function teamIds(): array
    {
        return $this->teams()->pluck('teams.id')->push($this->team_id)
            ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    public function scopeInTeams(Builder $query, string ...$slugs): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereHas('teams', fn (Builder $teams) => $teams->whereIn('slug', $slugs))
            ->orWhereHas('team', fn (Builder $teams) => $teams->whereIn('slug', $slugs)));
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim($value));
    }

    protected static function booted(): void
    {
        static::saved(function (self $user) {
            if ($user->team_id) {
                $user->teams()->syncWithoutDetaching([$user->team_id]);
            }
            $user->unsetRelation('team')->unsetRelation('teams');
        });
    }

    public function canPayroll(): bool
    {
        return $this->active && ($this->isAdmin() || ($this->inTeam('operations') && $this->payroll_authorized));
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }
}
