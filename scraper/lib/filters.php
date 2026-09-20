<?php
declare(strict_types=1);

// Okno "świeżości" wspólne dla HN/BetaList/Product Hunt — ten sam próg 24h co
// RouteOnAttribute "nowe_i_story" w NiFi (hn_time > now - 86400).
const CANDIDATE_WINDOW_SECONDS = 86400;

// Filtr słów kluczowych wspólny dla HN/BetaList/Product Hunt (RouteOnAttribute
// "pasuje" w NiFi). "ai" łapane z granicą słowa — NiFi-owe contains('ai')
// pasowało do "brain", "explain" itd., co było historycznym bugiem.
// Pozostałe słowa świadomie jako substring (jak w NiFi): "tool" łapie też "tools".
function matches_keyword_filter(string $title): bool {
    $t = strtolower($title);
    if (preg_match('/\bai\b/i', $t)) {
        return true;
    }
    foreach (['tool', 'saas', 'launch', 'llm', 'gpt', 'model', 'open source'] as $kw) {
        if (str_contains($t, $kw)) {
            return true;
        }
    }
    return false;
}
