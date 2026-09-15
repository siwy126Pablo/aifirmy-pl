<?php
session_start();
require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/db.php';
require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/openai.php';

define('SUPABASE_URL', 'https://szassqzvivdgvpkciyif.supabase.co');
define('SUPABASE_KEY', SUPABASE_ANON_KEY ); // ← wklej swój anon key

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

function sb_patch(string $table, string $id, array $data): void {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $table . '?id=eq.' . rawurlencode($id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_CUSTOMREQUEST   => 'PATCH',
        CURLOPT_POSTFIELDS      => json_encode($data),
        CURLOPT_HTTPHEADER      => [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function sb_post(string $table, array $data): void {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $table);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
            'Content-Type: application/json',
            'Prefer: return=minimal',
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function sb_delete(string $table, string $id): void {
    $ch = curl_init(SUPABASE_URL . '/rest/v1/' . $table . '?id=eq.' . rawurlencode($id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'DELETE',
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_KEY,
            'Authorization: Bearer ' . SUPABASE_KEY,
        ],
    ]);
    curl_exec($ch);
    curl_close($ch);
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

function favicon_from_url(string $url): ?string {
    $domain = parse_url($url, PHP_URL_HOST);
    if (!$domain) return null;
    $domain = preg_replace('/^www\./i', '', $domain);
    return 'https://www.google.com/s2/favicons?domain=' . rawurlencode($domain) . '&sz=128';
}

function slugify(string $text): string {
    $text = preg_replace('/^Show HN:\s*/i', '', $text);
    $map = [
        'ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
        'Ą'=>'a','Ć'=>'c','Ę'=>'e','Ł'=>'l','Ń'=>'n','Ó'=>'o','Ś'=>'s','Ź'=>'z','Ż'=>'z',
    ];
    $text = strtr($text, $map);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

// Pola 3-stanowe (true/false/NULL = nie zweryfikowano) — rodo_compliant,
// dpa_available, eu_data_hosting od migracji 002_tri_state_compliance_fields.
function tri_state_from_post(string $key): ?bool {
    $v = $_POST[$key] ?? 'null';
    if ($v === 'true') return true;
    if ($v === 'false') return false;
    return null;
}

function tri_state_badge(?bool $value): array {
    if ($value === true)  return ['badge-green', '✓ Tak'];
    if ($value === false) return ['badge-red', '✗ Nie'];
    return ['badge-gray', 'Nie zweryf.'];
}

// Zwarty, czysto informacyjny badge dla pól 3-stanowych w wierszu tabeli —
// edycja odbywa się wyłącznie przez #edit-modal (patchTool()); to tylko
// odczyt. id="{field}-badge-{toolId}" musi zostać zsynchronizowane z
// updateTriStateDisplay() w JS, które podmienia ten sam element po zapisie
// z modala bez przeładowania strony.
function render_tri_state_badge(string $toolId, string $field, ?bool $value): void {
    $safeId = htmlspecialchars($toolId);
    [$badgeClass, $badgeLabel] = tri_state_badge($value);
    ?>
    <span id="<?= $field ?>-badge-<?= $safeId ?>" class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
    <?php
}

// Buduje URL zakładki "Narzędzia" zachowujący search/filtr/sort — używane
// przez paginację i nagłówki sortowania, żeby żaden z tych trzech
// parametrów nigdy nie ginął przy zmianie innego (wymóg: da się wrócić
// do tego samego widoku po przeładowaniu/edycji).
function tools_tab_url(int $page, string $sort, string $dir, string $q, string $categoryId): string {
    $params = ['tab' => 'tools'];
    if ($page > 1) $params['page'] = $page;
    if ($sort !== 'created_at') $params['sort'] = $sort;
    if ($dir !== 'desc') $params['dir'] = $dir;
    if ($q !== '') $params['q'] = $q;
    if ($categoryId !== '') $params['category_id'] = $categoryId;
    return '?' . http_build_query($params);
}

// Nagłówek kolumny jako link zmieniający sortowanie — zmiana sortowania
// świadomie resetuje na stronę 1 (strona 4 w innej kolejności to inny,
// mylący zbiór wyników).
function tools_sort_header(string $label, string $field, string $currentSort, string $currentDir, string $q, string $categoryId): void {
    $isActive = $currentSort === $field;
    $nextDir  = ($isActive && $currentDir === 'asc') ? 'desc' : 'asc';
    $url      = tools_tab_url(1, $field, $nextDir, $q, $categoryId);
    $indicator = $isActive ? ($currentDir === 'asc' ? ' ▲' : ' ▼') : '';
    $style = $isActive ? 'color:#4f46e5;font-weight:700' : 'color:inherit';
    ?>
    <a href="<?= htmlspecialchars($url) ?>" style="text-decoration:none;<?= $style ?>"><?= htmlspecialchars($label . $indicator) ?></a>
    <?php
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
        header('Location: /admin/index.php');
        exit;
    }
    $error = 'Nieprawidłowe hasło';
}

$logged_in = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// ---------- akcje POST (tylko gdy zalogowany) ----------

if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = $_GET['tab'] ?? 'queue';

    if (isset($_POST['action'], $_POST['id'])) {
        $id = $_POST['id'];
        if ($_POST['action'] === 'publish') {
            sb_patch('scrape_queue', $id, ['stage' => 'published']);
        } elseif ($_POST['action'] === 'reject') {
            sb_patch('scrape_queue', $id, ['stage' => 'rejected']);
        } elseif ($_POST['action'] === 'hard_delete') {
            sb_delete('scrape_queue', $id);
        } elseif ($_POST['action'] === 'restore_to_queue') {
            sb_patch('scrape_queue', $id, ['stage' => 'ai_done']);
        }
        header('Location: /admin/index.php?tab=' . $tab);
        exit;
    }

    if (isset($_POST['add_tool'])) {
        $logoUrl = trim($_POST['logo_url'] ?? '');
        if ($logoUrl === '') {
            $logoUrl = favicon_from_url($_POST['website_url']);
        }
        // Puste pole = NULL (nie zweryfikowano/nie dotyczy), nie 0 — cena 0
        // ma inne znaczenie (np. darmowy tier freemium) niż brak danych.
        // is_numeric() zamiast samego (float) cast, żeby niepoprawny/pusty
        // string nigdy nie ciął się cicho do 0.
        $priceFromPln = trim($_POST['price_from_pln'] ?? '');
        $priceFromPln = ($priceFromPln !== '' && is_numeric($priceFromPln)) ? (float) $priceFromPln : null;
        sb_post('tools', [
            'slug'          => slugify($_POST['name']),
            'name'          => $_POST['name'],
            'tagline_pl'    => $_POST['tagline_pl'] ?: null,
            'description_pl'=> $_POST['description_pl'] ?: null,
            'website_url'   => $_POST['website_url'],
            'logo_url'      => $logoUrl,
            'category_id'   => $_POST['category_id'] ?: null,
            'pricing_model' => $_POST['pricing_model'],
            'price_from_pln'  => $priceFromPln,
            'rodo_compliant'  => tri_state_from_post('rodo_compliant'),
            'dpa_available'   => tri_state_from_post('dpa_available'),
            'eu_data_hosting' => tri_state_from_post('eu_data_hosting'),
            'ai_act_risk'   => $_POST['ai_act_risk'],
            'status'        => 'approved',
        ]);
        header('Location: /admin/index.php?tab=tools');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel admina — aifirmy.pl</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; background: #f5f5f5; color: #333; }
        .header { background: #4f46e5; color: white; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
        .header a { color: white; text-decoration: none; font-size: 14px; }
        .container { max-width: 1200px; margin: 24px auto; padding: 0 24px; }
        .login-box { max-width: 360px; margin: 100px auto; background: white; padding: 32px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .login-box h1 { font-size: 20px; margin-bottom: 24px; }
        input[type=password] { width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; margin-bottom: 12px; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; }
        .btn-primary { background: #4f46e5; color: white; width: 100%; }
        .btn-success { background: #16a34a; color: white; }
        .btn-danger { background: #dc2626; color: white; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fca5a5; font-size: 12px; padding: 4px 10px; }
        .error { color: #dc2626; font-size: 14px; margin-bottom: 12px; }
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; }
        .tab { padding: 8px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; background: white; border: 1px solid #ddd; text-decoration: none; color: #374151; }
        .tab.active { background: #4f46e5; color: white; border-color: #4f46e5; }
        table { width: 100%; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08); border-collapse: collapse; }
        th { background: #f9fafb; padding: 12px 16px; text-align: left; font-size: 12px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .badge { padding: 2px 8px; border-radius: 99px; font-size: 12px; font-weight: 500; }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-blue { background: #dbeafe; color: #2563eb; }
        .badge-gray { background: #f3f4f6; color: #6b7280; }
        .badge-red { background: #fee2e2; color: #dc2626; }
        .desc { max-width: 400px; white-space: normal; line-height: 1.5; }
        .actions { display: flex; gap: 8px; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .stat-val { font-size: 32px; font-weight: 600; color: #4f46e5; }
        .stat-lbl { font-size: 13px; color: #6b7280; margin-top: 4px; }
        .notice { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        /* Panel "Znalezione sygnały" — celowo BEZ dolnej krawędzi/zaokrąglenia
           i BEZ marginesu, żeby wizualnie "sklejał się" z wierszem narzędzia
           tuż pod nim (do którego się odnosi), zamiast wyglądać jak osobna,
           pływająca karta. Strzałka ▼ w .evidence-arrow dodatkowo wskazuje
           kierunek w dół, na wiersz docelowy. */
        .evidence-panel { background: #fffbeb; border: 1px solid #fde68a; border-bottom: none; border-radius: 8px 8px 0 0; color: #92400e; padding: 10px 16px 2px; font-size: 14px; }
        .evidence-arrow { text-align: center; color: #d97706; font-size: 13px; line-height: 1; margin-top: 2px; }
        .notice a { color: #4f46e5; font-weight: 500; text-decoration: none; margin-left: 8px; }
        .notice a:hover { text-decoration: underline; }
        .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 24px; }
        .modal-box { background: white; border-radius: 12px; padding: 24px; max-width: 640px; width: 100%; max-height: 85vh; overflow-y: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
        .verify-row { margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid #f3f4f6; }
        .verify-row:last-child { border-bottom: none; }
        .verify-check { display: flex; align-items: center; gap: 6px; font-weight: 500; font-size: 14px; margin-bottom: 8px; }
        .verify-warn { color: #dc2626; font-weight: 400; font-size: 12px; }
        .verify-compare { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .verify-old, .verify-new { padding: 8px 10px; border-radius: 6px; font-size: 13px; line-height: 1.4; word-break: break-word; }
        .verify-old { background: #f3f4f6; color: #6b7280; }
        .verify-new { background: #dcfce7; color: #166534; }
        .verify-hint-note { margin-top: 6px; font-size: 12px; color: #6b7280; font-style: italic; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 20px; }
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
        <button type="submit" class="btn btn-primary">Zaloguj się</button>
    </form>
</div>

<?php else: ?>

<div class="header">
    <strong>aifirmy.pl — Panel admina</strong>
    <div style="display:flex;gap:16px;align-items:center">
        <a href="/admin/affiliate.php">Linki afiliacyjne</a>
        <a href="/admin/logs.php">Logi</a>
        <a href="?logout=1">Wyloguj →</a>
    </div>
</div>

<div class="container">
<?php
$tab = $_GET['tab'] ?? 'queue';

$czeka      = sb_count('scrape_queue', 'stage=eq.ai_done');
$approved   = sb_count('tools', 'status=eq.approved');
$opublikowane = sb_count('scrape_queue', 'stage=eq.published');
$odrzucone_ai  = sb_count('scrape_queue', 'stage=eq.ai_rejected');
?>

    <div class="stats">
        <div class="stat">
            <div class="stat-val"><?= $czeka ?></div>
            <div class="stat-lbl">Czeka na moderację</div>
        </div>
        <div class="stat">
            <div class="stat-val"><?= $approved ?></div>
            <div class="stat-lbl">Zatwierdzone narzędzia</div>
        </div>
        <div class="stat">
            <div class="stat-val"><?= $opublikowane ?></div>
            <div class="stat-lbl">Opublikowane łącznie</div>
        </div>
    </div>

    <div class="tabs">
        <a href="?tab=queue" class="tab <?= $tab === 'queue' ? 'active' : '' ?>">Kolejka (<?= $czeka ?>)</a>
        <a href="?tab=ai_rejected" class="tab <?= $tab === 'ai_rejected' ? 'active' : '' ?>">Odrzucone przez AI (<?= $odrzucone_ai ?>)</a>
        <a href="?tab=tools" class="tab <?= $tab === 'tools' ? 'active' : '' ?>">Narzędzia</a>
        <a href="?tab=add"   class="tab <?= $tab === 'add'   ? 'active' : '' ?>">+ Dodaj wpis</a>
    </div>

    <?php if ($tab === 'queue'): ?>
    <?php
    $items = sb_get(
        'scrape_queue' .
        '?stage=eq.ai_done' .
        '&order=scraped_at.desc' .
        '&limit=50' .
        '&select=id,raw_name,ai_description,ai_category,source_url,scraped_at'
    );
    ?>
    <?php if ($odrzucone_ai > 0): ?>
    <div class="notice">
        ⚠️ <strong><?= $odrzucone_ai ?></strong> odrzuconych przez AI oczekuje na przegląd.
        <a href="?tab=ai_rejected">Sprawdź →</a>
    </div>
    <?php endif; ?>
    <table>
        <tr>
            <th>Nazwa</th>
            <th>Opis AI</th>
            <th>Kategoria</th>
            <th>Źródło</th>
            <th>Data</th>
            <th>Akcja</th>
        </tr>
        <?php foreach ($items as $item): ?>
        <tr>
            <td><strong><?= htmlspecialchars($item['raw_name'] ?? '') ?></strong></td>
            <td class="desc"><?= htmlspecialchars($item['ai_description'] ?? '') ?></td>
            <td><span class="badge badge-blue"><?= htmlspecialchars($item['ai_category'] ?? '') ?></span></td>
            <td><a href="<?= htmlspecialchars($item['source_url'] ?? '') ?>" target="_blank" style="color:#4f46e5">Link →</a></td>
            <td><?= $item['scraped_at'] ? date('d.m H:i', strtotime($item['scraped_at'])) : '' ?></td>
            <td>
                <div class="actions">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                        <input type="hidden" name="action" value="publish">
                        <button class="btn btn-success">✓</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                        <input type="hidden" name="action" value="reject">
                        <button class="btn btn-danger">✗</button>
                    </form>
                    <button class="btn btn-delete" onclick="softDelete('<?= htmlspecialchars($item['id']) ?>', 'scrape_queue', 'stage', this.closest('tr'))">Usuń</button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
        <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:40px">Brak wpisów do moderacji 🎉</td></tr>
        <?php endif; ?>
    </table>

    <?php elseif ($tab === 'ai_rejected'): ?>
    <?php
    $rejected_items = sb_get(
        'scrape_queue' .
        '?stage=eq.ai_rejected' .
        '&order=scraped_at.desc' .
        '&limit=50' .
        '&select=id,raw_name,ai_description,source_url,scraped_at'
    );
    ?>
    <table>
        <tr>
            <th>Nazwa (raw)</th>
            <th>Opis AI</th>
            <th>Źródło</th>
            <th>Data</th>
            <th>Akcja</th>
        </tr>
        <?php foreach ($rejected_items as $item): ?>
        <tr>
            <td><strong><?= htmlspecialchars($item['raw_name'] ?? '') ?></strong></td>
            <td class="desc"><?= htmlspecialchars($item['ai_description'] ?? '') ?></td>
            <td><a href="<?= htmlspecialchars($item['source_url'] ?? '') ?>" target="_blank" style="color:#4f46e5">Link →</a></td>
            <td><?= $item['scraped_at'] ? date('d.m H:i', strtotime($item['scraped_at'])) : '' ?></td>
            <td>
                <div class="actions">
                    <form method="POST" onsubmit="return confirm('Usunąć trwale? Tej operacji nie można cofnąć.');">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                        <input type="hidden" name="action" value="hard_delete">
                        <button class="btn btn-danger">Usuń trwale</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                        <input type="hidden" name="action" value="restore_to_queue">
                        <button class="btn btn-secondary">To jednak produkt — przenieś do kolejki</button>
                    </form>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rejected_items)): ?>
        <tr><td colspan="5" style="text-align:center;color:#9ca3af;padding:40px">Brak odrzuconych wpisów 🎉</td></tr>
        <?php endif; ?>
    </table>

    <?php elseif ($tab === 'tools'): ?>
    <?php
    // Search/filtr/sort — wszystkie trzy jako query params, żeby dało się
    // wrócić do tego samego widoku po przeładowaniu/edycji (np. inline-edit
    // w wierszu przeładowuje stronę przez zwykły link, nie przez fetch).
    $tools_q           = trim((string) ($_GET['q'] ?? ''));
    $tools_category_id = trim((string) ($_GET['category_id'] ?? ''));

    // Biała lista sortowalnych kolumn — nazwa parametru URL => realna
    // kolumna/wyrażenie order= w zapytaniu do PostgREST. categories(name_pl)
    // to sortowanie po zagnieżdżonym zasobie — sprawdzone jako działające
    // na żywo w Supabase REST przed wdrożeniem, nie zgadywane.
    $tools_sort_columns = [
        'name'       => 'name',
        'category'   => 'categories(name_pl)',
        'status'     => 'status',
        'created_at' => 'created_at',
    ];
    $tools_sort = $_GET['sort'] ?? 'created_at';
    if (!isset($tools_sort_columns[$tools_sort])) $tools_sort = 'created_at';
    $tools_dir  = ($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
    $tools_order = $tools_sort_columns[$tools_sort] . '.' . $tools_dir . ',id.asc';

    $tools_filters = ['status=eq.approved'];
    if ($tools_q !== '') {
        $tools_filters[] = 'name=ilike.*' . rawurlencode($tools_q) . '*';
    }
    if ($tools_category_id !== '') {
        $tools_filters[] = 'category_id=eq.' . rawurlencode($tools_category_id);
    }
    $tools_filter_qs = implode('&', $tools_filters);

    // Licznik i paginacja MUSZĄ liczyć się na przefiltrowanym zbiorze, nie
    // na globalnym $approved (ten zostaje niezmieniony — karta statystyk
    // u góry ma pokazywać prawdziwy total, niezależny od aktywnego filtra).
    $tools_page_size    = 100;
    $tools_filtered_total = sb_count('tools', $tools_filter_qs);
    $tools_total_pages  = max(1, (int) ceil($tools_filtered_total / $tools_page_size));
    $tools_page         = max(1, min($tools_total_pages, (int) ($_GET['page'] ?? 1)));
    $tools_offset       = ($tools_page - 1) * $tools_page_size;

    $tools = sb_get(
        'tools' .
        '?' . $tools_filter_qs .
        '&order=' . $tools_order .
        '&limit=' . $tools_page_size .
        '&offset=' . $tools_offset .
        '&select=id,slug,name,website_url,logo_url,category_id,pricing_model,rodo_compliant,dpa_available,eu_data_hosting,ai_act_risk,status,ai_verified_at,categories(name_pl)'
    );
    $tools_categories = sb_get('categories?order=sort_order&select=id,name_pl');
    ?>
    <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:16px;background:white;padding:16px;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,0.08)">
        <input type="hidden" name="tab" value="tools">
        <?php if ($tools_sort !== 'created_at'): ?>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($tools_sort) ?>">
        <?php endif; ?>
        <?php if ($tools_dir !== 'desc'): ?>
        <input type="hidden" name="dir" value="<?= htmlspecialchars($tools_dir) ?>">
        <?php endif; ?>
        <div>
            <label style="font-size:12px;font-weight:500;display:block;margin-bottom:4px">Szukaj po nazwie</label>
            <input type="search" name="q" value="<?= htmlspecialchars($tools_q) ?>" placeholder="np. Sona8" style="padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:14px;width:220px">
        </div>
        <div>
            <label style="font-size:12px;font-weight:500;display:block;margin-bottom:4px">Kategoria</label>
            <select name="category_id" style="padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                <option value="">Wszystkie</option>
                <?php foreach ($tools_categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['id']) ?>" <?= $tools_category_id === $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name_pl']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Filtruj</button>
        <?php if ($tools_q !== '' || $tools_category_id !== ''): ?>
        <a href="<?= htmlspecialchars(tools_tab_url(1, $tools_sort, $tools_dir, '', '')) ?>" class="btn btn-secondary">Wyczyść</a>
        <?php endif; ?>
    </form>
    <table>
        <tr>
            <th><?php tools_sort_header('Nazwa', 'name', $tools_sort, $tools_dir, $tools_q, $tools_category_id); ?></th>
            <th><?php tools_sort_header('Kategoria', 'category', $tools_sort, $tools_dir, $tools_q, $tools_category_id); ?></th>
            <th>Cennik</th>
            <th>RODO</th>
            <th>DPA</th>
            <th>Hosting UE</th>
            <th>AI Act</th>
            <th><?php tools_sort_header('Status', 'status', $tools_sort, $tools_dir, $tools_q, $tools_category_id); ?></th>
            <th>Akcja</th>
        </tr>
        <?php foreach ($tools as $tool): ?>
        <tr id="evidence-row-<?= htmlspecialchars($tool['id']) ?>" style="display:none">
            <td colspan="9" class="evidence-panel">
                <div id="evidence-content-<?= htmlspecialchars($tool['id']) ?>"></div>
                <div class="evidence-arrow" aria-hidden="true">▼</div>
            </td>
        </tr>
        <tr>
            <td>
                <div style="display:flex;align-items:center;gap:8px">
                    <?php if ($tool['logo_url']): ?>
                    <img src="<?= htmlspecialchars($tool['logo_url']) ?>" alt="" style="width:20px;height:20px;border-radius:4px;object-fit:contain;border:1px solid #eee">
                    <?php endif; ?>
                    <div>
                        <strong><?= htmlspecialchars($tool['name']) ?></strong><br><small style="color:#9ca3af"><?= htmlspecialchars($tool['slug']) ?></small>
                    </div>
                </div>
            </td>
            <td id="category-cell-<?= htmlspecialchars($tool['id']) ?>"><?= htmlspecialchars($tool['categories']['name_pl'] ?? '') ?></td>
            <td><span class="badge badge-gray"><?= htmlspecialchars($tool['pricing_model'] ?? '') ?></span></td>
            <td><?php render_tri_state_badge($tool['id'], 'rodo', $tool['rodo_compliant']); ?></td>
            <td><?php render_tri_state_badge($tool['id'], 'dpa', $tool['dpa_available']); ?></td>
            <td><?php render_tri_state_badge($tool['id'], 'eu', $tool['eu_data_hosting']); ?></td>
            <td><?= htmlspecialchars($tool['ai_act_risk'] ?? '') ?></td>
            <td><span class="badge <?= $tool['status'] === 'approved' ? 'badge-green' : 'badge-gray' ?>"><?= htmlspecialchars($tool['status']) ?></span></td>
            <td>
                <div class="actions">
                    <div>
                        <button class="btn btn-secondary" style="font-size:12px;padding:6px 10px" onclick="verifyTool('<?= htmlspecialchars($tool['id']) ?>', this)">🔍 Zweryfikuj przez AI</button>
                        <div id="verified-<?= htmlspecialchars($tool['id']) ?>" style="font-size:11px;color:#9ca3af;margin-top:4px">
                            <?= $tool['ai_verified_at'] ? 'Sprawdzono: ' . date('d.m.Y', strtotime($tool['ai_verified_at'])) : '' ?>
                        </div>
                    </div>
                    <button
                        class="btn btn-secondary"
                        style="font-size:12px;padding:6px 10px"
                        data-tool-id="<?= htmlspecialchars($tool['id']) ?>"
                        data-tool='<?= htmlspecialchars(json_encode([
                            'website_url'     => $tool['website_url'],
                            'category_id'     => $tool['category_id'],
                            'logo_url'        => $tool['logo_url'],
                            'rodo_compliant'  => $tool['rodo_compliant'],
                            'dpa_available'   => $tool['dpa_available'],
                            'eu_data_hosting' => $tool['eu_data_hosting'],
                        ]), ENT_QUOTES) ?>'
                        onclick="openEditModalFromButton(this)"
                    >✏️ Edytuj</button>
                    <button class="btn btn-delete" onclick="softDelete('<?= htmlspecialchars($tool['id']) ?>', 'tools', 'status', this.closest('tr'))">Usuń</button>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php if ($tools_total_pages > 1): ?>
    <div style="display:flex;justify-content:center;align-items:center;gap:16px;margin-top:16px">
        <?php if ($tools_page > 1): ?>
        <a href="<?= htmlspecialchars(tools_tab_url($tools_page - 1, $tools_sort, $tools_dir, $tools_q, $tools_category_id)) ?>" class="btn btn-secondary">← Poprzednia</a>
        <?php endif; ?>
        <span style="font-size:13px;color:#6b7280">Strona <?= $tools_page ?> z <?= $tools_total_pages ?> (<?= $tools_filtered_total ?> narzędzi)</span>
        <?php if ($tools_page < $tools_total_pages): ?>
        <a href="<?= htmlspecialchars(tools_tab_url($tools_page + 1, $tools_sort, $tools_dir, $tools_q, $tools_category_id)) ?>" class="btn btn-secondary">Następna →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Modal edycji narzędzia — wzorowany na #verify-modal (ten sam
         mechanizm otwierania/zamykania, styl .modal-overlay/.modal-box).
         Jedyna ścieżka edycji URL/kategorii/logo/RODO/DPA/Hosting UE —
         dawne mini-formularze inline w wierszu usunięte po zweryfikowaniu
         tego modala na żywo jako pełnoprawnej zamiany. -->
    <div id="edit-modal" class="modal-overlay" style="display:none">
        <div class="modal-box">
            <h2 style="margin-bottom:16px;font-size:18px">Edytuj narzędzie</h2>
            <div style="display:flex;flex-direction:column;gap:14px">
                <div>
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">URL strony</label>
                    <input type="url" id="edit-website_url" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Kategoria</label>
                    <select id="edit-category_id" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                        <?php foreach ($tools_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name_pl']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Logo URL</label>
                    <input type="url" id="edit-logo_url" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                    <div>
                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">RODO zgodny</label>
                        <select id="edit-rodo_compliant" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                            <option value="null">Nie zweryfikowano</option>
                            <option value="true">Tak</option>
                            <option value="false">Nie</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Umowa DPA</label>
                        <select id="edit-dpa_available" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                            <option value="null">Nie zweryfikowano</option>
                            <option value="true">Tak</option>
                            <option value="false">Nie</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Hosting UE</label>
                        <select id="edit-eu_data_hosting" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                            <option value="null">Nie zweryfikowano</option>
                            <option value="true">Tak</option>
                            <option value="false">Nie</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeEditModal()">Anuluj</button>
                <button class="btn btn-success" onclick="saveEditModal()">Zapisz zmiany</button>
            </div>
        </div>
    </div>

    <?php elseif ($tab === 'add'): ?>
    <?php
    $categories = sb_get('categories?order=sort_order&select=id,name_pl');
    ?>
    <div style="background:white;padding:32px;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,0.08);max-width:700px">
        <h2 style="margin-bottom:24px;font-size:18px">Dodaj narzędzie ręcznie</h2>
        <form method="POST" style="display:flex;flex-direction:column;gap:16px">
            <div>
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Nazwa *</label>
                <input type="text" name="name" required style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
            </div>
            <div>
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Tagline (1 zdanie)</label>
                <input type="text" name="tagline_pl" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
            </div>
            <div>
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Opis PL</label>
                <textarea name="description_pl" rows="4" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px;resize:vertical"></textarea>
            </div>
            <div>
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">URL strony *</label>
                <input type="url" name="website_url" required style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
            </div>
            <div>
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Logo URL (opcjonalnie — jeśli puste, użyjemy favicon strony)</label>
                <input type="url" name="logo_url" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
            </div>
            <div>
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Kategoria</label>
                <select name="category_id" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                    <option value="">— wybierz —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name_pl']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Model cenowy</label>
                    <select name="pricing_model" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                        <option value="free">Free</option>
                        <option value="freemium" selected>Freemium</option>
                        <option value="paid">Paid</option>
                        <option value="open_source">Open Source</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Cena od (PLN, opcjonalnie)</label>
                    <input type="number" step="0.01" name="price_from_pln" placeholder="np. 99" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">AI Act ryzyko</label>
                    <select name="ai_act_risk" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                        <option value="minimal" selected>Minimalne</option>
                        <option value="limited">Ograniczone</option>
                        <option value="high">Wysokie</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">RODO zgodny</label>
                    <select name="rodo_compliant" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                        <option value="null" selected>Nie zweryfikowano</option>
                        <option value="true">Tak</option>
                        <option value="false">Nie</option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Umowa DPA dostępna</label>
                    <select name="dpa_available" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                        <option value="null" selected>Nie zweryfikowano</option>
                        <option value="true">Tak</option>
                        <option value="false">Nie</option>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Hosting danych w UE</label>
                    <select name="eu_data_hosting" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                        <option value="null" selected>Nie zweryfikowano</option>
                        <option value="true">Tak</option>
                        <option value="false">Nie</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="add_tool" class="btn btn-primary" style="align-self:flex-start;padding:12px 32px">Dodaj narzędzie</button>
        </form>
    </div>
    <?php endif; ?>

</div>

<div id="verify-modal" class="modal-overlay" style="display:none">
    <div class="modal-box">
        <h2 style="margin-bottom:16px;font-size:18px">Weryfikacja AI — porównanie</h2>
        <div id="verify-modal-body"></div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="closeVerifyModal()">Anuluj</button>
            <button class="btn btn-success" onclick="applyVerifiedChanges()">Zatwierdź zaznaczone zmiany</button>
        </div>
    </div>
</div>

<?php endif; ?>
<script>
function softDelete(id, table, field, row) {
    if (!window.confirm('Usunąć ten wpis?')) return;
    fetch('<?= SUPABASE_URL ?>/rest/v1/' + table + '?id=eq.' + encodeURIComponent(id), {
        method: 'PATCH',
        headers: {
            'apikey': '<?= SUPABASE_KEY ?>',
            'Authorization': 'Bearer <?= SUPABASE_KEY ?>',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({[field]: 'rejected'})
    }).then(function(r) {
        if (r.ok) row.remove();
    });
}

// Wspólny, niskopoziomowy helper PATCH na tools — jedno miejsce zamiast
// powtarzania fetch/headers w każdej funkcji save* (dawniej saveUrl/
// saveCategory/saveLogo powtarzały ten sam blok trzykrotnie). Używany
// zarówno przez istniejące pojedyncze inline-save, jak i nowy modal
// edycji (jeden PATCH na wszystkie 6 pól naraz zamiast sześciu osobnych).
function patchTool(id, fields, onSuccess) {
    fetch('<?= SUPABASE_URL ?>/rest/v1/tools?id=eq.' + encodeURIComponent(id), {
        method: 'PATCH',
        headers: {
            'apikey': '<?= SUPABASE_KEY ?>',
            'Authorization': 'Bearer <?= SUPABASE_KEY ?>',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(fields)
    }).then(function(r) {
        if (r.ok) {
            if (onSuccess) onSuccess();
        } else {
            alert('Nie udało się zapisać zmian.');
        }
    });
}

// Select ma wartości 'null'/'true'/'false' jako stringi (HTML nie ma
// natywnego typu boolean/null dla <option>) — triStateFromSelect konwertuje
// to na prawdziwe true/false/null PRZED JSON.stringify, tak żeby PATCH
// wysłał literalny JSON null (nie string "null"). Używane przez modal
// edycji (saveEditModal) przy odczycie jego trzech selectów.
function triStateFromSelect(value) {
    if (value === 'true') return true;
    if (value === 'false') return false;
    return null;
}

// ---------- Modal edycji narzędzia ----------
// Jedyna ścieżka edycji URL/kategorii/logo/RODO/DPA/Hosting UE — dawne
// mini-formularze inline w wierszu usunięte. Jeden PATCH na wszystkie
// 6 pól naraz (przez wspólny patchTool()), zamiast sześciu osobnych
// requestów jak przy dawnym inline-save.

var editTargetId = null;

function triStateToSelectValue(value) {
    if (value === true) return 'true';
    if (value === false) return 'false';
    return 'null';
}

function openEditModalFromButton(btn) {
    var id = btn.dataset.toolId;
    var tool = JSON.parse(btn.dataset.tool);
    openEditModal(id, tool);
}

function openEditModal(id, tool) {
    editTargetId = id;
    document.getElementById('edit-website_url').value = tool.website_url || '';
    document.getElementById('edit-category_id').value = tool.category_id || '';
    document.getElementById('edit-logo_url').value = tool.logo_url || '';
    document.getElementById('edit-rodo_compliant').value = triStateToSelectValue(tool.rodo_compliant);
    document.getElementById('edit-dpa_available').value = triStateToSelectValue(tool.dpa_available);
    document.getElementById('edit-eu_data_hosting').value = triStateToSelectValue(tool.eu_data_hosting);
    document.getElementById('edit-modal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('edit-modal').style.display = 'none';
    editTargetId = null;
}

// Kolory/etykiety plakietki 3-stanowej w JS — musi zostać zsynchronizowane
// z tri_state_badge() w PHP (admin/index.php), jeśli któreś się zmieni.
function triStateBadgeInfo(value) {
    if (value === true)  return { cls: 'badge-green', label: '✓ Tak' };
    if (value === false) return { cls: 'badge-red',   label: '✗ Nie' };
    return { cls: 'badge-gray', label: 'Nie zweryf.' };
}

// Aktualizuje wyłącznie plakietkę danego pola 3-stanowego w wierszu —
// od usunięcia inline mini-formularzy w wierszu nie ma już odpowiadającego
// <select>u do zsynchronizowania, jest tylko badge (patrz render_tri_state_badge
// w PHP).
function updateTriStateDisplay(id, prefix, value) {
    var badge = document.getElementById(prefix + '-badge-' + id);
    if (badge) {
        var info = triStateBadgeInfo(value);
        badge.className = 'badge ' + info.cls;
        badge.textContent = info.label;
    }
}

// Po udanym PATCH z modala: uaktualnia WIDOCZNE wartości w wierszu bez
// przeładowania strony. Od usunięcia inline mini-formularzy (URL/kategoria/
// logo/RODO/DPA/Hosting UE) jedyne, co faktycznie zostało w wierszu do
// zsynchronizowania, to tekst kolumny "Kategoria" i trzy plakietki
// 3-stanowe — URL i logo nie mają już żadnego odpowiednika w tabeli.
function updateRowAfterEdit(id, fields) {
    var categoryCell = document.getElementById('category-cell-' + id);
    if (categoryCell) {
        var categorySelect = document.getElementById('edit-category_id');
        var matchingOption = categorySelect
            ? Array.prototype.filter.call(categorySelect.options, function(opt) {
                return opt.value === (fields.category_id || '');
            })[0]
            : null;
        categoryCell.textContent = matchingOption ? matchingOption.text : '';
    }

    updateTriStateDisplay(id, 'rodo', fields.rodo_compliant);
    updateTriStateDisplay(id, 'dpa', fields.dpa_available);
    updateTriStateDisplay(id, 'eu', fields.eu_data_hosting);
}

function saveEditModal() {
    if (!editTargetId) return;
    var id = editTargetId;
    var fields = {
        website_url:     document.getElementById('edit-website_url').value.trim(),
        category_id:     document.getElementById('edit-category_id').value || null,
        logo_url:        document.getElementById('edit-logo_url').value.trim() || null,
        rodo_compliant:  triStateFromSelect(document.getElementById('edit-rodo_compliant').value),
        dpa_available:   triStateFromSelect(document.getElementById('edit-dpa_available').value),
        eu_data_hosting: triStateFromSelect(document.getElementById('edit-eu_data_hosting').value),
    };
    patchTool(id, fields, function() {
        updateRowAfterEdit(id, fields);
        closeEditModal();
    });
}

// ---------- Weryfikacja narzędzia przez AI ----------

var VERIFY_FIELD_DEFS = [
    { key: 'description',            oldKey: 'description',   dbField: 'description_pl', label: 'Opis',           defaultChecked: true },
    { key: 'category',               oldKey: 'category',      dbField: 'category_id',    label: 'Kategoria',      defaultChecked: true, useIdField: 'category_id' },
    { key: 'pricing_model',          oldKey: 'pricing_model', dbField: 'pricing_model',  label: 'Model cenowy',   defaultChecked: true },
    { key: 'best_for_pl',            oldKey: 'best_for_pl',   dbField: 'best_for_pl',    label: 'Najlepsze dla',  defaultChecked: true },
    { key: 'ai_act_risk_suggestion', oldKey: 'ai_act_risk',   dbField: 'ai_act_risk',    label: 'AI Act ryzyko',  defaultChecked: false },
    { key: 'logo_hint',              oldKey: 'logo_url',      dbField: 'logo_url',       label: 'Logo URL',       defaultChecked: false },
];

var verifyTargetId = null;
var verifyPatchValues = [];

function verifyTool(id, btn) {
    var original = btn.textContent;
    btn.disabled = true;
    btn.textContent = '⏳ Weryfikuję…';

    // Nazwa narzędzia jest już w DOM (pierwszy <strong> w tym samym wierszu,
    // patrz komórka "Nazwa") — czytamy ją stąd zamiast dociągać osobnym
    // zapytaniem, żeby nagłówek panelu "Znalezione sygnały" mógł jasno
    // wskazywać, do którego wiersza się odnosi.
    var row = btn.closest('tr');
    var nameEl = row ? row.querySelector('strong') : null;
    var toolName = nameEl ? nameEl.textContent : '';

    fetch('verify_tool.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'tool_id=' + encodeURIComponent(id)
    })
    .then(function(r) {
        return r.json().then(function(data) { return { ok: r.ok, data: data }; });
    })
    .then(function(res) {
        if (!res.ok || res.data.error) {
            alert(res.data.error || 'Weryfikacja nie powiodła się.');
            return;
        }
        verifyTargetId = id;
        openVerifyModal(res.data);
        renderComplianceEvidence(id, res.data.compliance_evidence, toolName);
    })
    .catch(function() {
        alert('Błąd sieci podczas weryfikacji.');
    })
    .finally(function() {
        btn.disabled = false;
        btn.textContent = original;
    });
}

// Cytaty ze strony dot. RODO/DPA/hostingu UE, znalezione przy okazji tego
// samego wywołania OpenAI co reszta weryfikacji — NIE ocena zgodności,
// samo rodo_compliant/dpa_available/eu_data_hosting zostaje manual-only.
// Renderowane bezpośrednio w wierszu tabeli, nad tymi samymi trójstanowymi
// plakietkami co zawsze (żadnej nowej logiki zapisu, edycja tylko przez
// #edit-modal) — nie w modalu weryfikacji, żeby cytat był fizycznie obok
// pól do ręcznego ustawienia, nie w osobnym popupie. Budowane przez
// DOM/textContent, nie innerHTML z surowym tekstem — cytat pochodzi ze
// scrapowanej, niezaufanej strony zewnętrznej. Nazwa narzędzia w nagłówku
// i wizualne "sklejenie" z wierszem (.evidence-panel, strzałka ▼) usuwają
// niejednoznaczność, do którego wiersza panel się odnosi.
var COMPLIANCE_EVIDENCE_LABELS = { rodo: 'RODO', dpa: 'DPA', eu_hosting: 'Hosting UE' };

function renderComplianceEvidence(id, evidence, toolName) {
    var row = document.getElementById('evidence-row-' + id);
    var content = document.getElementById('evidence-content-' + id);
    if (!row || !content) return;

    content.innerHTML = '';

    var keys = Object.keys(COMPLIANCE_EVIDENCE_LABELS).filter(function(key) {
        return evidence && typeof evidence[key] === 'string' && evidence[key] !== '';
    });

    if (keys.length === 0) {
        row.style.display = 'none';
        return;
    }

    var header = document.createElement('div');
    header.style.fontWeight = '600';
    header.style.marginBottom = '6px';
    header.textContent = toolName
        ? '🔍 Znalezione sygnały dla „' + toolName + '” (nie ocena zgodności)'
        : '🔍 Znalezione sygnały (nie ocena zgodności)';
    content.appendChild(header);

    keys.forEach(function(key) {
        var line = document.createElement('div');
        line.style.fontSize = '13px';
        line.style.marginBottom = '4px';

        var label = document.createElement('strong');
        label.textContent = COMPLIANCE_EVIDENCE_LABELS[key] + ': ';

        line.appendChild(label);
        line.appendChild(document.createTextNode('„' + evidence[key] + '”'));
        content.appendChild(line);
    });

    var disclaimer = document.createElement('div');
    disclaimer.className = 'verify-hint-note';
    disclaimer.style.marginTop = '4px';
    disclaimer.textContent = 'To są cytaty znalezione na stronie, nie ocena AI czy narzędzie jest zgodne z prawem. Sprawdź kontekst i ustaw pola RODO/DPA/Hosting UE poniżej ręcznie.';
    content.appendChild(disclaimer);

    row.style.display = 'table-row';
}

function verifyDisplayValue(v) {
    return (v === null || v === undefined || v === '') ? '(brak)' : String(v);
}

function openVerifyModal(data) {
    var body = document.getElementById('verify-modal-body');
    body.innerHTML = '';
    verifyPatchValues = [];

    VERIFY_FIELD_DEFS.forEach(function(def) {
        var oldVal = data.old ? data.old[def.oldKey] : null;
        var newVal = data.new ? data.new[def.key] : null;
        var patchVal = def.useIdField ? (data.new ? data.new[def.useIdField] : null) : newVal;
        var unresolved = !!def.useIdField && !patchVal;

        var idx = verifyPatchValues.length;
        verifyPatchValues.push({ dbField: def.dbField, value: patchVal });

        var row = document.createElement('div');
        row.className = 'verify-row';

        var label = document.createElement('label');
        label.className = 'verify-check';

        var cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.checked = def.defaultChecked && !unresolved;
        cb.disabled = unresolved;
        cb.dataset.index = String(idx);

        label.appendChild(cb);
        label.appendChild(document.createTextNode(' ' + def.label));
        if (unresolved) {
            var warn = document.createElement('span');
            warn.className = 'verify-warn';
            warn.textContent = ' (nie rozpoznano kategorii — pomiń lub popraw ręcznie)';
            label.appendChild(warn);
        }

        var compare = document.createElement('div');
        compare.className = 'verify-compare';

        var oldBox = document.createElement('div');
        oldBox.className = 'verify-old';
        oldBox.textContent = verifyDisplayValue(oldVal);

        var newBox = document.createElement('div');
        newBox.className = 'verify-new';
        newBox.textContent = verifyDisplayValue(newVal);

        compare.appendChild(oldBox);
        compare.appendChild(newBox);

        row.appendChild(label);
        row.appendChild(compare);
        body.appendChild(row);
    });

    // Podpowiedź ai_act_risk wg kategorii — tylko fallback, gdy strona nie dała
    // AI żadnej własnej sugestii (ai_act_risk_suggestion puste). Osobny wiersz,
    // wyraźnie odróżniony etykietą, żeby nie mylić z sugestią z treści strony.
    var categoryHint = data.new ? data.new.category_ai_act_hint : null;
    var pageSuggestion = data.new ? data.new.ai_act_risk_suggestion : null;
    if (categoryHint && !pageSuggestion) {
        var hintIdx = verifyPatchValues.length;
        verifyPatchValues.push({ dbField: 'ai_act_risk', value: categoryHint });

        var hintRow = document.createElement('div');
        hintRow.className = 'verify-row';

        var hintLabel = document.createElement('label');
        hintLabel.className = 'verify-check';

        var hintCb = document.createElement('input');
        hintCb.type = 'checkbox';
        hintCb.checked = false;
        hintCb.dataset.index = String(hintIdx);

        hintLabel.appendChild(hintCb);
        hintLabel.appendChild(document.createTextNode(' Sugestia wg kategorii (Załącznik III AI Act)'));

        var hintCompare = document.createElement('div');
        hintCompare.className = 'verify-compare';

        var hintOldBox = document.createElement('div');
        hintOldBox.className = 'verify-old';
        hintOldBox.textContent = verifyDisplayValue(data.old ? data.old.ai_act_risk : null);

        var hintNewBox = document.createElement('div');
        hintNewBox.className = 'verify-new';
        hintNewBox.textContent = verifyDisplayValue(categoryHint);

        hintCompare.appendChild(hintOldBox);
        hintCompare.appendChild(hintNewBox);

        var hintNote = document.createElement('div');
        hintNote.className = 'verify-hint-note';
        hintNote.textContent = 'Ogólna orientacja, nie porada prawna — zweryfikuj indywidualnie';

        hintRow.appendChild(hintLabel);
        hintRow.appendChild(hintCompare);
        hintRow.appendChild(hintNote);
        body.appendChild(hintRow);
    }

    document.getElementById('verify-modal').style.display = 'flex';
}

function closeVerifyModal() {
    document.getElementById('verify-modal').style.display = 'none';
    verifyTargetId = null;
    verifyPatchValues = [];
}

function formatPlDate(isoString) {
    var d = new Date(isoString);
    var pad = function(n) { return String(n).padStart(2, '0'); };
    return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear();
}

function applyVerifiedChanges() {
    var checks = document.querySelectorAll('#verify-modal-body input[type=checkbox]:checked');
    var payload = {};
    checks.forEach(function(cb) {
        var entry = verifyPatchValues[Number(cb.dataset.index)];
        payload[entry.dbField] = entry.value;
    });

    if (Object.keys(payload).length === 0) {
        closeVerifyModal();
        return;
    }

    var targetId = verifyTargetId;
    var verifiedAt = new Date().toISOString();
    payload.ai_verified_at = verifiedAt;

    fetch('<?= SUPABASE_URL ?>/rest/v1/tools?id=eq.' + encodeURIComponent(targetId), {
        method: 'PATCH',
        headers: {
            'apikey': '<?= SUPABASE_KEY ?>',
            'Authorization': 'Bearer <?= SUPABASE_KEY ?>',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    }).then(function(r) {
        if (r.ok) {
            var verifiedEl = document.getElementById('verified-' + targetId);
            if (verifiedEl) verifiedEl.textContent = 'Sprawdzono: ' + formatPlDate(verifiedAt);
            closeVerifyModal();
        } else {
            alert('Nie udało się zapisać zmian.');
        }
    });
}
</script>
</body>
</html>
