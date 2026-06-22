<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;

class TemplatePaidTrafficDriveLinksSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<string> $links */
        $links = require database_path('data/template_paid_traffic_drive_links.php');

        if (count($links) !== 51) {
            throw new \RuntimeException('Expected 51 paid traffic Google Drive links, got '.count($links));
        }

        for ($i = 1; $i <= 51; $i++) {
            $url = trim($links[$i - 1]);
            if ($url === '') {
                continue;
            }

            Template::query()
                ->where('sort_order', $i)
                ->update(['paid_traffic_drive_url' => $url]);
        }
    }
}
