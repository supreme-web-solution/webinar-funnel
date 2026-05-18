<?php

namespace App\Jobs\Concerns;

use App\Models\Keyword;
use App\Services\Mentions\KeywordMentionCapEnforcer;

trait EnforcesKeywordMentionCap
{
    protected function abortFetchIfKeywordAtMentionCap(Keyword $keyword): bool
    {
        $enforcer = app(KeywordMentionCapEnforcer::class);

        if (! $enforcer->hasReachedCap($keyword)) {
            return false;
        }

        $enforcer->enforceCap($keyword);

        return true;
    }

    protected function enforceKeywordMentionCapAfterFetch(Keyword $keyword): void
    {
        app(KeywordMentionCapEnforcer::class)->enforceCap($keyword);
    }
}
