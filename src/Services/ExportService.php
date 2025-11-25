<?php
// scanner/src/Services/ExportService.php

/**
 * Service pro generování exportů s detailním reportingem problémů
 *
 * @package Scanner\Services
 * @author KRS3
 * @version 2.1
 */

declare(strict_types=1);

namespace Scanner\Services;

class ExportService
{
    /**
     * Vygeneruje textový export s detailními problémy
     *
     * @param string $projectName Název projektu
     * @param array $structure Projektová struktura
     * @param array $importantFiles Výsledky kontroly důležitých souborů
     * @param string $projectPath Cesta k projektu (volitelné)
     * @param array $aiRules Pravidla AI (volitelné)
     * @return string Naformátovaný textový export s problémy
     */
    public function generateTextExport(string $projectName, array $structure, array $importantFiles, string $projectPath = null, array $aiRules = null): string
    {
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

        // 🔍 DETAILNÍ ANALÝZA PROBLÉMŮ
        if ($projectPath && $aiRules && is_dir($projectPath)) {
            $codeAnalyzer = new CodeAnalyzer([]);
            $qualityAnalysis = $codeAnalyzer->analyzeCodeQuality($projectPath, $aiRules);

            $export .= $this->generateDetailedProblemsSection($qualityAnalysis);
        } else {
            $export .= "\n🔍 CODE QUALITY ANALYSIS: ❌ Nedostupné (chybějící parametry)\n";
        }

        $export .= "\n=== END EXPORT ===\n";
        return $export;
    }

    /**
     * Vygeneruje sekci s detailními problémy
     *
     * @param array $qualityAnalysis Výsledky analýzy kvality
     * @return string Naformátovaná sekce problémů
     */
    private function generateDetailedProblemsSection(array $qualityAnalysis): string
    {
        $section = "\n🔍 DETAILED CODE QUALITY ANALYSIS:\n";
        $section .= "  • Celkem souborů: {$qualityAnalysis['celkem_souboru']}\n";
        $section .= "  • Celkem řádků: {$qualityAnalysis['celkem_radku']}\n";
        $section .= "  • Soubory bez PHP Doc: " . count($qualityAnalysis['soubory_bez_phpdoc']) . "\n";
        $section .= "  • Soubory bez loggeru: " . count($qualityAnalysis['soubory_bez_loggeru']) . "\n";
        $section .= "  • Soubory bez namespaces: " . count($qualityAnalysis['soubory_bez_namespaces']) . "\n";

        // 🔍 NOVÉ: STATISTIKA PROBLÉMŮ PODLE ZÁVAŽNOSTI
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
     * Vygeneruje detailní výpis problémů pro každý soubor
     *
     * @param array $filesWithProblems Soubory s problémy
     * @return string Naformátovaný výpis problémů
     */
    private function generateFileProblemsDetails(array $filesWithProblems): string
    {
        $section = "\n  📋 DETAILNÍ PROBLÉMY V SOUBORECH:\n";
        
        foreach ($filesWithProblems as $filePath => $problems) {
            $fileName = basename($filePath);
            $section .= "\n     📄 {$fileName}:\n";
            
            foreach ($problems as $problem) {
                $severityIcon = $this->getSeverityIcon($problem['severity']);
                $section .= "       {$severityIcon} {$problem['description']}\n";
                $section .= "          💡 NÁVRH: {$problem['suggestion']}\n";
                
                // Přidat příklad pokud existuje
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
     * Vygeneruje původní sekce problémů pro kompatibilitu
     *
     * @param array $qualityAnalysis Výsledky analýzy kvality
     * @return string Naformátované původní sekce
     */
    private function generateLegacyProblemsSections(array $qualityAnalysis): string
    {
        $section = "";

        // 📋 SOUBORY BEZ PHP DOC (původní formát)
        if (!empty($qualityAnalysis['soubory_bez_phpdoc'])) {
            $section .= "\n  📋 Soubory bez PHP Doc:\n";
            foreach (array_slice($qualityAnalysis['soubory_bez_phpdoc'], 0, 10) as $file) {
                $section .= "     ❌ " . basename($file) . "\n";
            }
        }

        // 📋 SOUBORY BEZ LOGGERU (původní formát)
        if (!empty($qualityAnalysis['soubory_bez_loggeru'])) {
            $section .= "\n  📋 Soubory bez Loggeru:\n";
            foreach (array_slice($qualityAnalysis['soubory_bez_loggeru'], 0, 10) as $file) {
                $section .= "     ❌ " . basename($file) . "\n";
            }
        }

        // 📋 SOUBORY BEZ NAMESPACES (původní formát)
        if (!empty($qualityAnalysis['soubory_bez_namespaces'])) {
            $section .= "\n  📋 Soubory bez Namespaces:\n";
            foreach (array_slice($qualityAnalysis['soubory_bez_namespaces'], 0, 10) as $file) {
                $section .= "     ❌ " . basename($file) . "\n";
            }
        }

        return $section;
    }

    /**
     * Vrátí ikonu podle závažnosti problému
     *
     * @param string $severity Závažnost problému
     * @return string Ikona
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
     * Vygeneruje AI kontext s detailními informacemi o problémech
     *
     * @param string $projectName Název projektu
     * @param array $structure Projektová struktura
     * @param array $importantFiles Stav důležitých souborů
     * @param array $aiRules Pravidla chování AI
     * @param array $qualityAnalysis Výsledky analýzy kvality (volitelné)
     * @return string Informace AI kontextu
     */
    public function generateAIContext(
        string $projectName, 
        array $structure, 
        array $importantFiles, 
        array $aiRules,
        array $qualityAnalysis = []
    ): string {
        $context = "=== AI WORKING CONTEXT ===\n";
        $context .= "Project: $projectName\n";
        $context .= "Scan Date: " . date('Y-m-d H:i:s') . "\n\n";

        $context .= "🎯 CODING STANDARDS:\n";
        foreach ($aiRules['coding_standards'] as $rule => $value) {
            $status = $value ? '✅ REQUIRED' : '⚠️ OPTIONAL';
            $context .= "  $status - $rule\n";
        }

        // 🔍 NOVÉ: PŘIDÁNÍ INFORMACÍ O PROBLÉMECH DO AI CONTEXTU
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
     * Vygeneruje sekci problémů pro AI kontext
     *
     * @param array $qualityAnalysis Výsledky analýzy kvality
     * @return string Naformátovaná sekce problémů pro AI
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