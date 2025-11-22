<?php
// scanner/src/Services/ExportService.php
/**
 * Service pro generování exportů
 *
 * @package Scanner\Services
 * @author KRS3
 * @version 2.0
 */

declare(strict_types=1);

namespace Scanner\Services;

class ExportService
{
    /**
     * Vygeneruje textový export struktury projektu
     *
     * @param string $projectName Název projektu
     * @param array $structure Projektová struktura
     * @param array $importantFiles Výsledky kontroly důležitých souborů
     * @param string $projectPath Cesta k projektu (volitelné)
     * @param array $aiRules Pravidla AI (volitelné)
     * @return string Naformátovaný textový export
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

    // 🔍 DETAILNÍ ANALÝZA CHYB
    if ($projectPath && $aiRules && is_dir($projectPath)) {
        $codeAnalyzer = new CodeAnalyzer([]);
        $qualityAnalysis = $codeAnalyzer->analyzeCodeQuality($projectPath, $aiRules);

        $export .= "\n🔍 CODE QUALITY ANALYSIS:\n";
        $export .= "  • Celkem souborů: {$qualityAnalysis['celkem_souboru']}\n";
        $export .= "  • Celkem řádků: {$qualityAnalysis['celkem_radku']}\n";
        $export .= "  • Soubory bez PHP Doc: " . count($qualityAnalysis['soubory_bez_phpdoc']) . "\n";
        $export .= "  • Soubory bez loggeru: " . count($qualityAnalysis['soubory_bez_loggeru']) . "\n";
        $export .= "  • Soubory s chybami: " . count($qualityAnalysis['soubory_s_chybami']) . "\n";

        // 📋 DETAILNÍ VÝPIS CHYB PRO KAŽDÝ SOUBOR
        if (!empty($qualityAnalysis['soubory_s_chybami'])) {
            $export .= "\n  🚨 DETAILNÍ CHYBY V SOUBORECH:\n";
            foreach ($qualityAnalysis['soubory_s_chybami'] as $file => $chyby) {
                $export .= "     📄 " . basename($file) . ":\n";
                foreach ($chyby as $index => $chyba) {
                    $export .= "        ❌ " . $chyba . "\n";
                }
            }
        }

        // 📋 SOUBORY BEZ PHP DOC
        if (!empty($qualityAnalysis['soubory_bez_phpdoc'])) {
            $export .= "\n  📋 Soubory bez PHP Doc:\n";
            foreach (array_slice($qualityAnalysis['soubory_bez_phpdoc'], 0, 10) as $file) {
                $export .= "     ❌ " . basename($file) . "\n";
            }
        }

        // 📋 SOUBORY BEZ LOGGERU
        if (!empty($qualityAnalysis['soubory_bez_loggeru'])) {
            $export .= "\n  📋 Soubory bez Loggeru:\n";
            foreach (array_slice($qualityAnalysis['soubory_bez_loggeru'], 0, 10) as $file) {
                $export .= "     ❌ " . basename($file) . "\n";
            }
        }
    } else {
        $export .= "\n🔍 CODE QUALITY ANALYSIS: ❌ Nedostupné (chybějící parametry)\n";
    }

    $export .= "\n=== END EXPORT ===\n";
    return $export;
}


    /**
     * Vygeneruje AI kontext pro práci s projektem
     *
     * @param string $projectName Název projektu
     * @param array $structure Projektová struktura
     * @param array $importantFiles Stav důležitých souborů
     * @param array $aiRules Pravidla chování AI
     * @return string Informace AI kontextu
     */
    public function generateAIContext(string $projectName, array $structure, array $importantFiles, array $aiRules): string
    {
        $context = "=== AI WORKING CONTEXT ===\n";
        $context .= "Project: $projectName\n";
        $context .= "Scan Date: " . date('Y-m-d H:i:s') . "\n\n";

        $context .= "🎯 CODING STANDARDS:\n";
        foreach ($aiRules['coding_standards'] as $rule => $value) {
            $status = $value ? '✅ REQUIRED' : '⚠️ OPTIONAL';
            $context .= "  $status - $rule\n";
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
}
?>