<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'active',
        'no_hp',
        'device_token',
    ];
    protected $appends = [
        'perms',
        'label',
        'value',
        'last_seen',
        'has_push_subscription',
    ];
    protected $casts = [
        'created_at' => 'datetime'
    ];
    public function getLastSeenAttribute()
    {
        $ls =  cache($this->id . '-lastseen');
        if ($ls) {
            return $ls;
        } else {
            return $this->created_at;
        }
    }
    public function getLabelAttribute()
    {
        return $this->name . ' | ' . $this->username;
    }
    public function getValueAttribute()
    {
        return $this->id;
    }
    public function getPermsAttribute()
    {
        return $this->getPermissionNames();
    }



    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'device_token',
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
        ];
    }
    /**
     * Get the pasien associated with the User
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function pasien(): HasOne
    {
        return $this->hasOne(Pasien::class, 'user_id');
    }
    public function puskesmas()
    {
        return $this->hasOne(Puskesmas::class, 'user_id');
    }

    public function getHasPushSubscriptionAttribute(): bool
    {
        return filled($this->attributes['device_token'] ?? null);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(UserPushSubscription::class);
    }

    public function relatedPasien(): ?Pasien
    {
        if ($this->can('Pasien')) {
            return $this->pasien;
        }

        if ($this->can('Pendamping')) {
            return Pasien::where('pendamping_id', $this->id)->first();
        }

        return null;
    }
}
