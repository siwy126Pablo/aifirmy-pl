<?php
session_start();
require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/db.php';

define('SUPABASE_URL', 'https://szassqzvivdgvpkciyif.supabase.co');
define('SUPABASE_KEY', SUPABASE_ANON_KEY ); // ← wklej swój anon key

const DEFAULT_DISCLOSURE = 'Ten link jest linkiem afiliacyjnym — możemy otrzymać prowizję.';

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

// ---------- auth ----------

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: /admin/affiliate.php');
        exit;
    }
    $error = 'Nieprawidłowe hasło';
}

$logged_in = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

// ---------- akcje POST (tylko gdy zalogowany) ----------

if ($logged_in && $_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['add_link'])) {
        sb_post('affiliate_links', [
            'tool_id'         => $_POST['tool_id'],
            'program_name'    => $_POST['program_name'],
            'affiliate_url'   => $_POST['affiliate_url'],
            'commission_note' => $_POST['commission_note'] ?: null,
            'disclosure_text' => $_POST['disclosure_text'] ?: DEFAULT_DISCLOSURE,
        ]);
        header('Location: /admin/affiliate.php');
        exit;
    }

    if (isset($_POST['update_link'], $_POST['link_id'])) {
        sb_patch('affiliate_links', $_POST['link_id'], [
            'tool_id'         => $_POST['tool_id'],
            'program_name'    => $_POST['program_name'],
            'affiliate_url'   => $_POST['affiliate_url'],
            'commission_note' => $_POST['commission_note'] ?: null,
            'disclosure_text' => $_POST['disclosure_text'] ?: DEFAULT_DISCLOSURE,
        ]);
        header('Location: /admin/affiliate.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Linki afiliacyjne — Panel admina — aifirmy.pl</title>
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
        .btn-primary { background: #4f46e5; color: white; width: 100%; }
        .btn-secondary { background: #e5e7eb; color: #374151; }
        .error { color: #dc2626; font-size: 14px; margin-bottom: 12px; }
        .tabs { display: flex; gap: 8px; margin-bottom: 24px; }
        .tab { padding: 8px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; background: white; border: 1px solid #ddd; text-decoration: none; color: #374151; }
        .tab.active { background: #4f46e5; color: white; border-color: #4f46e5; }
        table { width: 100%; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.08); border-collapse: collapse; }
        th { background: #f9fafb; padding: 12px 16px; text-align: left; font-size: 12px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        td { padding: 12px 16px; border-bottom: 1px solid #f3f4f6; font-size: 14px; vertical-align: top; }
        tr:last-child td { border-bottom: none; }
        .badge { padding: 4px 10px; border-radius: 99px; font-size: 12px; font-weight: 500; border: none; cursor: pointer; }
        .badge-green { background: #dcfce7; color: #16a34a; }
        .badge-gray { background: #f3f4f6; color: #6b7280; }
        .desc { max-width: 320px; white-space: normal; line-height: 1.5; }
        .form-box { background:white;padding:32px;border-radius:12px;box-shadow:0 1px 4px rgba(0,0,0,0.08);max-width:700px }
        .form-box h2 { margin-bottom:24px;font-size:18px }
        .form-box form { display:flex;flex-direction:column;gap:16px }
        .form-box label { font-size:13px;font-weight:500;display:block;margin-bottom:6px }
        .form-box input, .form-box textarea, .form-box select { width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:14px }
        .form-box textarea { resize:vertical }
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
    <strong>aifirmy.pl — Linki afiliacyjne</strong>
    <div class="links">
        <a href="/admin/index.php">← Panel główny</a>
        <a href="?logout=1">Wyloguj →</a>
    </div>
</div>

<div class="container">
<?php
$tab = $_GET['tab'] ?? 'list';
?>

    <div class="tabs">
        <a href="?tab=list" class="tab <?= $tab === 'list' ? 'active' : '' ?>">Lista</a>
        <a href="?tab=add"  class="tab <?= $tab === 'add'  ? 'active' : '' ?>">+ Dodaj link</a>
    </div>

    <?php if ($tab === 'list'): ?>
    <?php
    $links = sb_get(
        'affiliate_links' .
        '?order=created_at.desc' .
        '&limit=100' .
        '&select=id,program_name,affiliate_url,commission_note,active,created_at,tools(name)'
    );
    ?>
    <table>
        <tr>
            <th>Narzędzie</th>
            <th>Program</th>
            <th>Status</th>
            <th>Prowizja</th>
            <th>Data</th>
            <th></th>
        </tr>
        <?php foreach ($links as $link): ?>
        <tr>
            <td><strong><?= htmlspecialchars($link['tools']['name'] ?? '—') ?></strong></td>
            <td>
                <a href="?tab=edit&id=<?= urlencode($link['id']) ?>" style="color:#4f46e5;text-decoration:none">
                    <?= htmlspecialchars($link['program_name']) ?>
                </a>
            </td>
            <td>
                <button
                    class="badge <?= $link['active'] ? 'badge-green' : 'badge-gray' ?>"
                    onclick="toggleActive('<?= htmlspecialchars($link['id']) ?>', <?= $link['active'] ? 'true' : 'false' ?>, this)"
                ><?= $link['active'] ? 'Aktywny' : 'Nieaktywny' ?></button>
            </td>
            <td class="desc"><?= htmlspecialchars($link['commission_note'] ?? '') ?></td>
            <td><?= $link['created_at'] ? date('d.m.Y', strtotime($link['created_at'])) : '' ?></td>
            <td><a href="?tab=edit&id=<?= urlencode($link['id']) ?>" class="btn btn-secondary" style="font-size:12px;padding:4px 10px">Edytuj</a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($links)): ?>
        <tr><td colspan="6" style="text-align:center;color:#9ca3af;padding:40px">Brak linków afiliacyjnych</td></tr>
        <?php endif; ?>
    </table>

    <?php elseif ($tab === 'add'): ?>
    <?php
    $tools = sb_get('tools?order=name&select=id,name');
    ?>
    <div class="form-box">
        <h2>Dodaj link afiliacyjny</h2>
        <form method="POST">
            <div>
                <label>Narzędzie *</label>
                <select name="tool_id" required>
                    <option value="">— wybierz —</option>
                    <?php foreach ($tools as $t): ?>
                    <option value="<?= htmlspecialchars($t['id']) ?>"><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Nazwa programu *</label>
                <input type="text" name="program_name" placeholder="np. ClickUp przez PartnerStack" required>
            </div>
            <div>
                <label>URL afiliacyjny *</label>
                <input type="url" name="affiliate_url" required>
            </div>
            <div>
                <label>Notatka o prowizji</label>
                <textarea name="commission_note" rows="2" placeholder="np. cookie 180 dni, Tier 2 Polska, $10/signup"></textarea>
            </div>
            <div>
                <label>Tekst ujawnienia (disclosure)</label>
                <textarea name="disclosure_text" rows="2"><?= htmlspecialchars(DEFAULT_DISCLOSURE) ?></textarea>
            </div>
            <button type="submit" name="add_link" class="btn btn-primary" style="align-self:flex-start;padding:12px 32px">Dodaj link</button>
        </form>
    </div>

    <?php elseif ($tab === 'edit' && isset($_GET['id'])): ?>
    <?php
    $id      = $_GET['id'];
    $records = sb_get('affiliate_links?id=eq.' . urlencode($id) . '&select=id,tool_id,program_name,affiliate_url,commission_note,disclosure_text');
    $record  = $records[0] ?? null;
    $tools   = sb_get('tools?order=name&select=id,name');
    ?>
    <?php if (!$record): ?>
    <p style="color:#9ca3af">Nie znaleziono wpisu.</p>
    <?php else: ?>
    <div class="form-box">
        <h2>Edytuj link afiliacyjny</h2>
        <form method="POST">
            <input type="hidden" name="link_id" value="<?= htmlspecialchars($record['id']) ?>">
            <div>
                <label>Narzędzie *</label>
                <select name="tool_id" required>
                    <?php foreach ($tools as $t): ?>
                    <option value="<?= htmlspecialchars($t['id']) ?>" <?= $t['id'] === $record['tool_id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Nazwa programu *</label>
                <input type="text" name="program_name" value="<?= htmlspecialchars($record['program_name']) ?>" required>
            </div>
            <div>
                <label>URL afiliacyjny *</label>
                <input type="url" name="affiliate_url" value="<?= htmlspecialchars($record['affiliate_url']) ?>" required>
            </div>
            <div>
                <label>Notatka o prowizji</label>
                <textarea name="commission_note" rows="2"><?= htmlspecialchars($record['commission_note'] ?? '') ?></textarea>
            </div>
            <div>
                <label>Tekst ujawnienia (disclosure)</label>
                <textarea name="disclosure_text" rows="2"><?= htmlspecialchars($record['disclosure_text']) ?></textarea>
            </div>
            <button type="submit" name="update_link" class="btn btn-primary" style="align-self:flex-start;padding:12px 32px">Zapisz zmiany</button>
        </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>

</div>
<?php endif; ?>
<script>
function toggleActive(id, currentActive, btn) {
    const newVal = !currentActive;
    fetch('<?= SUPABASE_URL ?>/rest/v1/affiliate_links?id=eq.' + encodeURIComponent(id), {
        method: 'PATCH',
        headers: {
            'apikey': '<?= SUPABASE_KEY ?>',
            'Authorization': 'Bearer <?= SUPABASE_KEY ?>',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({active: newVal})
    }).then(function(r) {
        if (!r.ok) return;
        btn.textContent = newVal ? 'Aktywny' : 'Nieaktywny';
        btn.className = 'badge ' + (newVal ? 'badge-green' : 'badge-gray');
        btn.setAttribute('onclick', "toggleActive('" + id + "', " + newVal + ", this)");
    });
}
</script>
</body>
</html>
