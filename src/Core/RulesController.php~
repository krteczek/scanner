<?php
declare(strict_types=1);

namespace Scanner\Core;

require_once __DIR__ . '/RulesManager.php';

$rulesManager = new RulesManager();
$message = '';

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['import_rules'])) {
        $message = $rulesManager->importRules($_POST['import_data'] ?? '');
    } else {
        $message = $rulesManager->processFormSubmission($_POST);
    }
}

// Export pravidel
if (isset($_GET['export'])) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="ai_rules_export.json"');
    echo json_encode($rulesManager->getRulesForExport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Načtení dat pro view
$viewData = $rulesManager->getRulesForView();
extract($viewData);

// Načtení template
include __DIR__ . '/templates/rules_view.php';