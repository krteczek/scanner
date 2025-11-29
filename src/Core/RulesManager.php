<?php
declare(strict_types=1);

namespace Scanner\Core;

use Scanner\Logger\Logger;

/**
 * Správce AI pravidel pro skenování kódu
 * Zajišťuje načítání, ukládání, import a export pravidel validace
 * Spravuje kategorie pravidel a jejich konfiguraci
 * 
 * @package Scanner\Core
 * @author KRS3  
 * @version 2.0
 */
class RulesManager
{
    /** @var array Konfigurace pravidel z rules.php */
    private array $rulesConfig;

    /** @var array Aktuálně načtená pravidla */
    private array $currentRules;

    /** @var array Kategorie pravidel a jejich definice */
    private array $ruleCategories;

    /** @var Logger Instance loggeru pro zaznamenávání operací */
    private Logger $logger;

    /**
     * Inicializuje správce pravidel
     * Načte konfiguraci a nastaví logger
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->loadRules();
    }

    /**
     * Načte pravidla z konfiguračního souboru rules.php
     * Inicializuje aktuální pravidla a kategorie
     *
     * @return void
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
     * Zpracuje odeslání formuláře a aktualizuje pravidla
     * Převádí POST data na strukturovaná pravidla a ukládá je
     *
     * @param array $postData POST data z formuláře pravidel
     * @return string Zpráva o úspěchu/chybě operace
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
     * Importuje pravidla z JSON dat
     * Validuje JSON strukturu a ukládá nová pravidla
     *
     * @param string $jsonData JSON řetězec s daty pravidel
     * @return string Zpráva o úspěchu/chybě importu
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
     * Uloží pravidla do konfiguračního souboru
     * Generuje PHP kód s exportem pole a zapisuje do souboru
     *
     * @param array $rules Nová pravidla k uložení
     * @return bool True pokud bylo uložení úspěšné
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
     * Získá aktuální pravidla pro export do JSON
     * Vrací strukturovaná data vhodná pro serializaci
     *
     * @return array Data pravidel pro JSON export
     */
    public function getRulesForExport(): array
    {
        return [
            'rules' => $this->currentRules,
            'categories' => $this->ruleCategories
        ];
    }

    /**
     * Získá aktuální pravidla a kategorie pro zobrazení v šabloně
     * Data jsou připravena pro použití v rules_view.php
     *
     * @return array Data pravidel pro šablonu
     */
    public function getRulesForView(): array
    {
        return [
            'currentRules' => $this->currentRules,
            'ruleCategories' => $this->ruleCategories
        ];
    }
}