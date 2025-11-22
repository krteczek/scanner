<?php
// scanner/src/Core/¨¨
/**
 * Správce pravidel - Dynamická verze
 *
 * @package Scanner\Core
 * @author KRS3
 * @version 2.0
 */

declare(strict_types=1);

// Načtení pravidel
$rulesConfig = @include __DIR__ . '/../../config/rules.php';
$currentRules = $rulesConfig['rules'] ?? [];
$ruleCategories = $rulesConfig['rule_categories'] ?? [];

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newRules = [];

    // Dynamické načtení hodnot z formuláře podle definic
    foreach ($ruleCategories as $categoryKey => $category) {
        $newRules[$categoryKey] = [];

        foreach ($category['rules'] as $ruleKey => $ruleDef) {
            if ($ruleDef['type'] === 'boolean') {
                $newRules[$categoryKey][$ruleKey] = isset($_POST[$categoryKey . '_' . $ruleKey]);
            } elseif ($ruleDef['type'] === 'select') {
                $newRules[$categoryKey][$ruleKey] = $_POST[$categoryKey . '_' . $ruleKey] ?? $ruleDef['default'];
            }
        }
    }

    // Uložení pravidel
    if (saveRules($newRules, $rulesConfig)) {
        $currentRules = $newRules;
        $message = "✅ Pravidla úspěšně uložena!";
    } else {
        $message = "❌ Chyba při ukládání pravidel!";
    }
}

// Export pravidel
if (isset($_GET['export'])) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="ai_rules_export.json"');
    echo json_encode(['rules' => $currentRules, 'categories' => $ruleCategories], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Import pravidel
if (isset($_POST['import_rules'])) {
    $importData = json_decode($_POST['import_data'], true);
    if ($importData && isset($importData['rules'])) {
        if (saveRules($importData['rules'], $rulesConfig)) {
            $currentRules = $importData['rules'];
            $message = "✅ Pravidla úspěšně importována!";
        }
    } else {
        $message = "❌ Chyba při importu pravidel!";
    }
}

/**
 * Uloží pravidla do konfiguračního souboru
 *
 * @param array $rules Nová pravidla
 * @param array $rulesConfig Původní konfigurace
 * @return bool True pokud se uložení povedlo
 */
function saveRules(array $rules, array $rulesConfig): bool
{
    $rulesConfig['rules'] = $rules;
    $rulesContent = "<?php\n/**\n * AI Pravidla - automaticky generováno\n * Generated: " . date('Y-m-d H:i:s') . "\n */\n\ndeclare(strict_types=1);\n\nreturn " . var_export($rulesConfig, true) . ";\n?>";
    return file_put_contents(__DIR__ . '/../../config/rules.php', $rulesContent) !== false;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Správce AI Pravidel</title>
    <link rel="stylesheet" href="../public/style.css">
</head>
<body>
    <div class="container">
        <h1>⚙️ Správce AI Pravidel</h1>

        <?php if (isset($message)): ?>
            <div class="message">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="export-buttons">
            <a href="?export=1" class="btn" style="background:#27ae60">📥 Exportovat pravidla (JSON)</a>
            <a href="./../../index.php" class="btn">← Zpět na Scanner</a>
        </div>

        <!-- Import sekce -->
        <div class="import-section">
            <h3>📤 Import pravidel</h3>
            <form method="POST">
                <textarea name="import_data" placeholder='{"rules": {"koding_standardy": {"phpdoc_povinne": true}}}'></textarea>
                <button type="submit" name="import_rules" style="background:#e67e22; color:white; padding:8px 15px; border:none; border-radius:5px; margin-top:10px;">
                    📤 Importovat JSON
                </button>
            </form>
        </div>

        <!-- Hlavní formulář -->
        <form method="POST" class="rules-form">
            <?php foreach ($ruleCategories as $categoryKey => $category): ?>
                <div class="rule-section">
                    <h3 class="category-title"><?= htmlspecialchars($category['label']) ?></h3>

                    <?php foreach ($category['rules'] as $ruleKey => $ruleDef): ?>
                        <div class="rule-item">
                            <?php if ($ruleDef['type'] === 'boolean'): ?>
                                <label style="display: block; cursor: pointer;">
                                    <input type="checkbox"
                                           name="<?= $categoryKey ?>_<?= $ruleKey ?>"
                                           <?= ($currentRules[$categoryKey][$ruleKey] ?? $ruleDef['default']) ? 'checked' : '' ?>>
                                    <strong><?= htmlspecialchars($ruleDef['label']) ?></strong>
                                </label>

                            <?php elseif ($ruleDef['type'] === 'select'): ?>
                                <label><strong><?= htmlspecialchars($ruleDef['label']) ?>:</strong></label>
                                <select name="<?= $categoryKey ?>_<?= $ruleKey ?>" style="margin-left: 10px; padding: 5px;">
                                    <?php foreach ($ruleDef['options'] as $value => $label): ?>
                                        <option value="<?= $value ?>"
                                                <?= ($currentRules[$categoryKey][$ruleKey] ?? $ruleDef['default']) === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" style="background:#3498db; color:white; padding:12px 30px; border:none; border-radius:5px; cursor:pointer; font-size:16px;">
                    💾 Uložit všechna pravidla
                </button>
            </div>
        </form>

        <div style="text-align: center; margin-top: 20px; color: #666; font-size: 0.9em;">
            <p>💡 <strong>Dynamický systém:</strong> Přidávej nová pravidla pouze do pole <code>rule_categories</code> v <code>config/rules.php</code></p>
        </div>
    </div>
</body>
</html>