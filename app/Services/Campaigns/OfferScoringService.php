<?php

namespace App\Services\Campaigns;

class OfferScoringService
{
    /**
     * Score, rank, and pick a weekly promote recommendation.
     *
     * @param  array<int, array<string, mixed>>  $offers
     * @return array{results: array<int, array<string, mixed>>, top_pick: array<string, mixed>|null}
     */
    public function scoreAndRank(array $offers): array
    {
        $scored = array_map(fn (array $offer): array => $this->enrichOffer($offer), $offers);

        usort($scored, fn (array $a, array $b): int => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $topPick = $scored[0] ?? null;
        if ($topPick !== null && (int) ($topPick['score'] ?? 0) < 45) {
            $topPick = null;
        }

        return [
            'results' => $scored,
            'top_pick' => $topPick,
        ];
    }

    /**
     * @param  array<string, mixed>  $offer
     * @return array<string, mixed>
     */
    protected function enrichOffer(array $offer): array
    {
        $gravity = $this->parseGravity($offer['gravity_hint'] ?? '');
        $epc = $this->parseEpc($offer['epc_hint'] ?? '');
        $refund = $this->parseRefund($offer['refund_rate'] ?? null);
        $marketplace = strtolower((string) ($offer['marketplace'] ?? ''));

        $reasons = [];
        $score = 0.0;

        if ($gravity > 0 || $epc > 0) {
            $gravityScore = min(40.0, ($gravity / 150.0) * 40.0);
            $epcScore = min(35.0, ($epc / 2.5) * 35.0);
            $refundPenalty = min(15.0, max(0.0, ($refund - 5.0) * 1.2));
            $score = $gravityScore + $epcScore + 10.0 - $refundPenalty;

            if ($gravity >= 50) {
                $reasons[] = 'Strong gravity ('.round($gravity).')';
            } elseif ($gravity >= 20) {
                $reasons[] = 'Decent gravity';
            }

            if ($epc >= 1.5) {
                $reasons[] = 'High EPC ($'.number_format($epc, 2).')';
            } elseif ($epc >= 0.75) {
                $reasons[] = 'Solid EPC';
            }

            if ($refund > 0 && $refund <= 8) {
                $reasons[] = 'Low refund rate';
            } elseif ($refund > 15) {
                $reasons[] = 'Watch refunds ('.round($refund).'%)';
            }
        } else {
            $score = 42.0;

            if ($marketplace === 'jvzoo') {
                $score += 6.0;
            } elseif ($marketplace === 'warriorplus') {
                $score += 4.0;
            } elseif ($marketplace === 'clickbank') {
                $score += 8.0;
            }

            if (($offer['trend'] ?? '') === 'rising') {
                $score += 12.0;
                $reasons[] = 'Trending in daily scan';
            }

            $reasons[] = 'Live marketplace listing';
        }

        if (($offer['search_keyword'] ?? '') !== '') {
            $score += 3.0;
        }

        $scoreInt = (int) round(max(0, min(100, $score)));

        return array_merge($offer, [
            'score' => $scoreInt,
            'score_label' => $this->scoreLabel($scoreInt),
            'promote_reason' => $reasons !== [] ? implode(' · ', $reasons) : 'Worth a test campaign',
        ]);
    }

    protected function scoreLabel(int $score): string
    {
        if ($score >= 75) {
            return 'Hot';
        }

        if ($score >= 55) {
            return 'Good';
        }

        if ($score >= 40) {
            return 'Watch';
        }

        return 'Low';
    }

    protected function parseGravity(mixed $value): float
    {
        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }

        if (! is_string($value) || trim($value) === '') {
            return 0.0;
        }

        return max(0.0, (float) preg_replace('/[^0-9.]/', '', $value));
    }

    protected function parseEpc(mixed $value): float
    {
        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }

        if (! is_string($value) || trim($value) === '') {
            return 0.0;
        }

        return max(0.0, (float) preg_replace('/[^0-9.]/', '', $value));
    }

    protected function parseRefund(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return max(0.0, (float) $value);
        }

        return max(0.0, (float) preg_replace('/[^0-9.]/', '', (string) $value));
    }
}
