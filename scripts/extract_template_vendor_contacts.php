<?php

/**
 * One-off helper: fetch vendor contact blocks from Google Docs and write database/data/template_vendor_contacts.php
 *
 * Usage: php scripts/extract_template_vendor_contacts.php
 */

$docIds = [
    '11hLHISHr8n9SoKxVEREhZkMdP-ErPbTFxj_79i3BpGE',
    '1Zf2DGReDxp99-RjgAgCTUO6JQCPdLIe1WRMSZNj7gJo',
    '1VS0nVePJ-J3IT_FVt8asBR0pjJ-KinADcp8rY8NSoKc',
    '1Afg5TuNXFFKIMnFPVmGh4CVf4NvklS8iCuoVhtYUs4Y',
    '1MZoNVyUbOV6CaCzu9g90MXxMwwkrJBPjprjSYvyekog',
    '1VmPmxbT2642THbZqQgkQWs0efnyp6iVa3h_52Ql6lKc',
    '1OBVpORFy7p9CVh40n6Kus0qWd1m43y2pexTx7ydg6Ik',
    '15blZwa0wKXTrdqWnn6zxE-f7g6MyOE3qtMMPVR-Wh_k',
    '1Zct9dz10c_eEsvouaNGGDJKcIB3hxZHqG_nVAz1zoF0',
    '1fl-IulV1tk3M6R_Kr5zx4RKZRjtR_Y81QNQZh3y_sfU',
    '18oQnmZu9bD-FmiAfnTOKhI5W7h8PFohcESFl5ssFSwQ',
    '1-M0jprK1C_QLuzkVOyr9T4whn0xbGnBacVHFMlciX0o',
    '1dtbriXSlKfGl6qhU1kt9dqKpG6XKqgn6u3_5dbQ7seI',
    '1TF4TqmvE3mD5_j27t_A1bVWSKDwjLlaQJNqge0LF7VA',
    '1_EaOMeICMq_hxpmARSKfCS9_WRfry3RtHUlIfeFZyCU',
    '1Rk5vuMDU0LxLZpabdvPQyz3so2O8QCOPTIoBkdJiukY',
    '1I_a4AyWSLk2GUVtOwsIzzauZ8DLX5SDt5uLdU9CL3G8',
    '19wb3ZTFnvhPzs-goQtafNEYPjWvs9zZOqBd6DTzCo4U',
    '1o1ZLft8yuzYzRyu7QeCShSRtGbyJmrqTk4JwAbr6CDg',
    '1CKRHwn0oErwfQ2KyKOQtqSba5W073lT3Yj_gyDM7m0M',
    '1GhiDnGW_s8KX8Ko9ZbHz1LLdOm0lCPewM0QQIc8dKnM',
    '1peweEGnTsOcDXptCk4GylS_HnWGYvXUZ9RdRTaaHcdE',
    '1BAbiGxM8qXPcvA8QXpjzYxMieuDafzwcoUxO2WKlVAY',
    '1FbEmF2VESe3gUpM1nDzSBlnrzPJy99rZbS1owwyWrAU',
    '1PKzYL9HAf3NZdX1DEYfqs3xXU7mUttRNFksNsERmJQE',
    '1k1Byg33MWxsB3Uul6wcFBTwdOHu6SYLn205gEQxU8jE',
    '1HfKTZQigfInk-112O2a-HNPtYzzTBMVFoM0sNtJKp0E',
    '1vdCqZqLHwc5teNwpjCNaUdA-iFWmnpuW1mStJlo0tks',
    '1aBIl_nKa7BTMeBDHTPP-On55wQyuC-3MD5wJ_soayPY',
    '10yo-JXy0hCB_YIBKiQMsC3tNJwjL5Ctu1n3a9-zw4AM',
    '1fOdj4T8FShyXJbxd_wmYMtqBr7nRKlrufo86KUHNgmk',
    '1vJCS9JGaTi3qQR6ATIF3EVf3igs6XyZ4lkXKLMGR8gQ',
    '1JhvPK_CE8LlNzgnjdI4cFbplgpMddGEDsG-l6Awa7F8',
    '1GnKQqyBtjMFfuCIvI9P2hrU0WL59TVo8RUN4DasBjYw',
    '1sf_mavSZT61uUJw-QAcixLyOc1RmTA5okHu689Wsryg',
    '1X7kgUM91_Fuyo_b12ocGbz4fFIl7Gk5lU45LgrE7eeI',
    '1f3m-wqACg7CzyK-0lVr-udCt2waj5uroN6lOnkm-_jg',
    '1isXxGm-Jz0rPiq-dOPZEhCDOppyLQizsM8vmz38y2jM',
    '11Y7Qpo_1xgAN5O_4BR-FN6rLeUoLonPnbYHcRVUzayg',
    '1iRihJbtrDGRO8uQXvkNMjlWapjBicE2eFN-y8GulJGk',
    '1gzGVo6NdM1R4EQ8ICXZuyuNwrgtqgKf93XDajP7rtRc',
    '1rqh-jz9WNut_kXnA2cimE6NFnr1MeKr-7bE9gD7UHYc',
    '18AOVayXh69ySfpykgwYlKlAcfS5xmVZUs9fbWcUWfdY',
    '1b_m377xv6hY31QxuCVKorAKw8qyMioMwxraLvDOqG_g',
    '1xpOO3f7DzUDk5GXHJ_RyPsO0DXIzu1LsiHcB8UYXC0s',
    '1nketUTyAC6WfVVboDoULIy8NF7910R1pmFnCdedOzxE',
    '1XZU7fh9Iwwm7u98fLei1KNyXaEm4Op9lvX4HieXl7uw',
    '1rnDiSLzI9JSfoiDfUvNbnPxJhELnWWE8SNKEOaHXrWA',
    '1Xms-zu_bTUJIKobnrCJhRFaijGJ3pHef8HNuqFNWD34',
    '1NY4bpgcIhfd8epN5nBEME4w2m7vgoCKdEJtSKZ0i_x4',
    '1vpEzsPUmeVWx3E2uy7P0t_f0NgDILvw6cSdntjjlutg',
];

function extractVendorContact(string $text): ?array
{
    if (! preg_match('/CONTACT THE VENDOR FOR ANY ASSISTANCE:\s*(.*)/is', $text, $matches)) {
        return null;
    }

    $body = trim($matches[1]);
    $body = preg_replace("/\r\n|\r/", "\n", $body) ?? $body;
    $lines = array_values(array_filter(array_map('trim', explode("\n", $body)), fn (string $line): bool => $line !== ''));

    if ($body === '' && $lines === []) {
        return null;
    }

    $emails = [];
    if (preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $body, $emailMatches)) {
        $emails = array_values(array_unique($emailMatches[0]));
    }

    $urls = [];
    if (preg_match_all('#https?://[^\s<>"\'\)]+#i', $body, $urlMatches)) {
        $urls = array_values(array_unique($urlMatches[0]));
    }

    return [
        'heading' => 'Contact the vendor for any assistance',
        'body' => $body,
        'lines' => $lines,
        'emails' => $emails,
        'urls' => $urls,
    ];
}

$results = [];

foreach ($docIds as $index => $docId) {
    $url = "https://docs.google.com/document/d/{$docId}/export?format=txt";
    $context = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'DFY-Webinar-Forge-Vendor-Contact-Extractor/1.0',
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);

    if ($raw === false) {
        fwrite(STDERR, "Failed to fetch doc #".($index + 1)." ({$docId})\n");
        $results[] = null;

        continue;
    }

    // Strip UTF-8 BOM
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
    $contact = extractVendorContact($raw);
    $results[] = $contact;

    $label = $contact ? substr($contact['body'], 0, 60) : '(missing)';
    echo sprintf("#%d OK: %s\n", $index + 1, $label);
    usleep(200000);
}

$export = var_export($results, true);
$outputPath = dirname(__DIR__).'/database/data/template_vendor_contacts.php';
$contents = "<?php\n\n/**\n * Vendor contact blocks per template (indexes 0–50 = templates #1–#51).\n *\n * @return array<int, array{heading: string, body: string, lines: list<string>, emails: list<string>, urls: list<string>}|null>\n */\nreturn {$export};\n";

file_put_contents($outputPath, $contents);
echo "\nWrote {$outputPath}\n";
