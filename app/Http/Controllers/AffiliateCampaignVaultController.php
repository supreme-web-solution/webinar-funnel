<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class AffiliateCampaignVaultController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('affiliate-campaign-vault/Index', [
            'signupUrl' => (string) config('addons.affiliate_campaign_vault.signup_url'),
        ]);
    }
}
