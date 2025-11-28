<?php
declare(strict_types=1);

namespace Scanner\Core;

use Scanner\Logger\Logger;

/**
 * Controller pro správu AI pravidel
 * 
 * @package Scanner\Core
 * @author KRS3
 * @version 2.0
 */
class RulesController
{
    private Logger $logger;

    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->logger->info('RulesController initialized');
    }

    /**
     * Spustí controller pro správu pravidel
     *
     * @return void
     */
    public function run(): void
    {
        $rulesManager = new RulesManager();
        $message = '';

        // Zpracování formuláře
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->logger->info('Processing rules form submission');
            
            if (isset($_POST['import_rules'])) {
                $message = $rulesManager->importRules($_POST['import_data'] ?? '');
                $this->logger->info('Rules import attempted', ['success' => !empty($message)]);
            } else {
                $message = $rulesManager->processFormSubmission($_POST);
                $this->logger->info('Rules form processed', ['success' => !empty($message)]);
            }
        }

        // Export pravidel
        if (isset($_GET['export'])) {
            $this->logger->info('Exporting rules to JSON');
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="ai_rules_export.json"');
            echo json_encode($rulesManager->getRulesForExport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Načtení dat pro view
        $viewData = $rulesManager->getRulesForView();
        extract($viewData);

        $this->logger->debug('Loading rules view template');

        // Načtení template
        include __DIR__ . '/templates/rules_view.php';
    }
}

// Spuštění controlleru
$controller = new RulesController();
$controller->run();