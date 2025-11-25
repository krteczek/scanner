<?php
declare(strict_types=1);

namespace Scanner\Core;

use Scanner\Logger\Logger;

/**
 * RulesManager - manages scanning rules and validations
 * 
 * @package Scanner\Core
 * @author KRS3  
 * @version 2.0
 */
class RulesManager
{
    private array $rulesConfig;
    private array $currentRules;
    private array $ruleCategories;
    private Logger $logger;

    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->loadRules();
    }

    /**
     * Load rules from configuration file
     */
    private function loadRules(): void
    {
        $this->rulesConfig = @include __DIR__ . '/../../config/rules.php';
        $this->currentRules = $this->rulesConfig['rules'] ?? [];
        $this->ruleCategories = $this->rulesConfig['rule_categories'] ?? [];
        
        $this->logger->debug('Rules loaded', [
            'categories_count' => count($this->ruleCategories),
            'rules_count' => count($this->currentRules)
        ]);
    }

    /**
     * Process form submission and update rules
     * 
     * @param array $postData POST data from form
     * @return string Success/error message
     */
    public function processFormSubmission(array $postData): string
    {
        $this->logger->info('Processing rules form submission');
        
        $newRules = [];
        
        foreach ($this->ruleCategories as $categoryKey => $category) {
            $newRules[$categoryKey] = [];
            
            foreach ($category['rules'] as $ruleKey => $ruleDef) {
                $formFieldName = $categoryKey . '_' . $ruleKey;
                
                if ($ruleDef['type'] === 'boolean') {
                    $newRules[$categoryKey][$ruleKey] = isset($postData[$formFieldName]);
                } elseif ($ruleDef['type'] === 'select') {
                    $newRules[$categoryKey][$ruleKey] = $postData[$formFieldName] ?? $ruleDef['default'];
                }
            }
        }
        
        if ($this->saveRules($newRules)) {
            $this->currentRules = $newRules;
            $this->logger->info('Rules successfully saved');
            return "✅ Pravidla úspěšně uložena!";
        }
        
        $this->logger->error('Failed to save rules');
        return "❌ Chyba při ukládání pravidel!";
    }

    /**
     * Import rules from JSON data
     * 
     * @param string $jsonData JSON rules data
     * @return string Success/error message
     */
    public function importRules(string $jsonData): string
    {
        $this->logger->info('Importing rules from JSON');
        
        $importData = json_decode($jsonData, true);
        if ($importData && isset($importData['rules'])) {
            if ($this->saveRules($importData['rules'])) {
                $this->currentRules = $importData['rules'];
                $this->logger->info('Rules successfully imported');
                return "✅ Pravidla úspěšně importována!";
            }
        }
        
        $this->logger->error('Failed to import rules - invalid JSON data');
        return "❌ Chyba při importu pravidel!";
    }

    /**
     * Save rules to configuration file
     * 
     * @param array $rules New rules to save
     * @return bool True if successful
     */
    private function saveRules(array $rules): bool
    {
        $this->rulesConfig['rules'] = $rules;
        
        $rulesContent = "<?php\n/**\n * AI Pravidla - automaticky generováno\n * Generated: " . date('Y-m-d H:i:s') . "\n */\n\ndeclare(strict_types=1);\n\nreturn " . var_export($this->rulesConfig, true) . ";\n?>";
        
        $result = file_put_contents(__DIR__ . '/../../config/rules.php', $rulesContent) !== false;
        
        if ($result) {
            $this->logger->debug('Rules saved to file', ['rules_count' => count($rules)]);
        } else {
            $this->logger->error('Failed to save rules to file');
        }
        
        return $result;
    }

    /**
     * Get current rules for export
     * 
     * @return array Rules data for JSON export
     */
    public function getRulesForExport(): array
    {
        return [
            'rules' => $this->currentRules,
            'categories' => $this->ruleCategories
        ];
    }

    /**
     * Get current rules and categories for view
     * 
     * @return array Rules data for template
     */
    public function getRulesForView(): array
    {
        return [
            'currentRules' => $this->currentRules,
            'ruleCategories' => $this->ruleCategories
        ];
    }
}