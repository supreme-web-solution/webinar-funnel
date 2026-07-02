<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUuid;
use App\Services\Ads\AdAccountResolver;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['uuid', 'name', 'username', 'email', 'password', 'zernio_profile_id', 'zernio_ad_account_id', 'platform_ad_account_ids'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicUuid, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uuid' => 'string',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'platform_ad_account_ids' => 'array',
        ];
    }

    public function funnels(): HasMany
    {
        return $this->hasMany(Funnel::class);
    }

    public function integrationAccounts(): HasMany
    {
        return $this->hasMany(IntegrationAccount::class);
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function promotionPosts(): HasMany
    {
        return $this->hasMany(FunnelPromotionPost::class);
    }

    /**
     * @param  array<string, string>  $incoming
     */
    public function savePlatformAdAccountIds(array $incoming): void
    {
        $existing = is_array($this->platform_ad_account_ids) ? $this->platform_ad_account_ids : [];
        $merged = array_merge(
            $existing,
            AdAccountResolver::normalisePlatformIds($incoming)
        );

        if ($merged === []) {
            return;
        }

        $this->forceFill(['platform_ad_account_ids' => $merged])->save();
    }

    /**
     * @return array<string, string>
     */
    public function resolvedPlatformAdAccountIds(): array
    {
        $saved = is_array($this->platform_ad_account_ids) ? $this->platform_ad_account_ids : [];

        if ($saved === [] && is_string($this->zernio_ad_account_id) && $this->zernio_ad_account_id !== '') {
            $saved['facebook'] = $this->zernio_ad_account_id;
        }

        if ($saved === []) {
            $latest = \App\Models\FunnelAdCampaign::query()
                ->where('user_id', $this->id)
                ->whereNotNull('platform_ad_account_ids')
                ->latest()
                ->value('platform_ad_account_ids');
            if (is_array($latest)) {
                $saved = AdAccountResolver::normalisePlatformIds($latest);
            }
        }

        return $saved;
    }
}
