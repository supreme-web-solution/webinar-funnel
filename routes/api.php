<?php

use App\Models\Funnel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/funnels/check-slug', function (Request $request) {
        $request->validate([
            'slug' => ['required', 'string', 'alpha_dash', 'max:120'],
        ]);

        $exists = Funnel::query()
            ->where('user_id', $request->user()->id)
            ->where('slug', $request->string('slug')->toString())
            ->exists();

        return response()->json([
            'available' => ! $exists,
        ]);
    });
});
