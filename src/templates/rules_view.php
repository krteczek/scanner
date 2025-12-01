<?php
declare(strict_types=1);

namespace Scanner\Templates;

/**
 * Template pro správu AI pravidel
 * 
 * @package Scanner\Templates
 * @author KRS3
 * @version 2.0
 */
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
            <a href="?action=rules&export=1" class="btn" style="background:#27ae60">📥 Exportovat pravidla (JSON)</a>
            <a href="./index.php" class="btn">← Zpět na Scanner</a>
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