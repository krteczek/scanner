<?php
/**
 * Handler pro zobrazení obsahu konkrétního souboru
 * 
 * Odpovídá akci: ?action=view&project=nazev&file=cesta/k/souboru
 * Zobrazí obsah souboru s možností kopírování.
 */

declare(strict_types=1);

namespace Scanner\Handlers;
use Scanner\Utilities\Config;

class FileViewHandler implements HandlerInterface
{
    /**
     * Zpracuje požadavek na zobrazení souboru
     */
    public function handle(array $params = []): string
    {
        // 1. Validace vstupu
        $projectName = $params['project'] ?? null;
        $filePath = $params['file'] ?? null;
        
        if (!$projectName || !$filePath) {
            $errorHandler = new ErrorHandler();
            return $errorHandler->handle([
                'error' => 'Chybějící parametry',
                'message' => 'Pro zobrazení souboru jsou potřeba: ?action=view&project=nazev&file=cesta'
            ]);
        }
        
        // 2. Sestavení absolutní cesty
        $baseDir = Config::getScannerRoot();
        $projectsDir = Config::getProjectsDir();
        $absolutePath = $projectsDir . '/' . $projectName . '/' . $filePath;
        
        // 3. Kontrola existence souboru
        if (!file_exists($absolutePath)) {
            $errorHandler = new ErrorHandler();
            return $errorHandler->handle([
                'error' => 'Soubor nenalezen',
                'message' => "Soubor '$filePath' v projektu '$projectName' neexistuje."
            ]);
        }
        
        // 4. Bezpečnostní kontrola (jen soubory, ne složky)
        if (is_dir($absolutePath)) {
            $errorHandler = new ErrorHandler();
            return $errorHandler->handle([
                'error' => 'Nelze zobrazit složku',
                'message' => 'Lze zobrazit pouze obsah souborů.'
            ]);
        }
        
        // 5. Kontrola velikosti souboru (limit např. 2MB)
        $fileSize = filesize($absolutePath);
        if ($fileSize > 2 * 1024 * 1024) {
            $errorHandler = new ErrorHandler();
            return $errorHandler->handle([
                'error' => 'Soubor je příliš velký',
                'message' => "Soubor má $fileSize bajtů. Maximální velikost pro zobrazení je 2 MB."
            ]);
        }
        
        // 6. Načtení a zobrazení obsahu
        $content = file_get_contents($absolutePath);
        $fileInfo = pathinfo($absolutePath);
        $extension = strtolower($fileInfo['extension'] ?? '');
        
        return $this->renderFileContent($projectName, $filePath, $content, $extension, $fileSize);
    }
    
    /**
     * Vykreslí obsah souboru
     */
    private function renderFileContent(string $projectName, string $filePath, string $content, 
                                      string $extension, int $fileSize): string
    {
        $formattedSize = $this->formatFileSize($fileSize);
        $backUrl = '?action=scan&project=' . urlencode($projectName);
        
        // Rozlišení syntaxe podle typu souboru
        $languageClass = '';
        if (in_array($extension, ['php', 'js', 'html', 'css', 'json', 'xml'])) {
            $languageClass = 'language-' . $extension;
        }
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Scanner - Soubor: <?= htmlspecialchars(basename($filePath)) ?></title>
            <link rel="stylesheet" href="/scanner/public/style.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/styles/github.min.css">
            <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.8.0/highlight.min.js"></script>
            <style>
                .file-header {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 5px;
                    margin-bottom: 20px;
                    border-left: 4px solid #28a745;
                }
                .file-meta {
                    display: flex;
                    gap: 20px;
                    margin-top: 10px;
                    color: #666;
                }
                .file-content {
                    background: #fcfcfc;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                    padding: 0;
                    overflow: hidden;
                }
                pre {
                    margin: 0;
                    padding: 20px;
                    overflow-x: auto;
                    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
                    font-size: 14px;
                    line-height: 1.5;
                }
                .copy-btn {
                    position: sticky;
                    top: 10px;
                    float: right;
                    background: #007bff;
                    color: white;
                    border: none;
                    padding: 8px 15px;
                    border-radius: 3px;
                    cursor: pointer;
                    margin: 10px;
                }
                .copy-btn:hover {
                    background: #0056b3;
                }
                .copy-btn.copied {
                    background: #28a745;
                }
                .line-numbers {
                    padding-right: 20px;
                    border-right: 1px solid #ddd;
                    background: #f5f5f5;
                    text-align: right;
                    color: #666;
                    user-select: none;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="file-header">
                    <h1>📄 <?= htmlspecialchars(basename($filePath)) ?></h1>
                    <p class="subtitle">
                        Projekt: <strong><?= htmlspecialchars($projectName) ?></strong> 
                        | <a href="<?= $backUrl ?>">← Zpět na report</a>
                    </p>
                    
                    <div class="file-meta">
                        <span>📁 Cesta: <code><?= htmlspecialchars($filePath) ?></code></span>
                        <span>📦 Velikost: <?= $formattedSize ?></span>
                        <span>🔤 Znaků: <?= strlen($content) ?></span>
                        <span>📝 Řádků: <?= substr_count($content, "\n") + 1 ?></span>
                    </div>
                </div>
                
                <div class="file-content">
                    <button class="copy-btn" onclick="copyToClipboard()" id="copyBtn">
                        📋 Kopírovat celý soubor
                    </button>
                    
                    <div style="display: flex;">
                        <!-- Čísla řádků -->
                        <div class="line-numbers">
                            <?php 
                            $lines = substr_count($content, "\n") + 1;
                            for ($i = 1; $i <= $lines; $i++): 
                            ?>
                                <div style="padding: 2px 10px;"><?= $i ?></div>
                            <?php endfor; ?>
                        </div>
                        
                        <!-- Obsah souboru -->
                        <div style="flex: 1; min-width: 0;">
                            <pre><code class="<?= $languageClass ?>" id="fileContent"><?= htmlspecialchars($content) ?></code></pre>
                        </div>
                    </div>
                </div>
                
                <div class="actions" style="margin-top: 20px;">
                    <button onclick="copyToClipboard()" class="btn btn-primary">
                        📋 Kopírovat celý soubor
                    </button>
                    <button onclick="window.print()" class="btn btn-secondary">
                        🖨️ Tisk
                    </button>
                    <a href="<?= $backUrl ?>" class="btn">
                        ← Zpět na report
                    </a>
                </div>
            </div>
            
            <script>
                // Aktivace syntax highlighting
                if (typeof hljs !== 'undefined') {
                    document.querySelectorAll('pre code').forEach((block) => {
                        hljs.highlightElement(block);
                    });
                }
                
                // Funkce pro kopírování
                function copyToClipboard() {
                    const content = document.getElementById('fileContent').textContent;
                    navigator.clipboard.writeText(content).then(() => {
                        const btn = document.getElementById('copyBtn');
                        btn.innerHTML = '✅ Zkopírováno!';
                        btn.classList.add('copied');
                        setTimeout(() => {
                            btn.innerHTML = '📋 Kopírovat celý soubor';
                            btn.classList.remove('copied');
                        }, 2000);
                    });
                }
                
                // Klávesová zkratka Ctrl+C
                document.addEventListener('keydown', (e) => {
                    if (e.ctrlKey && e.key === 'c') {
                        copyToClipboard();
                    }
                });
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Formátuje velikost souboru do čitelné podoby
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}