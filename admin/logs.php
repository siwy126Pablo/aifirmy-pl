<?php
session_start();
require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/db.php';

define('SUPABASE_URL', 'https://szassqzvivdgvpkciyif.supabase.co');
define('SUPABASE_KEY', SUPABASE_ANON_KEY);

// ---------- helpers Supabase REST ----------

function sb_get(string $path): array {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
        ],
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

function sb_count(string $table, string $filter = ''): int {
    $url = SUPABASE_URL . '/rest/v1/' . $table . '?select=id&limit=1' . ($filter ? '&' . $filter : '');
    $ch = curl_init($url);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_HEADERFUNCTION  => function ($ch, $h) use (&$headers) {
            $headers[] = $h;
            return strlen($h);
        },
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Prefer: count=exact',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
    foreach ($headers as $h) {
        if (preg_match('/^Content-Range:\s*[^\/]*\/(\d+)/i', $h, $m)) return (int) $m[1];
    }
    return 0;
}

// ---------- auth ----------

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: /admin/logs.php');
        exit;
    }
    $error = 'Nieprawidłowe hasło';
}

$logged_in = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// ---------- filtry + paginacja (tylko gdy zalogowany — unika zbędnych zapytań do Supabase) ----------

if ($logged_in) {
    // Lista source do filtra budowana z DISTINCT ai activity_log.source.
    // PostgREST nie ma natywnego DISTINCT — pobieramy posortowane wartości
    // i dedupujemy w PHP. Limit 2000 to praktyczny kompromis dla admin
    // tooling przy obecnej skali projektu, nie prawdziwy DISTINCT — jeśli
    // activity_log kiedyś urośnie znacznie ponad to, warto to przerobić na
    // widok/RPC z prawdziwym DISTINCT.
    $sourceRows = sb_get('activity_log?select=source&order=source.asc&limit=2000');
    $sourceOptions = [];
    foreach ($sourceRows as $row) {
        if (!empty($row['source']) && !in_array($row['source'], $sourceOptions, true)) {
            $sourceOptions[] = $row['source'];
        }
    }
    sort($sourceOptions);

    $allLevels = ['info', 'warning', 'error'];

    // Rozróżnienie "formularz jeszcze nie wysłany" (domyślne warning+error)
    // od "wysłany z wszystkimi odznaczonymi poziomami" (checkboxy odznaczone
    // nie trafiają do query stringu) — stąd ukryte pole filtered=1.
    $filtered = isset($_GET['filtered']);
    $selectedLevels = $filtered
        ? array_values(array_intersect($allLevels, (array) ($_GET['level'] ?? [])))
        : ['warning', 'error'];

    $selectedSource = trim((string) ($_GET['source'] ?? ''));

    $filterParts = [];
    if ($selectedSource !== '') {
        $filterParts[] = 'source=eq.' . rawurlencode($selectedSource);
    }
    if (count($selectedLevels) > 0 && count($selectedLevels) < count($allLevels)) {
        $filterParts[] = 'level=in.(' . implode(',', array_map('rawurlencode', $selectedLevels)) . ')';
    } elseif (count($selectedLevels) === 0) {
        // Brak zaznaczonych poziomów — świadomie pokaż pustą listę zamiast
        // cichego fallbacku na "wszystkie".
        $filterParts[] = 'level=eq.__none__';
    }
    $filterQs = implode('&', $filterParts);

    $logs_page_size   = 50;
    $logs_total       = sb_count('activity_log', $filterQs);
    $logs_total_pages = max(1, (int) ceil($logs_total / $logs_page_size));
    $logs_page        = max(1, min($logs_total_pages, (int) ($_GET['page'] ?? 1)));
    $logs_offset      = ($logs_page - 1) * $logs_page_size;

    $logs = sb_get(
        'activity_log' .
        '?' . ($filterQs !== '' ? $filterQs . '&' : '') .
        'order=created_at.desc,id.asc' .
        '&limit=' . $logs_page_size .
        '&offset=' . $logs_offset .
        '&select=id,created_at,source,level,message,context'
    );

    function logs_pager_url(int $page, string $source, array $levels): string {
        $qs  = ['filtered' => 1, 'page' => $page];
        if ($source !== '') $qs['source'] = $source;
        $url = '?' . http_build_query($qs);
        foreach ($levels as $lvl) {
            $url .= '&level[]=' . rawurlencode($lvl);
        }
        return $url;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logi — Panel admina — aifirmy.pl</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f5f5f5; color: #333; }
        .header { background: #4f46e5; color: white; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: white; text-decoration: none; font-size: 14px; }
        .header .links { display: flex; gap: 16px; align-items: center; }
        .container { max-width: 1200px; margin: 24px auto; padding: 0 24px; }
        .login-box { max-width: 360px; margin: 100px auto; background: white; padding: 32px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .login-box h1 { font-size: 20px; margin-bottom: 24px; }
        input[type=password] { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; margin-bottom: 12px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; }
        .btn-primary { background: #4f46e5; color: white; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .error { color: #dc2626; font-size: 14px; margin-bottom: 12px; }
        table { width: 100%; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08); border-collapse: collapse; }
        th { background: #f9fafb; padding: 12px 16px; text-align: left; font-size: 12px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .desc { max-width: 420px; white-space: normal; line-height: 1.5; word-break: break-word; }
        .badge-level { padding: 2px 10px; border-radius: 99px; font-size: 12px; font-weight: 500; text-transform: uppercase; }
        .badge-level-error { background: #fee2e2; color: #991b1b; }
        .badge-level-warning { background: #fef3c7; color: #92400e; }
        .badge-level-info { background: #f3f4f6; color: #6b7280; }
        .context-pre { background: #111827; color: #e5e7eb; padding: 12px; border-radius: 8px; font-size: 12px; overflow-x: auto; margin-top: 8px; max-width: 480px; white-space: pre-wrap; word-break: break-word; }
        .filters-box { background: white; padding: 20px 24px; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .filters-box form { display: flex; flex-wrap: wrap; gap: 24px; align-items: flex-end; }
        .filters-box label.field-label { font-size: 13px; font-weight: 500; display: block; margin-bottom: 6px; }
        .filters-box select { padding: 8px 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; }
        .level-checks { display: flex; gap: 12px; padding-top: 6px; }
        .level-checks label { font-size: 14px; display: flex; align-items: center; gap: 4px; }
    </style>
</head>
<body>

<?php if (!$logged_in): ?>
<div class="login-box">
    <h1>🔐 Panel admina</h1>
    <?php if (isset($error)): ?>
        <p class="error"><?= $error ?></p>
    <?php endif; ?>
    <form method="POST">
        <input type="password" name="password" placeholder="Hasło" autofocus>
        <button type="submit" class="btn btn-primary" style="width:100%">Zaloguj się</button>
    </form>
</div>

<?php else: ?>

<div class="header">
    <strong>aifirmy.pl — Logi</strong>
    <div class="links">
        <a href="/admin/index.php">← Panel główny</a>
        <a href="?logout=1">Wyloguj →</a>
    </div>
</div>

<div class="container">

    <div class="filters-box">
        <form method="GET">
            <input type="hidden" name="filtered" value="1">
            <div>
                <label class="field-label">Źródło</label>
                <select name="source">
                    <option value="">Wszystkie</option>
                    <?php foreach ($sourceOptions as $src): ?>
                    <option value="<?= htmlspecialchars($src) ?>" <?= $selectedSource === $src ? 'selected' : '' ?>><?= htmlspecialchars($src) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="field-label">Poziom</label>
                <div class="level-checks">
                    <?php foreach ($allLevels as $lvl): ?>
                    <label>
                        <input type="checkbox" name="level[]" value="<?= $lvl ?>" <?= in_array($lvl, $selectedLevels, true) ? 'checked' : '' ?>>
                        <?= htmlspecialchars($lvl) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Filtruj</button>
        </form>
    </div>

    <table>
        <tr>
            <th>Data</th>
            <th>Źródło</th>
            <th>Poziom</th>
            <th>Wiadomość</th>
            <th>Kontekst</th>
        </tr>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td style="white-space:nowrap"><?= $log['created_at'] ? date('d.m.Y H:i', strtotime($log['created_at'])) : '' ?></td>
            <td><?= htmlspecialchars($log['source'] ?? '') ?></td>
            <td>
                <?php $lvl = $log['level'] ?? 'info'; ?>
                <span class="badge-level badge-level-<?= htmlspecialchars($lvl) ?>"><?= htmlspecialchars($lvl) ?></span>
            </td>
            <td class="desc"><?= htmlspecialchars($log['message'] ?? '') ?></td>
            <td>
                <?php if (!empty($log['context'])): ?>
                <button class="btn btn-secondary" style="font-size:12px;padding:4px 10px" onclick="toggleContext('ctx-<?= htmlspecialchars($log['id']) ?>')">Pokaż</button>
                <pre id="ctx-<?= htmlspecialchars($log['id']) ?>" class="context-pre" style="display:none"><?= htmlspecialchars(json_encode($log['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
                <?php else: ?>
                <span style="color:#9ca3af">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
        <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:40px">Brak wpisów spełniających kryteria</td></tr>
        <?php endif; ?>
    </table>

    <?php if ($logs_total_pages > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:16px;margin-top:16px">
        <?php if ($logs_page > 1): ?>
        <a href="<?= logs_pager_url($logs_page - 1, $selectedSource, $selectedLevels) ?>" class="btn btn-secondary">← Poprzednia</a>
        <?php endif; ?>
        <span style="font-size:13px;color:#6b7280">Strona <?= $logs_page ?> z <?= $logs_total_pages ?> (<?= $logs_total ?> wpisów)</span>
        <?php if ($logs_page < $logs_total_pages): ?>
        <a href="<?= logs_pager_url($logs_page + 1, $selectedSource, $selectedLevels) ?>" class="btn btn-secondary">Następna →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
<script>
function toggleContext(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
<?php endif; ?>
</body>
</html>
