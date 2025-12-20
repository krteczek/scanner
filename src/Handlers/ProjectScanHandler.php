<?php
// src/Handlers/ProjectScanHandler.php - OPRAVENÁ VERZE
declare(strict_types=1);

namespace Scanner\Handlers;

use Scanner\Core\ScannerEngine;
use Scanner\Utilities\Config;


class ProjectScanHandler implements HandlerInterface
{
     public function handle(array $params = []): string
    {
        // 1. Validace vstupu
        $projectName = $params['project'] ?? null;
        
        if (!$projectName) {
            $errorHandler = new ErrorHandler();
            return $errorHandler->handle([
                'error' => 'Chybějící parametr projektu',
                'message' => 'Pro skenování musíte zadat název projektu: ?action=scan&project=nazev'
            ]);
        }
        
        // 2. Příprava cest - OPRAVENO S Config
        $scannerRoot = Config::getScannerRoot();          // ← OPRAVA
        $projectsDir = Config::getProjectsDir();          // ← OPRAVA
        $projectPath = $projectsDir . '/' . $projectName;
        
        // 3. Kontrola existence projektu
        if (!is_dir($projectPath)) {
            $errorHandler = new ErrorHandler();
            return $errorHandler->handle([
                'error' => 'Projekt nenalezen',
                'message' => "Projekt '$projectName' neexistuje v cestě: " . htmlspecialchars($projectPath)
            ]);
        }
        
        // 4. Spuštění skenování
        try {
            $config = Config::load();                     // ← OPRAVA
            $config['rules'] = require $scannerRoot . '/config/rules.php';
            
            $scanner = new ScannerEngine($config);
            $scanResult = $scanner->scanProject($projectPath);
            
            // 5. Renderování výsledků
            return $this->renderReport($projectName, $projectPath, $scanResult);
            
        } catch (\Exception $e) {
            $errorHandler = new ErrorHandler();
            return $errorHandler->handle([
                'error' => 'Chyba při skenování',
                'message' => $e->getMessage(),
                'details' => 'Kontrolujte konfiguraci a práva k souborům.'
            ]);
        }
    }    
    /**
     * Vykreslí report skenování
     */
    private function renderReport(string $projectName, string $projectPath, array $scanResult): string
    {
        // Prozatím jednoduchý výpis
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Scanner - Report: <?= htmlspecialchars($projectName) ?></title>
            <link rel="stylesheet" href="/scanner/public/style.css">
        </head>
        <body>
            <div class="container">
                <h1>📊 Report: <?= htmlspecialchars($projectName) ?></h1>
                <p>Cesta: <code><?= htmlspecialchars($projectPath) ?></code></p>
                <p>Nalezeno položek: <?= count($scanResult) ?></p>
                
                <h3>Struktura projektu:</h3>
                <pre style="background: #f5f5f5; padding: 15px; border-radius: 5px;">
<?php foreach ($scanResult as $line): ?>
<?= htmlspecialchars($line) . "\n" ?>
<?php endforeach; ?>
                </pre>
                
                <p><a href="?action=list">← Zpět na seznam projektů</a></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}