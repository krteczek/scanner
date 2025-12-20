<?php
declare(strict_types=1);

namespace Scanner\Handlers;

use Scanner\Utilities\Config;
use Scanner\Core\ScannerEngine;

class ProjectScanHandler implements HandlerInterface
{
    public function handle(array $params = []): string
    {
        // 1. Validace vstupu
        $projectName = $params['project'] ?? null;
        
        if (!$projectName) {
            return $this->error('Chybějící parametr projektu', 
                'Pro skenování musíte zadat název projektu: ?action=scan&project=nazev');
        }
        
        // 2. Příprava cest
        $scannerRoot = Config::getScannerRoot();
        $projectsDir = Config::getProjectsDir();
        $projectPath = $projectsDir . '/' . $projectName;
        
        // 3. Kontrola existence projektu
        clearstatcache(true, $projectPath);
        if (!is_dir($projectPath) || !is_readable($projectPath)) {
            return $this->error('Projekt nenalezen nebo nepřístupný',
                "Nelze přistoupit k projektu '$projectName' v: " . htmlspecialchars($projectPath));
        }
        
        // 4. Spuštění skenování
        try {
            // Načti konfiguraci
            $config = Config::load();
            $config['rules'] = require $scannerRoot . '/config/rules.php';
            
            // Vytvoř ScannerEngine (NOVÁ VERZE)
            $scanner = new ScannerEngine($config);
            
            // MOŽNOST 1: Kompletní analýza projektu
            $scanResult = $scanner->scanProject($projectPath);
            // Vrací: ['structure', 'analysis', 'stats', 'project_path', 'scan_time']
            
            // MOŽNOST 2: Pouze struktura pro zobrazení (kompatibilní)
            // $displayStructure = $scanner->getDisplayStructure($projectPath);
            
            // 5. Renderování výsledků
            return $this->renderReport($projectName, $projectPath, $scanResult);
            
        } catch (\Exception $e) {
            return $this->error('Chyba při skenování', $e->getMessage());
        }
    }
    
    /**
     * Vykreslí report skenování
     */
    private function renderReport(string $projectName, string $projectPath, array $scanResult): string
    {
        // Extrahuj data z výsledku
        $structure = $scanResult['structure'] ?? [];
        $analysis = $scanResult['analysis'] ?? ['issues' => [], 'total_issues' => 0];
        $stats = $scanResult['stats'] ?? [];
        $displayItems = $structure['tree'] ?? [];
        
        $issues = $analysis['issues'] ?? [];
        $totalIssues = $analysis['total_issues'] ?? 0;
        $totalFiles = $stats['total_files'] ?? 0;
        $totalDirs = $stats['total_dirs'] ?? 0;
        
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
                body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
                .container { max-width: 1200px; margin: 0 auto; }
                .report-header { 
                    background: white; 
                    padding: 25px; 
                    border-radius: 10px; 
                    margin-bottom: 20px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    border-left: 5px solid #007bff;
                }
                .stats-grid { 
                    display: grid; 
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
                    gap: 15px; 
                    margin: 25px 0; 
                }
                .stat-box { 
                    background: white; 
                    padding: 20px; 
                    border-radius: 8px; 
                    box-shadow: 0 2px 5px rgba(0,0,0,0.08);
                    text-align: center;
                    transition: transform 0.2s;
                }
                .stat-box:hover { transform: translateY(-3px); }
                .stat-value { 
                    font-size: 2.2em; 
                    font-weight: bold; 
                    color: #007bff;
                    margin-bottom: 5px;
                }
                .stat-label { 
                    color: #666; 
                    font-size: 0.9em;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
                .issues-section, .structure-section {
                    background: white;
                    padding: 25px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                }
                .issue-item {
                    padding: 15px;
                    border-left: 4px solid #ffc107;
                    background: #fff8e1;
                    margin-bottom: 10px;
                    border-radius: 5px;
                    transition: background 0.2s;
                }
                .issue-item:hover { background: #fff1c2; }
                .issue-critical { border-left-color: #dc3545; background: #f8d7da; }
                .issue-info { border-left-color: #17a2b8; background: #d1ecf1; }
                .file-link { 
                    color: #007bff; 
                    text-decoration: none; 
                    font-family: 'Monaco', 'Menlo', monospace;
                    font-size: 0.95em;
                }
                .file-link:hover { text-decoration: underline; }
                .structure-tree {
                    background: #f9f9f9;
                    padding: 20px;
                    border-radius: 8px;
                    font-family: 'Monaco', 'Menlo', monospace;
                    font-size: 14px;
                    line-height: 1.4;
                    white-space: pre;
                    overflow-x: auto;
                    border: 1px solid #e0e0e0;
                    max-height: 500px;
                    overflow-y: auto;
                }
                .btn {
                    display: inline-block;
                    padding: 10px 20px;
                    background: #007bff;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    border: none;
                    cursor: pointer;
                    font-size: 1em;
                    margin-right: 10px;
                    transition: background 0.2s;
                }
                .btn:hover { background: #0056b3; }
                .btn-secondary { background: #6c757d; }
                .btn-secondary:hover { background: #545b62; }
                .btn-success { background: #28a745; }
                .btn-success:hover { background: #1e7e34; }
                .tab-nav { 
                    display: flex; 
                    border-bottom: 2px solid #dee2e6; 
                    margin-bottom: 20px; 
                }
                .tab-btn { 
                    padding: 12px 25px; 
                    background: none; 
                    border: none; 
                    cursor: pointer; 
                    font-size: 1em;
                    color: #495057;
                    border-bottom: 3px solid transparent;
                    margin-right: 5px;
                }
                .tab-btn.active { 
                    color: #007bff; 
                    border-bottom-color: #007bff; 
                    font-weight: bold;
                }
                .tab-content { display: none; }
                .tab-content.active { display: block; }
                .severity-badge {
                    padding: 3px 10px;
                    border-radius: 12px;
                    font-size: 0.8em;
                    font-weight: bold;
                    margin-left: 10px;
                }
                .severity-critical { background: #dc3545; color: white; }
                .severity-warning { background: #ffc107; color: #212529; }
                .severity-info { background: #17a2b8; color: white; }
            </style>
        </head>
        <body>
            <div class="container">
                <!-- Hlavička -->
                <div class="report-header">
                    <h1 style="margin: 0 0 10px 0;">📊 Scanner Report: <?= htmlspecialchars($projectName) ?></h1>
                    <p style="margin: 5px 0; color: #666;">
                        <strong>Cesta:</strong> <code><?= htmlspecialchars($projectPath) ?></code>
                    </p>
                    <p style="margin: 5px 0; color: #666;">
                        <strong>Čas skenu:</strong> <?= $scanResult['scan_time'] ?? date('Y-m-d H:i:s') ?>
                    </p>
                </div>
                
                <!-- Statistiky -->
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value"><?= $totalFiles ?></div>
                        <div class="stat-label">Souborů</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?= $totalDirs ?></div>
                        <div class="stat-label">Složek</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value"><?= $totalIssues ?></div>
                        <div class="stat-label">Problémů</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">
                            <?= $analysis['files_analyzed'] ?? '0' ?>
                        </div>
                        <div class="stat-label">Analyzováno</div>
                    </div>
                </div>
                
                <!-- Taby -->
                <div class="tab-nav">
                    <button class="tab-btn active" onclick="showTab('structure')">📁 Struktura</button>
                    <button class="tab-btn" onclick="showTab('issues')" <?= $totalIssues > 0 ? '' : 'disabled' ?>>
                        🔍 Problémy <?= $totalIssues > 0 ? "($totalIssues)" : '' ?>
                    </button>
                    <button class="tab-btn" onclick="showTab('files')">📋 Soubory</button>
                </div>
                
                <!-- TAB 1: Struktura -->
                <div id="tab-structure" class="tab-content active">
                    <div class="structure-section">
                        <h2 style="margin-top: 0;">📁 Struktura projektu</h2>
                        <div class="structure-tree">
<?php if (!empty($displayItems)): ?>
<?php foreach ($displayItems as $item): ?>
<?= htmlspecialchars(is_array($item) ? ($item['display'] ?? '') : $item) . "\n" ?>
<?php endforeach; ?>
<?php else: ?>
⚠️ Nepodařilo se načíst strukturu projektu.
<?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- TAB 2: Problémy -->
                <div id="tab-issues" class="tab-content">
                    <div class="issues-section">
                        <h2 style="margin-top: 0;">🔍 Nalezené problémy</h2>
                        
                        <?php if (empty($issues)): ?>
                            <div style="text-align: center; padding: 40px; color: #28a745;">
                                <div style="font-size: 3em; margin-bottom: 20px;">✅</div>
                                <h3>Žádné problémy nenalezeny!</h3>
                                <p>Všechny analyzované soubory prošly bez problémů.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($issues as $issue): 
                                $severityClass = 'severity-' . ($issue['severity'] ?? 'warning');
                            ?>
                                <div class="issue-item <?= ($issue['severity'] ?? '') === 'critical' ? 'issue-critical' : '' ?>">
                                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                        <div style="flex: 1;">
                                            <strong><?= htmlspecialchars($issue['rule_name'] ?? 'Neznámé pravidlo') ?></strong>
                                            <span class="severity-badge <?= $severityClass ?>">
                                                <?= htmlspecialchars($issue['severity'] ?? 'warning') ?>
                                            </span>
                                            <p style="margin: 8px 0 5px 0;"><?= htmlspecialchars($issue['message'] ?? '') ?></p>
                                        </div>
                                        <div style="text-align: right; min-width: 200px;">
                                            <a href="?action=view&project=<?= urlencode($projectName) ?>&file=<?= urlencode($issue['file'] ?? '') ?>" 
                                               class="file-link" target="_blank">
                                                📄 <?= htmlspecialchars($issue['file_name'] ?? basename($issue['file'] ?? '')) ?>
                                            </a>
                                            <?php if (!empty($issue['line'])): ?>
                                                <div style="color: #666; font-size: 0.9em; margin-top: 5px;">
                                                    Řádek: <?= $issue['line'] ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if (!empty($issue['snippet'])): ?>
                                        <div style="margin-top: 10px;">
                                            <details>
                                                <summary style="cursor: pointer; color: #666; font-size: 0.9em;">
                                                    Zobrazit kód
                                                </summary>
                                                <pre style="background: #f5f5f5; padding: 10px; margin: 5px 0 0 0; border-radius: 5px; font-size: 0.85em; overflow-x: auto;">
<?= htmlspecialchars($issue['snippet']) ?>
                                                </pre>
                                            </details>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- TAB 3: Soubory -->
                <div id="tab-files" class="tab-content">
                    <div class="issues-section">
                        <h2 style="margin-top: 0;">📋 Seznam souborů</h2>
                        
                        <?php if (!empty($structure['files'])): ?>
                            <table style="width: 100%; border-collapse: collapse;">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Soubor</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Velikost</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Typ</th>
                                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #dee2e6;">Akce</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($structure['files'] as $file): ?>
                                        <tr style="border-bottom: 1px solid #eee;">
                                            <td style="padding: 12px;">
                                                <span style="color: #666;"><?= htmlspecialchars(dirname($file['path'] ?? '')) ?>/</span>
                                                <strong><?= htmlspecialchars($file['name'] ?? '') ?></strong>
                                            </td>
                                            <td style="padding: 12px;">
                                                <?= $this->formatSize($file['size'] ?? 0) ?>
                                            </td>
                                            <td style="padding: 12px;">
                                                <span style="background: #e9ecef; padding: 3px 8px; border-radius: 3px; font-size: 0.85em;">
                                                    .<?= htmlspecialchars($file['extension'] ?? '?') ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px;">
                                                <a href="?action=view&project=<?= urlencode($projectName) ?>&file=<?= urlencode($file['path'] ?? '') ?>" 
                                                   class="btn btn-success" style="padding: 5px 12px; font-size: 0.9em;">
                                                    Zobrazit
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p style="text-align: center; color: #666; padding: 20px;">
                                Žádné soubory k zobrazení.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Akce -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center;">
                    <a href="?action=scan&project=<?= urlencode($projectName) ?>" class="btn">
                        🔄 Znovu skenovat
                    </a>
                    <a href="?action=list" class="btn btn-secondary">
                        ← Zpět na seznam projektů
                    </a>
                    <?php if ($totalIssues > 0): ?>
                        <button onclick="window.print()" class="btn" style="background: #6f42c1;">
                            🖨️ Tisk reportu
                        </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <script>
                // Funkce pro přepínání tabů
                function showTab(tabName) {
                    // Skryj všechny taby
                    document.querySelectorAll('.tab-content').forEach(tab => {
                        tab.classList.remove('active');
                    });
                    // Odstraň aktivní třídu z tlačítek
                    document.querySelectorAll('.tab-btn').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    // Zobraz vybraný tab
                    document.getElementById('tab-' + tabName).classList.add('active');
                    // Aktivuj tlačítko
                    event.target.classList.add('active');
                }
                
                // Automaticky přepni na tab s problémy, pokud nějaké jsou
                window.addEventListener('DOMContentLoaded', function() {
                    const issueCount = <?= $totalIssues ?>;
                    if (issueCount > 0) {
                        // Nech strukturu jako výchozí, ale můžeš změnit:
                        // showTab('issues');
                    }
                });
                
                // Klávesové zkratky
                document.addEventListener('keydown', function(e) {
                    if (e.ctrlKey || e.metaKey) {
                        switch(e.key) {
                            case '1': showTab('structure'); break;
                            case '2': if (<?= $totalIssues ?> > 0) showTab('issues'); break;
                            case '3': showTab('files'); break;
                            case 'r': location.href = '?action=scan&project=<?= urlencode($projectName) ?>'; break;
                            case 'l': location.href = '?action=list'; break;
                        }
                    }
                });
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Formátuje velikost souboru
     */
    private function formatSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
    
    /**
     * Zobrazí chybovou stránku
     */
    private function error(string $title, string $message): string
    {
        $errorHandler = new ErrorHandler();
        return $errorHandler->handle([
            'error' => $title,
            'message' => $message
        ]);
    }
}