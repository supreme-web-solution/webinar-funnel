<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = App\Models\FunnelAdCampaign::with('creatives')->latest()->first();
echo "campaign: ".json_encode($c?->only(['id','name','status','last_error','platform_ad_account_ids']))."\n";
echo "creatives: ".json_encode($c?->creatives->map(fn ($x) => $x->only(['id','status','zernio_ad_id','zernio_post_id'])))."\n";
echo "jobs_count: ".DB::table('jobs')->count()."\n";
foreach (DB::table('jobs')->select('id','queue','payload')->limit(5)->get() as $j) {
    $p = json_decode($j->payload, true);
    echo "job queue={$j->queue} name=".($p['displayName'] ?? '?')."\n";
}
echo "failed_jobs: ".DB::table('failed_jobs')->count()."\n";
