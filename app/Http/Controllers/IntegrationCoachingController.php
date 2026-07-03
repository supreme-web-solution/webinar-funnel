<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class IntegrationCoachingController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('integrations/Coaching', [
            'videoUrl' => 'https://vimeo.com/1206772990',
            'checkoutUrl' => 'https://app.paykickstart.com/checkout/plan/QDBJamy63pGbLk7XQYOjr2o4qVxd9lY0',
        ]);
    }
}
