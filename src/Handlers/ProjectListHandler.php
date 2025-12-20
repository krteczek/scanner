<?php
/**
 * Handler pro zobrazení seznamu dostupných projektů
 * 
 * Odpovídá akci: ?action=list
 * Zobrazí všechny složky o úroveň výš jako dostupné projekty.
 */

declare(strict_types=1);

namespace Scanner\Handlers;

use Scanner\Utilities\FileHelper;
use Scanner\Utilities\Config;

class ProjectListHandler implements HandlerInterface
{
    /**
     * Zpracuje požadavek na seznam projektů
     */
    public function handle(array $params = []): string
    {
     $projectsDir = Config::getProjectsDir();
     $scannerRoot = Config::getScannerRoot();
       // 1. Příprava dat
        $baseDir = realpath(__DIR__ . '/../../') ?: '';
        $projectsDir = dirname($baseDir);
        
        $projectNames = FileHelper::getDirectories($projectsDir);
        $projects = [];
        
        foreach ($projectNames as $name) {
               $projects[] = [
                    'name' => $name,
                    'path' => $projectsDir . '/' . $name,
                    'scan_url' => '?action=scan&project=' . urlencode($name)
                ];
        }
        
        // 2. Renderování HTML
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Scanner - Výběr projektu</title>
            <link rel="stylesheet" href="/scanner/public/style.css">
            <style>
                .project-list {
                    margin: 20px 0;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    overflow: hidden;
                }
                .project-item {
                    padding: 15px;
                    border-bottom: 1px solid #eee;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .project-item:hover {
                    background-color: #f9f9f9;
                }
                .project-item:last-child {
                    border-bottom: none;
                }
                .project-name {
                    font-weight: bold;
                    font-size: 1.1em;
                }
                .project-path {
                    color: #666;
                    font-size: 0.9em;
                    font-family: monospace;
                }
                .btn-scan {
                    background: #4CAF50;
                    color: white;
                    padding: 8px 15px;
                    text-decoration: none;
                    border-radius: 3px;
                    font-size: 0.9em;
                }
                .btn-scan:hover {
                    background: #45a049;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>📁 Dostupné projekty</h1>
                <p class="subtitle">Vyberte projekt ke skenování. Scanner prochází složky o úroveň výš.</p>
                
                <?php if (empty($projects)): ?>
                    <div class="alert alert-info">
                        <p>Nebyly nalezeny žádné projekty. Zkontrolujte, zda existují složky vedle této aplikace.</p>
                        <p>Aktuální cesta: <code><?= htmlspecialchars($projectsDir) ?></code></p>
                    </div>
                <?php else: ?>
                    <div class="project-list">
                        <?php foreach ($projects as $project): ?>
                            <div class="project-item">
                                <div>
                                    <div class="project-name">📂 <?= htmlspecialchars($project['name']) ?></div>
                                    <div class="project-path"><?= htmlspecialchars($project['path']) ?></div>
                                </div>
                                <div>
                                    <a href="<?= $project['scan_url'] ?>" class="btn-scan">
                                        Skenovat projekt →
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="actions">
                        <p>API dostupné na: 
                            <a href="?action=api_rules">?action=api_rules</a> (JSON výstup)
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}