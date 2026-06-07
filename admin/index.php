<?php
session_start();
require_once '/home/siwy126/domains/aifirmy.pl/private_html/config/db.php';

// Wylogowanie
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /admin/');
    exit;
}

// Logowanie
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: /admin/');
        exit;
    } else {
        $error = 'Nieprawidłowe hasło';
    }
}

// Sprawdź czy zalogowany
$logged_in = isset($_SESSION['admin']) && $_SESSION['admin'] === true;
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
        .desc { max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
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
    <a href="?logout=1">Wyloguj →</a>
</div>

<div class="container">
    <?php
    $pdo = getDB();
    $tab = $_GET['tab'] ?? 'queue';

    // Akcje moderacji
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && isset($_POST['id'])) {
            $id = $_POST['id'];
            $action = $_POST['action'];
            if ($action === 'publish') {
                $pdo->prepare("UPDATE scrape_queue SET stage = 'published' WHERE id = ?")->execute([$id]);
            } elseif ($action === 'reject') {
                $pdo->prepare("UPDATE scrape_queue SET stage = 'rejected' WHERE id = ?")->execute([$id]);
            }
        }
        header('Location: /admin/?tab=' . $tab);
        exit;
    }

    // Statystyki
    $stats = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM scrape_queue WHERE stage = 'ai_done') AS czeka,
            (SELECT COUNT(*) FROM tools WHERE status = 'approved') AS approved,
            (SELECT COUNT(*) FROM scrape_queue WHERE stage = 'published') AS opublikowane
    ")->fetch();
    ?>

    <div class="stats">
        <div class="stat">
            <div class="stat-val"><?= $stats['czeka'] ?></div>
            <div class="stat-lbl">Czeka na moderację</div>
        </div>
        <div class="stat">
            <div class="stat-val"><?= $stats['approved'] ?></div>
            <div class="stat-lbl">Zatwierdzone narzędzia</div>
        </div>
        <div class="stat">
            <div class="stat-val"><?= $stats['opublikowane'] ?></div>
            <div class="stat-lbl">Opublikowane łącznie</div>
        </div>
    </div>

    <div class="tabs">
        <a href="?tab=queue" class="tab <?= $tab === 'queue' ? 'active' : '' ?>">Kolejka (<?= $stats['czeka'] ?>)</a>
        <a href="?tab=tools" class="tab <?= $tab === 'tools' ? 'active' : '' ?>">Narzędzia</a>
        <a href="?tab=add" class="tab <?= $tab === 'add' ? 'active' : '' ?>">+ Dodaj wpis</a>
    </div>

    <?php if ($tab === 'queue'): ?>
    <?php
    $items = $pdo->query("
        SELECT id, raw_name, ai_description, ai_category, source_url, scraped_at
        FROM scrape_queue
        WHERE stage = 'ai_done'
        ORDER BY scraped_at DESC
        LIMIT 50
    ")->fetchAll();
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
            <td><strong><?= htmlspecialchars($item['raw_name']) ?></strong></td>
            <td class="desc"><?= htmlspecialchars($item['ai_description'] ?? '') ?></td>
            <td><span class="badge badge-blue"><?= htmlspecialchars($item['ai_category'] ?? '') ?></span></td>
            <td><a href="<?= htmlspecialchars($item['source_url'] ?? '') ?>" target="_blank" style="color:#4f46e5">Link →</a></td>
            <td><?= date('d.m H:i', strtotime($item['scraped_at'])) ?></td>
            <td>
                <div class="actions">
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="action" value="publish">
                        <button class="btn btn-success">✓</button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="action" value="reject">
                        <button class="btn btn-danger">✗</button>
                    </form>
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
    $tools = $pdo->query("
        SELECT t.id, t.slug, t.name, t.pricing_model, t.rodo_compliant, t.ai_act_risk, t.status, c.name_pl AS category
        FROM tools t
        LEFT JOIN categories c ON t.category_id = c.id
        ORDER BY t.created_at DESC
        LIMIT 100
    ")->fetchAll();
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
            <td><strong><?= htmlspecialchars($tool['name']) ?></strong><br><small style="color:#9ca3af"><?= $tool['slug'] ?></small></td>
            <td><?= htmlspecialchars($tool['category'] ?? '') ?></td>
            <td><span class="badge badge-gray"><?= $tool['pricing_model'] ?></span></td>
            <td><?= $tool['rodo_compliant'] ? '✅' : '❌' ?></td>
            <td><?= htmlspecialchars($tool['ai_act_risk'] ?? '') ?></td>
            <td><span class="badge <?= $tool['status'] === 'approved' ? 'badge-green' : 'badge-gray' ?>"><?= $tool['status'] ?></span></td>
            <td><a href="?tab=edit&id=<?= $tool['id'] ?>" class="btn btn-secondary">Edytuj</a></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <?php elseif ($tab === 'add'): ?>
    <?php
    $categories = $pdo->query("SELECT id, name_pl FROM categories ORDER BY sort_order")->fetchAll();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_tool'])) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $_POST['name']));
        $stmt = $pdo->prepare("
            INSERT INTO tools (slug, name, tagline_pl, description_pl, website_url, category_id, pricing_model, rodo_compliant, ai_act_risk, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')
        ");
        $stmt->execute([
            $slug,
            $_POST['name'],
            $_POST['tagline_pl'],
            $_POST['description_pl'],
            $_POST['website_url'],
            $_POST['category_id'] ?: null,
            $_POST['pricing_model'],
            isset($_POST['rodo_compliant']) ? 'true' : 'false',
            $_POST['ai_act_risk'],
        ]);
        header('Location: /admin/?tab=tools');
        exit;
    }
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
                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name_pl']) ?></option>
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
</body>
</html>