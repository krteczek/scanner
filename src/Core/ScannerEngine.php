<?php
// /scanner/src/Core/ScannerEngine.php

declare(strict_types=1);

namespace Scanner\Core;

use Scanner\Services\ProjectScanner;
use Scanner\Services\CodeAnalyzer;
use Scanner\Services\ExportService;

/**
 * Hlavní engine scanneru
 *
 * @package Scanner\Core
 * @author KRS3
 * @version 2.0
 */


class ScannerEngine
{
    private array $config;
    private ProjectScanner $projectScanner;
    private CodeAnalyzer $codeAnalyzer;
    private ExportService $exportService;

    /**
     * Constructor
     *
     * @param array $config Konfigurace aplikace
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->projectScanner = new ProjectScanner($this->config);
        $this->codeAnalyzer = new CodeAnalyzer($this->config);
        $this->exportService = new ExportService();
    }

    /**
     * Spustí hlavní aplikaci scanneru
     *
     * @return void
     */
    public function run(): void
    {
        // Zpracování požadavku
        if (isset($_GET['scan'])) {
            $this->handleScanRequest($this->projectScanner, $_GET['scan']);
        } else {
            $this->showMainInterface($this->projectScanner);
        }
    }
    /**
     * Zobrazí hlavní rozhraní s výpisem projektů
     *
     * @param ProjectScanner $projectScanner Instance projektového scanneru
     * @return void
     */
    private function showMainInterface(ProjectScanner $projectScanner): void
    {
        $projects = $projectScanner->getProjects();

        echo "<!DOCTYPE html><html><head><title>Project Scanner</title>";
        echo "<link rel='stylesheet' href='public/style.css'>";
        echo $this->getJavaScript();
        echo "</head><body>";

        echo "<div class='container'>";
        echo "<h1>🔍 Project Scanner</h1>";

        // Odkaz na správce pravidel
        echo "<div style='text-align: center; margin: 20px 0;'>";
        echo "<a href='src/Core/RulesManager.php' style='background: #9b59b6; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;'>";
        echo "⚙️ Spravovat AI Pravidla";
        echo "</a>";
        echo "</div>";

        // Výpis projektů
        if (empty($projects)) {
            echo "<p>❌ Žádné projekty nenalezeny v: " . $this->config['paths']['projects_root'] . "</p>";
        } else {
            echo "<div class='project-list'>";
            foreach ($projects as $project) {
                echo "<div class='project' onclick=\"showStructure('$project')\">📂 $project</div>";
            }
            echo "</div>";
        }

        echo "<div id='results'></div>";
        echo "</div></body></html>";
    }

    /**
     * Zpracuje požadavek na skenování projektu
     *
     * @param ProjectScanner $projectScanner Instance projektového scanneru
     * @param string $projectName Název projektu
     * @return void
     */
    private function handleScanRequest(ProjectScanner $projectScanner, string $projectName): void
    {
        $projectPath = $this->config['paths']['projects_root'] . '/' . $projectName;

        if (!is_dir($projectPath)) {
            echo "❌ Projekt '$projectName' neexistuje!";
            return;
        }

        // Získání struktury a analýzy
        $structure = $projectScanner->scanProject($projectPath);
        $importantFilesCheck = $projectScanner->checkImportantFiles($projectPath);

        // Načtení AI pravidel
        $aiRules = @include __DIR__ . '/../../config/rules.php';
        if (!$aiRules) {
            $aiRules = [];
        }

        // Generování exportu
        $exportService = new ExportService();
        $textExport = $exportService->generateTextExport($projectName, $structure, $importantFilesCheck, $projectPath, $aiRules);

        // Výstup výsledků
        echo "<div class='scan-results'>";
        echo "<h3>📁 Struktura projektu: <strong>$projectName</strong></h3>";

        // Tlačítko pro export
        echo "<div class='export-section'>";
        echo "<button onclick='showExport()' style='background:#27ae60;margin:10px 0'>📋 Zobrazit export</button>";
        echo "</div>";

        // Textarea pro export
        echo "<div id='exportArea' style='display:none; margin:15px 0'>";
        echo "<textarea id='exportText' style='width:100%; height:300px; font-family:monospace; background:#2c3e50; color:white; padding:10px; border-radius:5px;' readonly>";
        echo htmlspecialchars($textExport);
        echo "</textarea><br>";
        echo "<button onclick='copyExport()' style='background:#e67e22; margin-top:5px'>📋 Kopírovat do schránky</button>";
        echo "</div>";

        // Zobrazení struktury
        echo "<div class='structure'>";
        foreach ($structure as $line) {
            echo htmlspecialchars($line) . "\n";
        }
        echo "</div>";

        // Kontrola důležitých souborů
        echo "<div class='important-files'>";
        echo "<h4>🎯 Kontrola důležitých souborů:</h4>";
        foreach ($importantFilesCheck as $file => $exists) {
            $status = $exists ? '✅' : '❌';
            echo "<div>$status $file</div>";
        }
        echo "</div>";

        echo "<br><button onclick='history.back()'>← Zpět</button>";
        echo "</div>";
    }

    /**
     * Vrátí JavaScript kód pro aplikaci
     *
     * @return string JavaScript kód
     */
    private function getJavaScript(): string
    {
        return "
        <script>
        function showStructure(project) {
            fetch('?scan=' + encodeURIComponent(project))
            .then(response => response.text())
            .then(data => {
                document.getElementById('results').innerHTML = data;
            });
        }

        function showExport() {
            const exportArea = document.getElementById('exportArea');
            if (!exportArea) return;

            if (exportArea.style.display === 'none' || !exportArea.style.display) {
                exportArea.style.display = 'block';
            } else {
                exportArea.style.display = 'none';
            }
        }

        function copyExport() {
            const textarea = document.getElementById('exportText');
            if (!textarea) {
                alert('❌ Textarea nenalezena');
                return;
            }

            textarea.select();
            textarea.setSelectionRange(0, 99999);
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    alert('✅ Export zkopírován do schránky!');
                } else {
                    alert('❌ Kopírování selhalo');
                }
            } catch (err) {
                alert('❌ Chyba při kopírování: ' + err);
            }
        }
        </script>
        ";
    }
}
?>