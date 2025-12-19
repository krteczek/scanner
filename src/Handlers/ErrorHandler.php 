<?php
/**
 * Handler pro zobrazení chybových stránek
 * 
 * Používá se interně pro všechny chyby v aplikaci.
 * Lze také volat přímo: ?action=error&message=text
 */

declare(strict_types=1);

namespace Scanner\Handlers;

class ErrorHandler implements HandlerInterface
{
    /**
     * Zpracuje chybový požadavek
     */
    public function handle(array $params = []): string
    {
        $errorTitle = $params['error'] ?? 'Chyba aplikace';
        $errorMessage = $params['message'] ?? 'Došlo k neočekávané chybě.';
        $errorDetails = $params['details'] ?? null;
        $backUrl = $params['back_url'] ?? '?action=list';
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="cs">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Scanner - Chyba</title>
            <link rel="stylesheet" href="/scanner/public/style.css">
            <style>
                .error-container {
                    max-width: 800px;
                    margin: 50px auto;
                    padding: 40px;
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                    border-top: 5px solid #dc3545;
                }
                .error-icon {
                    font-size: 4em;
                    text-align: center;
                    margin-bottom: 20px;
                    color: #dc3545;
                }
                .error-actions {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #eee;
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }
                .error-details {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin-top: 20px;
                    border-left: 4px solid #6c757d;
                    font-family: monospace;
                    font-size: 0.9em;
                    overflow-x: auto;
                }
                .error-details summary {
                    cursor: pointer;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .error-code {
                    background: #dc3545;
                    color: white;
                    padding: 3px 8px;
                    border-radius: 3px;
                    font-size: 0.8em;
                    margin-left: 10px;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">⚠️</div>
                
                <h1 style="text-align: center; color: #dc3545;"><?= htmlspecialchars($errorTitle) ?></h1>
                
                <div style="text-align: center; font-size: 1.2em; margin: 20px 0; color: #333;">
                    <?= htmlspecialchars($errorMessage) ?>
                </div>
                
                <?php if ($errorDetails): ?>
                    <details class="error-details">
                        <summary>Technické detaily</summary>
                        <pre style="margin: 0; white-space: pre-wrap;"><?= htmlspecialchars($errorDetails) ?></pre>
                    </details>
                <?php endif; ?>
                
                <?php if (!empty($_SERVER['REQUEST_URI'])): ?>
                    <div style="margin-top: 20px; color: #666; font-size: 0.9em;">
                        <strong>URL:</strong> <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>
                    </div>
                <?php endif; ?>
                
                <div class="error-actions">
                    <a href="<?= $backUrl ?>" class="btn btn-primary">
                        ← Zpět na bezpečné místo
                    </a>
                    <a href="?action=list" class="btn btn-secondary">
                        🏠 Domů (seznam projektů)
                    </a>
                    <button onclick="location.reload()" class="btn">
                        🔄 Zkusit znovu
                    </button>
                    <button onclick="window.history.back()" class="btn">
                        ↩️ Zpět
                    </button>
                </div>
                
                <div style="margin-top: 30px; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 0.9em;">
                    <p><strong>Tipy pro řešení problémů:</strong></p>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Zkontrolujte, zda má aplikace práva pro čtení souborů</li>
                        <li>Ověřte správnost zadaných parametrů v URL</li>
                        <li>Zkontrolujte existenci projektu nebo souboru</li>
                        <li>Pro více informací zapněte debug mód v <code>config/app.php</code></li>
                    </ul>
                </div>
            </div>
            
            <script>
                // Automatické skrytí chybových detailů
                document.addEventListener('DOMContentLoaded', function() {
                    const details = document.querySelector('.error-details');
                    if (details) {
                        // Uložit stav do localStorage
                        const savedState = localStorage.getItem('errorDetailsOpen');
                        if (savedState === 'true') {
                            details.open = true;
                        }
                        
                        // Sledovat změny
                        details.addEventListener('toggle', function() {
                            localStorage.setItem('errorDetailsOpen', details.open);
                        });
                    }
                    
                    // Přidat klávesové zkratky
                    document.addEventListener('keydown', function(e) {
                        // ESC pro zavření detailů
                        if (e.key === 'Escape' && details && details.open) {
                            details.open = false;
                        }
                        // F5 pro reload
                        if (e.key === 'F5') {
                            location.reload();
                        }
                    });
                });
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}