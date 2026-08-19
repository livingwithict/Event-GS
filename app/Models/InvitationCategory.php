<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Fixed set of three: how a guest's invitation goes out. Seeded once in the
 * create-table migration, not user-managed, so code refers to rows by key.
 */
class InvitationCategory extends Model
{
    public const PhysicalEmail = 'physical_email';

    public const EmailOnly = 'email_only';

    public const FaceVerification = 'face_verification';

    protected $fillable = ['key', 'name'];

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
