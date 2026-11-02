<?php
// Statystyki źródeł scrapowania — tylko odczyt (GET). Dane z activity_log
// (linie "Zakończono uruchomienie" każdego przebiegu scrapera) i tools.source.
// Logika liczenia: admin/stats_lib.php. Wykresy: czysty JS + SVG, bez bibliotek
// zewnętrznych (panel trzyma klucz service_role po stronie serwera, nie ładujemy cudzych skryptów).
require_once __DIR__ . '/session_init.php'; // flagi ciasteczka sesji (HttpOnly, Secure, SameSite=Lax)
admin_session_start();
require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/db.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/stats_lib.php';

date_default_timezone_set(STATS_TZ);

// ---------- auth (jak w logs.php) ----------

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        header('Location: /admin/stats.php');
        exit;
    }
    $error = 'Nieprawidłowe hasło';
}

$logged_in = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// ---------- dane (tylko gdy zalogowany) ----------

if ($logged_in) {
    $maxWindow = max(STATS_WINDOWS);
    $window = (int) ($_GET['days'] ?? 30);
    if (!in_array($window, STATS_WINDOWS, true)) {
        $window = 30;
    }
    $now = time();

    // Zawsze pobieramy największe okno (60 dni ≈ 250 wierszy), okno wybrane w panelu tnie PHP.
    // "Ostatni przebieg" liczy się z całego pobranego zakresu, niezależnie od okna.
    $since = gmdate('Y-m-d\TH:i:s\Z', $now - ($maxWindow + 1) * 86400);
    $runRows = sb_get(
        'activity_log?select=created_at,source,message,context' .
        '&source=like.' . rawurlencode('scraper:*') .
        '&message=like.' . rawurlencode('Zako*') .
        '&created_at=gte.' . rawurlencode($since) .
        '&order=created_at.desc&limit=2000'
    );
    $startRows = sb_get(
        'activity_log?select=created_at,source,message' .
        '&source=like.' . rawurlencode('scraper:*') .
        '&message=like.' . rawurlencode('Start*') .
        '&created_at=gte.' . rawurlencode($since) .
        '&order=created_at.desc&limit=2000'
    );
    $toolRows = sb_get('tools?select=source&status=in.(approved,premium)&limit=5000');

    $agg = stats_aggregate($runRows, $startRows, $now, $window);
    $catalog = stats_catalog_counts($toolRows);
    $chart = stats_chart_payload($agg, $window);

    // Kolejność wierszy tabeli i wykresu rankingowego: wg nowych wpisów w oknie (malejąco).
    $ranked = array_values($agg['sources']);
    usort($ranked, function ($a, $b) {
        return $b['totals']['inserted'] <=> $a['totals']['inserted'];
    });
}

function stats_n(int $n): string {
    return number_format($n, 0, ',', ' ');
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statystyki źródeł — Panel admina — aifirmy.pl</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f5f5f5; color: #333; }
        .header { background: #4f46e5; color: white; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: white; text-decoration: none; font-size: 14px; }
        .header .links { display: flex; gap: 16px; align-items: center; }
        .container { max-width: 1200px; margin: 24px auto; padding: 0 24px; }
        .login-box { max-width: 360px; margin: 100px auto; background: white; padding: 32px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .login-box h1 { font-size: 20px; margin-bottom: 24px; }
        input[type=password] { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; margin-bottom: 12px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; }
        .btn-primary { background: #4f46e5; color: white; }
        .error { color: #dc2626; font-size: 14px; margin-bottom: 12px; }

        h2 { font-size: 16px; font-weight: 600; margin: 32px 0 12px; }
        .note { font-size: 12.5px; color: #6b7280; line-height: 1.5; margin-top: 8px; max-width: 900px; }

        /* filtr okna: jeden rząd nad treścią, zaznaczenie = pogrubiony ✓ */
        .range { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
        .range .lbl { font-size: 13px; font-weight: 500; margin-right: 4px; }
        .range a { padding: 7px 14px; border-radius: 8px; font-size: 14px; color: #374151; text-decoration: none; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
        .range a:hover { background: #eef0f4; }
        .range a.on { font-weight: 700; color: #111827; box-shadow: inset 0 0 0 1px #4f46e5, 0 1px 3px rgba(0,0,0,0.08); }

        table { width: 100%; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08); border-collapse: collapse; }
        th { background: #f9fafb; padding: 10px 8px; text-align: right; font-size: 11.5px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; vertical-align: bottom; }
        td { padding: 10px 8px; border-bottom: 1px solid #f3f4f6; font-size: 14px; text-align: right; font-variant-numeric: tabular-nums; }
        th:first-child, td:first-child { text-align: left; padding-left: 14px; }
        td.src { white-space: nowrap; }
        tr:last-child td { border-bottom: none; }
        .table-wrap { overflow-x: auto; border-radius: 12px; }
        .st-ok { color: #006300; font-weight: 500; }
        .st-warn { color: #b42318; font-weight: 600; }
        td.status { text-align: left; white-space: nowrap; }
        th.status { text-align: left; }

        /* wykresy */
        .viz-root { --surface: #ffffff; --ink: #0b0b0b; --ink-2: #52514e; --muted: #898781; --grid: #e1e0d9; --axis: #c3c2b7; --series: #2a78d6; --series-hover: #3987e5; }
        .grid2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        @media (max-width: 860px) { .grid2 { grid-template-columns: minmax(0, 1fr); } }
        .card { background: var(--surface); border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); padding: 16px 16px 8px; }
        .card h3 { font-size: 14px; font-weight: 600; color: var(--ink); }
        .card .sub { font-size: 12.5px; color: var(--ink-2); margin: 2px 0 6px; }
        .card svg { display: block; width: 100%; height: auto; overflow: visible; }
        .card svg text { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; }
        .card svg .hit { fill: transparent; outline: none; cursor: default; }
        .card svg .hit:focus-visible { outline: 2px solid #4f46e5; outline-offset: -2px; }
        #chart-rank { max-width: 760px; }
        details.twin { margin-top: 4px; font-size: 13px; }
        details.twin summary { cursor: pointer; color: #4f46e5; padding: 6px 0; }
        details.twin table { box-shadow: none; border: 1px solid #f3f4f6; margin: 4px 0 10px; }
        details.twin th, details.twin td { padding: 6px 10px; font-size: 13px; }
        #viz-tip { position: fixed; display: none; z-index: 20; pointer-events: none; background: #111827; color: #e5e7eb; padding: 8px 10px; border-radius: 8px; font-size: 12.5px; line-height: 1.45; max-width: 300px; box-shadow: 0 4px 14px rgba(0,0,0,0.25); }
        #viz-tip strong { display: block; color: #fff; font-size: 14px; font-weight: 600; }
        #viz-tip .tip-sub { color: #c3c2b7; }
    </style>
</head>
<body>

<?php if (!$logged_in): ?>
<div class="login-box">
    <h1>🔐 Panel admina</h1>
    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    <form method="POST">
        <input type="password" name="password" placeholder="Hasło" autofocus>
        <button type="submit" class="btn btn-primary" style="width:100%">Zaloguj się</button>
    </form>
</div>

<?php else: ?>

<div class="header">
    <strong>aifirmy.pl — Statystyki źródeł</strong>
    <div class="links">
        <a href="/admin/index.php">← Panel główny</a>
        <a href="/admin/logs.php">Logi</a>
        <a href="?logout=1">Wyloguj →</a>
    </div>
</div>

<div class="container viz-root">

    <?php if ($GLOBALS['sb_last_error'] !== null): ?>
    <div class="error" style="background:#fef2f2;border:1px solid #fca5a5;padding:12px 16px;border-radius:8px">⚠️ Odczyt z Supabase nie powiódł się (<?= htmlspecialchars($GLOBALS['sb_last_error']) ?>) — sprawdź klucz w db.php; liczby poniżej mogą być niepełne.</div>
    <?php endif; ?>

    <!-- jeden rząd filtrów nad treścią; zakres obejmuje wszystko poniżej -->
    <div class="range">
        <span class="lbl">Okno:</span>
        <?php foreach (STATS_WINDOWS as $w): ?>
        <a href="?days=<?= $w ?>" class="<?= $w === $window ? 'on' : '' ?>"><?= $w === $window ? '✓ ' : '' ?>ostatnie <?= $w ?> dni</a>
        <?php endforeach; ?>
    </div>

    <h2>Przebiegi i wyniki wg źródła</h2>
    <div class="table-wrap">
    <table>
        <tr>
            <th>Źródło</th>
            <th class="status">Ostatni przebieg</th>
            <th>Przebiegi</th>
            <th>Pobrane</th>
            <th title="Odrzucone przez filtr: okno świeżości (24 h) oraz słowa kluczowe w nazwie; dla YC-OSS: poza oknem świeżości">Odrzucone przez filtr</th>
            <th>Duplikaty</th>
            <th>Nowe w kolejce</th>
            <th>Nowe / dzień</th>
            <th>Dni z nowymi</th>
            <th>Odrzucone przez AI</th>
            <th>Błędy</th>
        </tr>
        <?php foreach ($ranked as $s): $t = $s['totals']; ?>
        <tr>
            <td class="src"><strong><?= htmlspecialchars($s['label']) ?></strong></td>
            <td class="status <?= $s['status'][0] === 'ok' ? 'st-ok' : 'st-warn' ?>"><?= htmlspecialchars($s['status'][1]) ?></td>
            <td><?= stats_n($t['runs']) ?></td>
            <td><?= stats_n($t['fetched']) ?></td>
            <td><?= stats_n($t['filtered']) ?></td>
            <td><?= stats_n($t['duplicates']) ?></td>
            <td><strong><?= stats_n($t['inserted']) ?></strong></td>
            <td><?= number_format($s['avg_new'], 1, ',', '') ?></td>
            <td><?= $s['days_with_new'] ?> / <?= $window ?></td>
            <td><?= stats_n($t['rejected']) ?></td>
            <td><?= stats_n($t['errors']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    </div>
    <p class="note">„Nowe w kolejce” to wpisy dodane do scrape_queue (po filtrze i deduplikacji, przed decyzją w panelu). „Odrzucone przez filtr” łączy filtr daty (24 h) i filtr słów kluczowych w nazwie, bo scraper zapisuje tylko jedną sumę. Dni liczone wg czasu warszawskiego; dzień bez przebiegu na wykresie to „brak przebiegu”, nie zero.</p>

    <h2>Nowe wpisy dziennie</h2>
    <div class="grid2" id="charts-daily"></div>

    <details class="twin">
        <summary>Widok tabeli: nowe wpisy dziennie</summary>
        <div class="table-wrap">
        <table>
            <tr>
                <th>Dzień</th>
                <?php foreach (array_values($agg['sources']) as $s): ?>
                <th><?= htmlspecialchars($s['label']) ?></th>
                <?php endforeach; ?>
            </tr>
            <?php foreach (array_reverse($agg['days']) as $day): ?>
            <tr>
                <td><?= htmlspecialchars(date('d.m.Y', strtotime($day . ' 12:00:00'))) ?></td>
                <?php foreach (array_values($agg['sources']) as $s): $d = $s['perDay'][$day]; ?>
                <td<?= $d['runs'] === 0 ? ' title="brak przebiegu"' : '' ?>><?= $d['runs'] === 0 ? '–' : $d['inserted'] ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </details>

    <h2>Nowe wpisy w oknie — ranking źródeł</h2>
    <div class="card" id="chart-rank"></div>
    <p class="note">Te same liczby co kolumna „Nowe w kolejce” w tabeli powyżej.</p>

    <h2>W katalogu wg źródła (zatwierdzone narzędzia)</h2>
    <div class="table-wrap">
    <table style="max-width:520px">
        <tr><th>Źródło</th><th>Narzędzia</th></tr>
        <?php foreach ($catalog as $label => $n): ?>
        <tr><td><?= htmlspecialchars($label) ?></td><td><?= stats_n($n) ?></td></tr>
        <?php endforeach; ?>
    </table>
    </div>
    <p class="note">Uwaga: historyczne wpisy z BetaList i Product Hunt sprzed migracji z NiFi (20.09) mają w bazie źródło „hacker_news” (błąd NiFi, opisany w CLAUDE.md), więc ta kolumna zawyża Hacker News i zaniża oba pozostałe. „YC-OSS” łączy etykiety yc_ai_pilot i yc_ai. Źródło „Inne” to wpisy z nietypową wartością pola source.</p>

</div>

<div id="viz-tip" role="tooltip"></div>

<script>
(function () {
    var DATA = <?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var SVGNS = 'http://www.w3.org/2000/svg';
    var C = { series: '#2a78d6', hover: '#3987e5', ink: '#0b0b0b', muted: '#898781', grid: '#e1e0d9', axis: '#c3c2b7' };
    var tip = document.getElementById('viz-tip');

    function svg(name, attrs, parent) {
        var n = document.createElementNS(SVGNS, name);
        for (var k in attrs) { if (Object.prototype.hasOwnProperty.call(attrs, k)) n.setAttribute(k, attrs[k]); }
        if (parent) parent.appendChild(n);
        return n;
    }
    function text(parent, x, y, str, attrs) {
        var t = svg('text', attrs || {}, parent);
        t.setAttribute('x', x); t.setAttribute('y', y);
        t.textContent = str;
        return t;
    }

    // ---- tooltip: treść zawsze przez textContent ----
    function setTip(lines) {
        tip.textContent = '';
        var s = document.createElement('strong');
        s.textContent = lines[0];
        tip.appendChild(s);
        for (var i = 1; i < lines.length; i++) {
            var d = document.createElement('div');
            d.className = 'tip-sub';
            d.textContent = lines[i];
            tip.appendChild(d);
        }
    }
    function placeTip(x, y) {
        tip.style.display = 'block';
        var w = tip.offsetWidth, h = tip.offsetHeight;
        var px = x + 14, py = y + 14;
        if (px + w > window.innerWidth - 8) px = x - w - 14;
        if (py + h > window.innerHeight - 8) py = y - h - 14;
        tip.style.left = Math.max(8, px) + 'px';
        tip.style.top = Math.max(8, py) + 'px';
    }
    function hideTip() { tip.style.display = 'none'; }

    // hit = przezroczysty prostokąt większy niż słupek; bar = rysowany słupek (podświetlany)
    function wireHit(hit, bar, lines) {
        function on(x, y) { if (bar) bar.setAttribute('fill', C.hover); setTip(lines); placeTip(x, y); }
        function off() { if (bar) bar.setAttribute('fill', C.series); hideTip(); }
        hit.addEventListener('pointerenter', function (e) { on(e.clientX, e.clientY); });
        hit.addEventListener('pointermove', function (e) { placeTip(e.clientX, e.clientY); });
        hit.addEventListener('pointerleave', off);
        hit.addEventListener('focus', function () {
            var r = hit.getBoundingClientRect();
            on(r.left + r.width / 2, r.top);
        });
        hit.addEventListener('blur', off);
    }

    function plNew(n) {
        if (n === 1) return '1 nowy wpis';
        var m10 = n % 10, m100 = n % 100;
        if (m10 >= 2 && m10 <= 4 && !(m100 >= 12 && m100 <= 14)) return n + ' nowe wpisy';
        return n + ' nowych wpisów';
    }
    function dm(day) { return day.slice(8, 10) + '.' + day.slice(5, 7); }
    function niceMax(v) {
        if (v <= 1) return 1;
        var p = Math.pow(10, Math.floor(Math.log(v) / Math.LN10));
        var steps = [1, 2, 5, 10];
        for (var i = 0; i < steps.length; i++) { if (steps[i] * p >= v) return steps[i] * p; }
        return 10 * p;
    }

    // ---- słupki dzienne: jeden panel na źródło, jedna seria = jeden kolor ----
    function dailyChart(host, s, days) {
        var n = days.length;
        var card = document.createElement('div');
        card.className = 'card';
        host.appendChild(card); // najpierw do DOM, żeby zmierzyć szerokość i rysować w prawdziwych pikselach
        var W = Math.max(240, Math.floor(card.clientWidth - 32));
        var H = 170, L = 30, R = 8, T = 18, B = 24;
        var pw = W - L - R, ph = H - T - B;
        var maxV = 0, maxI = -1;
        for (var i = 0; i < n; i++) { if (s.inserted[i] > maxV) { maxV = s.inserted[i]; maxI = i; } }
        var top = niceMax(maxV);
        var slot = pw / n;
        var bw = Math.max(2, Math.min(24, slot - 2));
        var yBase = T + ph;

        var h3 = document.createElement('h3'); h3.textContent = s.label; card.appendChild(h3);
        var sub = document.createElement('div'); sub.className = 'sub';
        sub.textContent = plNew(s.totals.inserted) + ' w ' + DATA.windowDays + ' dniach';
        card.appendChild(sub);

        var root = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': s.label + ': nowe wpisy dziennie, ' + DATA.windowDays + ' dni' }, card);

        // siatka (hairline, ciągła) + oś Y
        var ticks = [0];
        if (top >= 2 && top % 2 === 0) ticks.push(top / 2);
        ticks.push(top);
        ticks.forEach(function (tv) {
            var y = yBase - (tv / top) * ph;
            svg('line', { x1: L, x2: W - R, y1: y, y2: y, stroke: tv === 0 ? C.axis : C.grid, 'stroke-width': 1 }, root);
            text(root, L - 6, y + 4, String(tv), { 'text-anchor': 'end', 'font-size': 11, fill: C.muted });
        });

        // etykiety osi X: ~6 sztuk, zawsze ostatni dzień
        var step = Math.ceil(n / 6);
        for (var j = 0; j < n; j++) {
            if ((n - 1 - j) % step === 0) {
                text(root, L + slot * j + slot / 2, H - 6, dm(days[j]), { 'text-anchor': 'middle', 'font-size': 11, fill: C.muted });
            }
        }

        for (var k = 0; k < n; k++) {
            var cx = L + slot * k + slot / 2;
            var x = cx - bw / 2;
            var v = s.inserted[k];
            var bar = null;
            if (s.runs[k] === 0) {
                // brak przebiegu: cienka kreska przy osi, inna niż "0" (nic)
                svg('rect', { x: x, y: yBase - 2, width: bw, height: 2, fill: C.axis }, root);
            } else if (v > 0) {
                var h = Math.max(2, (v / top) * ph);
                var r = Math.min(4, h, bw / 2);
                var y0 = yBase - h;
                bar = svg('path', {
                    d: 'M' + x + ',' + yBase + ' L' + x + ',' + (y0 + r) + ' Q' + x + ',' + y0 + ' ' + (x + r) + ',' + y0 +
                       ' L' + (x + bw - r) + ',' + y0 + ' Q' + (x + bw) + ',' + y0 + ' ' + (x + bw) + ',' + (y0 + r) +
                       ' L' + (x + bw) + ',' + yBase + ' Z',
                    fill: C.series
                }, root);
                if (k === maxI) {
                    text(root, cx, y0 - 5, String(v), { 'text-anchor': 'middle', 'font-size': 11, 'font-weight': 600, fill: C.ink });
                }
            }
            var lines;
            if (s.runs[k] === 0) {
                lines = ['brak przebiegu', dm(days[k])];
            } else {
                lines = [
                    plNew(v),
                    dm(days[k]) + ' · przebiegi: ' + s.runs[k],
                    'pobrane ' + s.fetched[k] + ' · odrzucone przez filtr ' + s.filtered[k],
                    'duplikaty ' + s.duplicates[k] + ' · odrzucone przez AI ' + s.rejected[k] + ' · błędy ' + s.errors[k]
                ];
            }
            // 60 dni × 4 panele to za dużo przystanków dla klawiatury: wartości dziennych słupków
            // są dostępne w "Widoku tabeli" pod wykresami, tooltip działa na wskaźnik.
            var hit = svg('rect', { x: L + slot * k, y: T, width: slot, height: ph + B - 4, class: 'hit' }, root);
            wireHit(hit, bar, lines);
        }
    }

    // ---- ranking poziomy: nowe wpisy w oknie, wartość na końcu słupka ----
    function rankChart(host, series) {
        var rows = series.slice().sort(function (a, b) { return b.totals.inserted - a.totals.inserted; });
        var rowH = 36, W = Math.max(280, Math.floor(host.clientWidth - 32)), T = 4;
        var L = Math.min(120, Math.round(W * 0.32)), R = 48;
        var H = T + rows.length * rowH + 4;
        var maxV = 0;
        rows.forEach(function (r) { if (r.totals.inserted > maxV) maxV = r.totals.inserted; });
        var top = niceMax(maxV);
        var pw = W - L - R;
        var root = svg('svg', { viewBox: '0 0 ' + W + ' ' + H, role: 'img', 'aria-label': 'Nowe wpisy w kolejce wg źródła, ' + DATA.windowDays + ' dni' }, host);

        svg('line', { x1: L, x2: L, y1: T, y2: H - 4, stroke: C.axis, 'stroke-width': 1 }, root);
        rows.forEach(function (r, i) {
            var y = T + i * rowH;
            var cy = y + rowH / 2;
            var v = r.totals.inserted;
            var len = (v / top) * pw;
            var th = 14;
            text(root, L - 10, cy + 4, r.label, { 'text-anchor': 'end', 'font-size': 13, fill: '#52514e' });
            var bar = null;
            if (v > 0) {
                var rr = Math.min(4, len);
                bar = svg('path', {
                    d: 'M' + L + ',' + (cy - th / 2) + ' L' + (L + len - rr) + ',' + (cy - th / 2) +
                       ' Q' + (L + len) + ',' + (cy - th / 2) + ' ' + (L + len) + ',' + (cy - th / 2 + rr) +
                       ' L' + (L + len) + ',' + (cy + th / 2 - rr) +
                       ' Q' + (L + len) + ',' + (cy + th / 2) + ' ' + (L + len - rr) + ',' + (cy + th / 2) +
                       ' L' + L + ',' + (cy + th / 2) + ' Z',
                    fill: C.series
                }, root);
            }
            text(root, L + len + 8, cy + 4, String(v), { 'font-size': 13, 'font-weight': 600, fill: C.ink });
            var t = r.totals;
            var hit = svg('rect', { x: 0, y: y, width: W, height: rowH, class: 'hit', tabindex: 0, 'aria-label': r.label + ': ' + plNew(v) }, root);
            wireHit(hit, bar, [
                plNew(v),
                r.label + ' · ostatnie ' + DATA.windowDays + ' dni · przebiegi: ' + t.runs,
                'pobrane ' + t.fetched + ' · odrzucone przez filtr ' + t.filtered,
                'duplikaty ' + t.duplicates + ' · odrzucone przez AI ' + t.rejected + ' · błędy ' + t.errors
            ]);
        });
    }

    var daily = document.getElementById('charts-daily');
    var rank = document.getElementById('chart-rank');
    var lastWidth = 0;
    function renderAll() {
        hideTip();
        daily.textContent = '';
        rank.textContent = '';
        DATA.series.forEach(function (s) { dailyChart(daily, s, DATA.days); });
        rankChart(rank, DATA.series);
        lastWidth = daily.clientWidth;
    }
    renderAll();
    var resizeTimer = null;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (daily.clientWidth !== lastWidth) renderAll(); // przerysuj tylko gdy zmieniła się szerokość
        }, 150);
    });
})();
</script>
<?php endif; ?>
</body>
</html>
