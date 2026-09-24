<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TutorialController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('tutorial/Index', [
            'intro' => sprintf(
                'Watch these step-by-step guides to get the most out of %s.',
                config('app.name', 'the app'),
            ),
            'sections' => config('tutorial.sections', []),
        ]);
    }
}
