<?php
// scanner/src/Services/ExportService.php

declare(strict_types=1);

namespace Scanner\Services;

use Scanner\Logger\Logger;
use Scanner\Logger\AdvancedLogger;
use Scanner\Services\CodeAnalyzer;
use Exception;

/**
 * Service pro generování textových exportů a AI kontextů
 * Zajišťuje formátování výsledků analýzy do čitelné podoby
 * Generuje detailní reporty problémů s návrhy na opravy
 * Vytváří strukturované výstupy pro AI asistenci
 * 
 * @package Scanner\Services
 * @author KRS3
 * @version 2.1
 */
class ExportService
{
    /** @var Logger Instance loggeru pro zaznamenávání operací */
    private Logger $logger;

    /** @var array Konfigurace aplikace */
    private array $config;

    /**
     * Inicializuje export service s konfigurací
     * Nastavuje logger a připravuje service pro generování exportů
     *
     * @param array $config Konfigurace aplikace
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->logger = Logger::getInstance();
        //$this->logger->info('ExportService initialized');
    }

    /**
     * Vygeneruje kompletní textový export projektu s detailní analýzou problémů
     * Kombinuje strukturu projektu, kontrolu souborů a výsledky analýzy kvality kódu
     *
     * @param string $projectName Název projektu
     * @param array $structure Projektová struktura jako pole zobrazených řádků
     * @param array $importantFiles Výsledky kontroly důležitých souborů [soubor => existuje]
     * @param string|null $projectPath Cesta k projektu pro analýzu kvality (volitelné)
     * @param array|null $aiRules Pravidla AI pro analýzu kódu (volitelné)
     * @return string Naformátovaný textový export s problémy
     */
    public function generateTextExport(
        string $projectName, 
        array $structure, 
        array $importantFiles, 
        ?string $projectPath = null, 
        ?array $aiRules = null
    ): string {
        $this->logger->info('Generating text export', ['project' => $projectName]);
        
        $export = "=== PROJECT EXPORT: $projectName ===\n";
        $export .= "Generated: " . date('Y-m-d H:i:s') . "\n";
        $export .= "========================================\n\n";

        $export .= "📁 PROJECT STRUCTURE:\n";
        foreach ($structure as $line) {
            $export .= $line . "\n";
        }

        $export .= "\n🎯 IMPORTANT FILES CHECK:\n";
        foreach ($importantFiles as $file => $exists) {
            $status = $exists ? '✅ EXISTS' : '❌ MISSING';
            $export .= "$status - $file\n";
        }

        // 🔍 DETAILNÍ ANALÝZA PROBLÉMŮ - OPRAVENÉ!
        if ($projectPath && $aiRules && is_dir($projectPath)) {
            try {
                $this->logger->debug('Running code quality analysis');
                $codeAnalyzer = new CodeAnalyzer($this->config);
                $qualityAnalysis = $codeAnalyzer->analyzeCodeQuality($projectPath, $aiRules);
                $export .= $this->generateDetailedProblemsSection($qualityAnalysis);
            } catch (Exception $e) {
                $this->logger->error('Code analysis failed', ['error' => $e->getMessage()]);
                $export .= "\n🔍 CODE QUALITY ANALYSIS: ❌ Chyba - {$e->getMessage()}\n";
            }
        } else {
            $this->logger->warning('Code quality analysis skipped - missing parameters');
            $export .= "\n🔍 CODE QUALITY ANALYSIS: ❌ Nedostupné (chybějící parametry)\n";
        }

        $export .= "\n=== END EXPORT ===\n";
        $this->logger->info('Text export generated successfully');
        return $export;
    }

    /**
     * Vygeneruje sekci s detailními problémy kvality kódu
     * Zobrazuje statistiky a strukturovaný výpis problémů podle závažnosti
     *
     * @param array $qualityAnalysis Výsledky analýzy kvality od CodeAnalyzer
     * @return string Naformátovaná sekce problémů pro textový export
     */
    private function generateDetailedProblemsSection(array $qualityAnalysis): string
    {
        $this->logger->debug('Generating detailed problems section');
        
        $section = "\n🔍 DETAILED CODE QUALITY ANALYSIS:\n";
        $section .= "  • Celkem souborů: {$qualityAnalysis['celkem_souboru']}\n";
        $section .= "  • Celkem řádků: {$qualityAnalysis['celkem_radku']}\n";
        $section .= "  • Soubory bez PHP Doc: " . count($qualityAnalysis['soubory_bez_phpdoc']) . "\n";
        $section .= "  • Soubory bez loggeru: " . count($qualityAnalysis['soubory_bez_loggeru']) . "\n";
        $section .= "  • Soubory bez namespaces: " . count($qualityAnalysis['soubory_bez_namespaces']) . "\n";

        // 🔍 STATISTIKA PROBLÉMŮ PODLE ZÁVAŽNOSTI
        $problemsBySeverity = $qualityAnalysis['problemy_podle_zavaznosti'] ?? [];
        $section .= "\n  🚨 PROBLÉMY PODLE ZÁVAŽNOSTI:\n";
        $section .= "    • Kritické: " . count($problemsBySeverity['critical'] ?? []) . "\n";
        $section .= "    • Chyby: " . count($problemsBySeverity['error'] ?? []) . "\n";
        $section .= "    • Varování: " . count($problemsBySeverity['warning'] ?? []) . "\n";
        $section .= "    • Informace: " . count($problemsBySeverity['info'] ?? []) . "\n";

        // 🔍 DETAILNÍ VÝPIS PROBLÉMŮ PRO KAŽDÝ SOUBOR
        if (!empty($qualityAnalysis['soubory_s_problemy'])) {
            $section .= $this->generateFileProblemsDetails($qualityAnalysis['soubory_s_problemy']);
        }

        // 📋 ZACHOVÁNÍ PŮVODNÍCH VÝPISŮ PRO KOMPATIBILITU
        $section .= $this->generateLegacyProblemsSections($qualityAnalysis);

        return $section;
    }

    /**
     * Vygeneruje detailní výpis problémů pro každý soubor s návrhy na opravu
     * Formátuje problémy do čitelné podoby s ikonami závažnosti a příklady
     *
     * @param array $filesWithProblems Asociativní pole [cesta_souboru => problémy]
     * @return string Naformátovaný výpis problémů pro textový export
     */
    private function generateFileProblemsDetails(array $filesWithProblems): string
    {
        $this->logger->debug('Generating file problems details', ['files_count' => count($filesWithProblems)]);
        
        $section = "\n  📋 DETAILNÍ PROBLÉMY V SOUBORECH:\n";
        
        foreach ($filesWithProblems as $filePath => $problems) {
            $fileName = basename($filePath);
            $section .= "\n     📄 {$fileName}:\n";
            
            foreach ($problems as $problem) {
                $severityIcon = $this->getSeverityIcon($problem['severity']);
                $section .= "       {$severityIcon} {$problem['description']}\n";
                $section .= "          💡 NÁVRH: {$problem['suggestion']}\n";
                
                if (!empty($problem['example'])) {
                    $exampleLines = explode("\n", $problem['example']);
                    if (count($exampleLines) > 0) {
                        $section .= "          📝 PŘÍKLAD: {$exampleLines[0]}\n";
                    }
                }
            }
        }
        
        return $section;
    }

    /**
     * Vygeneruje původní sekce problémů pro zpětnou kompatibilitu
     * Zachovává formát výstupu pro existující integrace
     *
     * @param array $qualityAnalysis Výsledky analýzy kvality
     * @return string Naformátované původní sekce problémů
     */
    private function generateLegacyProblemsSections(array $qualityAnalysis): string
    {
        $section = "";

        if (!empty($qualityAnalysis['soubory_bez_phpdoc'])) {
            $section .= "\n  📋 Soubory bez PHP Doc:\n";
            foreach (array_slice($qualityAnalysis['soubory_bez_phpdoc'], 0, 10) as $file) {
                $section .= "     ❌ " . basename($file) . "\n";
            }
        }

        if (!empty($qualityAnalysis['soubory_bez_loggeru'])) {
            $section .= "\n  📋 Soubory bez Loggeru:\n";
            foreach (array_slice($qualityAnalysis['soubory_bez_loggeru'], 0, 10) as $file) {
                $section .= "     ❌ " . basename($file) . "\n";
            }
        }

        if (!empty($qualityAnalysis['soubory_bez_namespaces'])) {
            $section .= "\n  📋 Soubory bez Namespaces:\n";
            foreach (array_slice($qualityAnalysis['soubory_bez_namespaces'], 0, 10) as $file) {
                $section .= "     ❌ " . basename($file) . "\n";
            }
        }

        return $section;
    }

    /**
     * Vrátí Unicode ikonu podle závažnosti problému
     * Používá se pro vizuální rozlišení typů problémů v exportu
     *
     * @param string $severity Závažnost problému ('critical', 'error', 'warning', 'info')
     * @return string Unicode ikona pro danou závažnost
     */
    private function getSeverityIcon(string $severity): string
    {
        return match($severity) {
            'critical' => '🛑',
            'error' => '❌',
            'warning' => '⚠️',
            'info' => 'ℹ️',
            default => '❓'
        };
    }

    /**
     * Vygeneruje strukturovaný AI kontext pro asistenci při vývoji
     * Obsahuje standardy kódování, stav projektu a problémy kvality
     *
     * @param string $projectName Název projektu
     * @param array $structure Projektová struktura jako pole zobrazených řádků
     * @param array $importantFiles Stav důležitých souborů [soubor => existuje]
     * @param array $aiRules Pravidla chování AI a standardy kódování
     * @param array $qualityAnalysis Výsledky analýzy kvality (volitelné)
     * @return string Informace AI kontextu pro použití v AI nástrojích
     */
    public function generateAIContext(
        string $projectName, 
        array $structure, 
        array $importantFiles, 
        array $aiRules,
        array $qualityAnalysis = []
    ): string {
        $this->logger->info('Generating AI context', ['project' => $projectName]);
        
        $context = "=== AI WORKING CONTEXT ===\n";
        $context .= "Project: $projectName\n";
        $context .= "Scan Date: " . date('Y-m-d H:i:s') . "\n\n";

        $context .= "🎯 CODING STANDARDS:\n";
        foreach ($aiRules['coding_standards'] as $rule => $value) {
            $status = $value ? '✅ REQUIRED' : '⚠️ OPTIONAL';
            $context .= "  $status - $rule\n";
        }

        if (!empty($qualityAnalysis)) {
            $context .= $this->generateAIProblemsContext($qualityAnalysis);
        }

        $context .= "\n📁 PROJECT STRUCTURE NOTES:\n";
        $krsRules = $aiRules['project_specific_rules']['krs3_structure'] ?? [];
        foreach ($krsRules as $component => $path) {
            $context .= "  • " . ucfirst(str_replace('_', ' ', $component)) . ": $path\n";
        }

        $context .= "\n🔍 IMPORTANT FILES STATUS:\n";
        foreach ($importantFiles as $file => $exists) {
            $status = $exists ? '✅ FOUND' : '❌ MISSING';
            $context .= "  $status - $file\n";
        }

        $context .= "\n=== END AI CONTEXT ===\n";
        return $context;
    }

    /**
     * Vygeneruje sekci problémů kvality kódu pro AI kontext
     * Formátuje problémy pro efektivní zpracování AI nástroji
     *
     * @param array $qualityAnalysis Výsledky analýzy kvality
     * @return string Naformátovaná sekce problémů optimalizovaná pro AI
     */
    private function generateAIProblemsContext(array $qualityAnalysis): string
    {
        $context = "\n🔍 CODE QUALITY ISSUES:\n";
        
        $problemsBySeverity = $qualityAnalysis['problemy_podle_zavaznosti'] ?? [];
        
        foreach ($problemsBySeverity as $severity => $problems) {
            if (!empty($problems)) {
                $context .= "  " . strtoupper($severity) . " (" . count($problems) . "):\n";
                foreach (array_slice($problems, 0, 5) as $problem) {
                    $context .= "    • {$problem['filename']}: {$problem['description']}\n";
                }
                if (count($problems) > 5) {
                    $context .= "    • ... a " . (count($problems) - 5) . " dalších\n";
                }
            }
        }
        
        return $context;
    }
}
?>