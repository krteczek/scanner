<?php
// /scanner/index.php
/**
 * Project Scanner - Entry Point
 *
 * @package Scanner
 * @author KRS3
 * @version 2.1 - Přidány klikatelné adresáře a vylepšené preview
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

// ✅ NAČTENÍ AUTOLOADERU
require_once __DIR__ . '/autoloader.php';

// 🔥 VYLEPŠENÉ PREVIEW SYSTEM
if (isset($_GET['preview'])) {
    $filePath = $_GET['preview'];
    if (file_exists($filePath) && is_file($filePath)) {
        $fileContent = htmlspecialchars(file_get_contents($filePath));
        $fileName = basename($filePath);

        echo "<!DOCTYPE html><html><head><title>Preview: $fileName</title>";
        echo "<link rel='stylesheet' href='public/style.css'>";
        echo "<style>
            .preview-container {
                background: #f8f9fa;
                color: #2c3e50;
                padding: 20px;
                border-radius: 8px;
                margin: 10px 0;
            }
            .code-content {
                background: white;
                border: 1px solid #dee2e6;
                border-radius: 5px;
                padding: 15px;
                font-family: 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.4;
                white-space: pre-wrap;
                max-height: 70vh;
                overflow: auto;
            }
            .preview-actions {
                margin: 15px 0;
                display: flex;
                gap: 10px;
            }
        </style>";
        echo "</head><body>";
        echo "<div class='container'>";
        echo "<h3>📄 " . htmlspecialchars($fileName) . "</h3>";

        echo "<div class='preview-actions'>";
        echo "<button onclick='copyCode()' style='background:#27ae60'>📋 Kopírovat kód</button>";
        echo "<button onclick='history.back()'>← Zpět</button>";
        echo "</div>";

        echo "<div class='preview-container'>";
        echo "<div class='code-content' id='codeContent'>$fileContent</div>";
        echo "</div>";

        echo "<script>
            function copyCode() {
                const codeContent = document.getElementById('codeContent');
                const textArea = document.createElement('textarea');
                textArea.value = codeContent.textContent;
                document.body.appendChild(textArea);
                textArea.select();
                try {
                    document.execCommand('copy');
                    alert('✅ Kód zkopírován do schránky!');
                } catch (err) {
                    alert('❌ Chyba při kopírování: ' + err);
                }
                document.body.removeChild(textArea);
            }
        </script>";
        echo "</div></body></html>";
        exit;
    }
}

// 🔥 NOVÉ: LOAD DIRECTORY ACTION
if (isset($_GET['action']) && $_GET['action'] === 'load_dir' && isset($_GET['path'])) {
    $dirPath = $_GET['path'];

    // Načtení konfigurace
    $config = require __DIR__ . '/config/app.php';

    $scanner = new Scanner\Core\ScannerEngine($config);
    $scanner->handleDirectoryLoad($dirPath);
    exit;
}

// 🔥 NOVÉ: RULES ACTION
if (isset($_GET['action']) && $_GET['action'] === 'rules') {
    require_once __DIR__ . '/src/Core/RulesController.php';
    $rulesController = new Scanner\Core\RulesController();
    $rulesController->run();
    exit;
}

// 🔥 NOVÉ: DIRECTORY PREVIEW ACTION
if (isset($_GET['action']) && $_GET['action'] === 'preview_dir' && isset($_GET['path'])) {
    $dirPath = $_GET['path'];

    // Načtení konfigurace
    $config = require __DIR__ . '/config/app.php';

    $scanner = new Scanner\Core\ScannerEngine($config);
    $scanner->handleDirectoryPreview($dirPath);
    exit;
}
// Načtení konfigurace
$config = require __DIR__ . '/config/app.php';

use Scanner\Logger\AdvancedLogger;

$logger = AdvancedLogger::getInstance([
    'file_path' => '/logs/',
    'echo' => true,
    'min_level' => 'DEBUG'
]);

// Spuštění scanneru
try {
    $scanner = new Scanner\Core\ScannerEngine($config);
    $scanner->run();
} catch (Exception $e) {
    echo "❌ Chyba scanneru: " . $e->getMessage();
    error_log("Scanner Error: " . $e->getMessage());
}
?>