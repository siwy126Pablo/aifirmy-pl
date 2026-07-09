<?php
session_start();
require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/db.php';

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
        }
        header('Location: /admin/index.php?tab=' . $tab);
        exit;
    }

    if (isset($_POST['add_tool'])) {
        sb_post('tools', [
            'slug'          => slugify($_POST['name']),
            'name'          => $_POST['name'],
            'tagline_pl'    => $_POST['tagline_pl'] ?: null,
            'description_pl'=> $_POST['description_pl'] ?: null,
            'website_url'   => $_POST['website_url'],
            'category_id'   => $_POST['category_id'] ?: null,
            'pricing_model' => $_POST['pricing_model'],
            'rodo_compliant'=> isset($_POST['rodo_compliant']),
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
        .desc { max-width: 400px; white-space: normal; line-height: 1.5; }
        .actions { display: flex; gap: 8px; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,0.08); }
        .stat-val { font-size: 32px; font-weight: 600; color: #4f46e5; }
        .stat-lbl { font-size: 13px; color: #6b7280; margin-top: 4px; }
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
        <a href="?logout=1">Wyloguj →</a>
    </div>
</div>

<div class="container">
<?php
$tab = $_GET['tab'] ?? 'queue';

$czeka      = sb_count('scrape_queue', 'stage=eq.ai_done');
$approved   = sb_count('tools', 'status=eq.approved');
$opublikowane = sb_count('scrape_queue', 'stage=eq.published');
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

    <?php elseif ($tab === 'tools'): ?>
    <?php
    $tools = sb_get(
        'tools' .
        '?status=eq.approved' .
        '&order=created_at.desc' .
        '&limit=100' .
        '&select=id,slug,name,website_url,category_id,pricing_model,rodo_compliant,ai_act_risk,status,categories(name_pl)'
    );
    $tools_categories = sb_get('categories?order=sort_order&select=id,name_pl');
    ?>
    <table>
        <tr>
            <th>Nazwa</th>
            <th>Kategoria</th>
            <th>Cennik</th>
            <th>RODO</th>
            <th>AI Act</th>
            <th>Status</th>
            <th>Akcja</th>
        </tr>
        <?php foreach ($tools as $tool): ?>
        <tr>
            <td>
                <strong><?= htmlspecialchars($tool['name']) ?></strong><br><small style="color:#9ca3af"><?= htmlspecialchars($tool['slug']) ?></small>
                <div style="margin-top:6px">
                    <input type="text" id="url-<?= htmlspecialchars($tool['id']) ?>" value="<?= htmlspecialchars($tool['website_url'] ?? '') ?>" style="font-size:12px;padding:4px 6px;border:1px solid #ddd;border-radius:4px;width:200px">
                    <button class="btn btn-secondary" style="font-size:12px;padding:4px 8px" onclick="saveUrl('<?= htmlspecialchars($tool['id']) ?>')">Zapisz URL</button>
                    <select id="cat-<?= htmlspecialchars($tool['id']) ?>" style="font-size:12px;padding:4px 6px;border:1px solid #ddd;border-radius:4px">
                        <?php foreach ($tools_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['id']) ?>" <?= $cat['id'] === $tool['category_id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name_pl']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-secondary" style="font-size:12px;padding:4px 8px" onclick="saveCategory('<?= htmlspecialchars($tool['id']) ?>')">Zapisz kategorię</button>
                    <span id="msg-<?= htmlspecialchars($tool['id']) ?>" style="font-size:12px;color:#16a34a"></span>
                </div>
            </td>
            <td><?= htmlspecialchars($tool['categories']['name_pl'] ?? '') ?></td>
            <td><span class="badge badge-gray"><?= htmlspecialchars($tool['pricing_model'] ?? '') ?></span></td>
            <td><?= $tool['rodo_compliant'] ? '✅' : '❌' ?></td>
            <td><?= htmlspecialchars($tool['ai_act_risk'] ?? '') ?></td>
            <td><span class="badge <?= $tool['status'] === 'approved' ? 'badge-green' : 'badge-gray' ?>"><?= htmlspecialchars($tool['status']) ?></span></td>
            <td><button class="btn btn-delete" onclick="softDelete('<?= htmlspecialchars($tool['id']) ?>', 'tools', 'status', this.closest('tr'))">Usuń</button></td>
        </tr>
        <?php endforeach; ?>
    </table>

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
                <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">Kategoria</label>
                <select name="category_id" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                    <option value="">— wybierz —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['id']) ?>"><?= htmlspecialchars($cat['name_pl']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
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
                    <label style="font-size:13px;font-weight:500;display:block;margin-bottom:6px">AI Act ryzyko</label>
                    <select name="ai_act_risk" style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px">
                        <option value="minimal" selected>Minimalne</option>
                        <option value="limited">Ograniczone</option>
                        <option value="high">Wysokie</option>
                    </select>
                </div>
                <div style="display:flex;align-items:center;gap:8px;padding-top:28px">
                    <input type="checkbox" name="rodo_compliant" id="rodo" checked>
                    <label for="rodo" style="font-size:14px">RODO zgodny</label>
                </div>
            </div>
            <button type="submit" name="add_tool" class="btn btn-primary" style="align-self:flex-start;padding:12px 32px">Dodaj narzędzie</button>
        </form>
    </div>
    <?php endif; ?>

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

function saveUrl(id) {
    var input = document.getElementById('url-' + id);
    var newUrl = input.value;
    fetch('<?= SUPABASE_URL ?>/rest/v1/tools?id=eq.' + encodeURIComponent(id), {
        method: 'PATCH',
        headers: {
            'apikey': '<?= SUPABASE_KEY ?>',
            'Authorization': 'Bearer <?= SUPABASE_KEY ?>',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({website_url: newUrl})
    }).then(function(r) {
        if (r.ok) input.value = newUrl;
    });
}

function saveCategory(id) {
    var select = document.getElementById('cat-' + id);
    var msg = document.getElementById('msg-' + id);
    fetch('<?= SUPABASE_URL ?>/rest/v1/tools?id=eq.' + encodeURIComponent(id), {
        method: 'PATCH',
        headers: {
            'apikey': '<?= SUPABASE_KEY ?>',
            'Authorization': 'Bearer <?= SUPABASE_KEY ?>',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({category_id: select.value})
    }).then(function(r) {
        if (r.ok) {
            msg.textContent = 'Zapisano';
            setTimeout(function() { msg.textContent = ''; }, 2000);
        }
    });
}
</script>
</body>
</html>
