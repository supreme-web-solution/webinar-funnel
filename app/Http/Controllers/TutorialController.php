<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class TutorialController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('tutorial/Index', [
            'intro' => (string) config('tutorial.intro', ''),
            'sections' => config('tutorial.sections', []),
        ]);
    }
}
