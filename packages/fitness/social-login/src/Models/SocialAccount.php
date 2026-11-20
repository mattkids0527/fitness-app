<?php

namespace Fitness\SocialLogin\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 使用者綁定的一組第三方帳號；created_at 即連結時間。
 */
class SocialAccount extends Model
{
    /*
    |--------------------------------------------------------------------------
    | GLOBAL VARIABLES
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
        'email',
        'name',
        'avatar',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    /**
     * 綁定的本站帳號。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
