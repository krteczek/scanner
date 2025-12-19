<?php
/**
 * Handler pro skenování konkrétního projektu
 * 
 * Odpovídá akci: ?action=scan&project=nazev_projektu
 * Zpracuje analýzu projektu podle pravidel a zobrazí report.
 */

declare(strict_types=1);

namespace Scanner\Handlers;

use Scanner\Core\ScannerEngine;
use Scanner\Core\RulesManager;

class ProjectScanHandler implements HandlerInterface
{
    /**
     * Zpracuje požadavek na skenování projektu
     */
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
        
        // 2. Příprava cest
        $baseDir = realpath(__DIR__ . '/../../../') ?: '';
        $projectsDir = dirname($baseDir);
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
            $rulesManager = new RulesManager(require $baseDir . '/config/rules.php');
            $scanner = new ScannerEngine($rulesManager);
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
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Scanner - Report: <?= htmlspecialchars($projectName) ?></title>
            <link rel="stylesheet" href="/scanner/public/style.css">
            <style>
                .report-header {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    border-left: 4px solid #007bff;
                }
                .stats {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                    gap: 15px;
                    margin: 20px 0;
                }
                .stat-box {
                    background: white;
                    padding: 15px;
                    border-radius: 5px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    text-align: center;
                }
                .stat-value {
                    font-size: 2em;
                    font-weight: bold;
                    color: #007bff;
                }
                .stat-label {
                    color: #666;
                    font-size: 0.9em;
                }
                .issues-list {
                    margin-top: 30px;
                }
                .issue-item {
                    padding: 15px;
                    border-left: 4px solid #ffc107;
                    background: #fff8e1;
                    margin-bottom: 10px;
                    border-radius: 3px;
                }
                .issue-critical {
                    border-left-color: #dc3545;
                    background: #f8d7da;
                }
                .file-link {
                    color: #007bff;
                    text-decoration: none;
                    font-family: monospace;
                }
                .file-link:hover {
                    text-decoration: underline;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="report-header">
                    <h1>📊 Report projektu: <?= htmlspecialchars($projectName) ?></h1>
                    <p class="subtitle">
                        Cesta: <code><?= htmlspecialchars($projectPath) ?></code> 
                        | <a href="?action=list">← Zpět na výběr projektů</a>
                    </p>
                </div>
                
                <?php if (empty($scanResult['issues'])): ?>
                    <div class="alert alert-success">
                        <h3>✅ Žádné problémy nenalezeny!</h3>
                        <p>Všechna analyzovaná pravidla prošla bez problémů.</p>
                    </div>
                <?php else: ?>
                    <!-- Statistiky -->
                    <div class="stats">
                        <div class="stat-box">
                            <div class="stat-value"><?= $scanResult['stats']['total_files'] ?? 0 ?></div>
                            <div class="stat-label">Celkem souborů</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?= $scanResult['stats']['analyzed_files'] ?? 0 ?></div>
                            <div class="stat-label">Analyzováno</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-value"><?= count($scanResult['issues']) ?></div>
                            <div class="stat-label">Nalezených problémů</div>
                        </div>
                    </div>
                    
                    <!-- Seznam problémů -->
                    <div class="issues-list">
                        <h2>🔍 Nalezené problémy</h2>
                        
                        <?php foreach ($scanResult['issues'] as $issue): ?>
                            <div class="issue-item <?= ($issue['severity'] === 'critical') ? 'issue-critical' : '' ?>">
                                <div style="display: flex; justify-content: space-between;">
                                    <div>
                                        <strong><?= htmlspecialchars($issue['rule_name']) ?></strong>
                                        <span style="margin-left: 10px; background: #<?= $issue['severity'] === 'critical' ? 'dc3545' : 'ffc107' ?>; 
                                                    color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8em;">
                                            <?= $issue['severity'] ?>
                                        </span>
                                    </div>
                                    <div>
                                        <a href="?action=view&project=<?= urlencode($projectName) ?>&file=<?= urlencode($issue['file']) ?>" 
                                           class="file-link" target="_blank">
                                            📄 <?= htmlspecialchars(basename($issue['file'])) ?>
                                        </a>
                                    </div>
                                </div>
                                <p style="margin: 10px 0 0 0;"><?= htmlspecialchars($issue['message']) ?></p>
                                <?php if (!empty($issue['line'])): ?>
                                    <p style="margin: 5px 0; color: #666; font-family: monospace;">
                                        Řádek: <?= $issue['line'] ?>
                                        <?php if (!empty($issue['snippet'])): ?>
                                            | Ukázka: <code><?= htmlspecialchars($issue['snippet']) ?></code>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Akce -->
                <div class="actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <a href="?action=scan&project=<?= urlencode($projectName) ?>" class="btn btn-primary">
                        🔄 Znovu skenovat
                    </a>
                    <a href="?action=list" class="btn btn-secondary">
                        ← Zpět na seznam projektů
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}