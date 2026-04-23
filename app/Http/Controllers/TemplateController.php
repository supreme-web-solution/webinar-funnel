<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Inertia\Inertia;
use Inertia\Response;

class TemplateController extends Controller
{
    public function index(): Response
    {
        $templates = Template::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->paginate(24)
            ->through(fn (Template $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'slug' => $template->slug,
                'category' => $template->category,
                'conversion_style' => $template->conversion_style,
                'thumbnail_url' => $template->thumbnail_url,
            ]);

        return Inertia::render('templates/Index', [
            'templates' => $templates,
        ]);
    }
}
