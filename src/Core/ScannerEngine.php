<?php
// scanner/src/Core/ScannerEngine.php

declare(strict_types=1);

namespace Scanner\Core;

use Scanner\Services\ProjectScanner;
use Scanner\Services\CodeAnalyzer;
use Scanner\Services\ExportService;

/**
 * Hlavní engine scanneru - řídí celou aplikaci
 * Zajišťuje zobrazení rozhraní, zpracování požadavků a koordinaci služeb
 * Implementuje hover okna pro zobrazení metadat souborů
 *
 * @package Scanner\Core
 * @author KRS3
 * @version 2.3 - Přidána zaškrtávací pole a batch export
 */
class ScannerEngine
{
    /** @var array Konfigurace aplikace */
    private array $config;

    /** @var ProjectScanner Instance projektového scanneru pro načítání struktur */
    private ProjectScanner $projectScanner;

    /** @var CodeAnalyzer Instance analyzátoru kódu pro kontrolu kvality */
    private CodeAnalyzer $codeAnalyzer;

    /** @var ExportService Instance služby pro generování exportů */
    private ExportService $exportService;

    /**
     * Inicializuje scanner engine s konfigurací
     * Vytváří instance všech potřebných služeb
     *
     * @param array $config Konfigurace aplikace
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->projectScanner = new ProjectScanner($this->config);
        $this->codeAnalyzer = new CodeAnalyzer($this->config);
        $this->exportService = new ExportService($this->config);
    }

    /**
     * Spustí hlavní aplikaci scanneru
     * Rozhoduje mezi zobrazením hlavního rozhraní a zpracováním skenování
     * Podle URL parametru 'scan' volá příslušnou akci
     *
     * @return void
     */
    public function run(): void
    {
        if (isset($_GET['scan'])) {
            $this->handleScanRequest($_GET['scan']);
        } else {
            $this->showMainInterface();
        }
    }

    /**
     * Zobrazí hlavní rozhraní s výpisem dostupných projektů
     * Zobrazuje tlačítko pro správu pravidel a seznam projektů
     *
     * @return void
     */
    private function showMainInterface(): void
    {
        $projects = $this->projectScanner->getProjects();

        echo "<!DOCTYPE html><html><head><title>Project Scanner</title>";
        echo "<link rel='stylesheet' href='public/style.css'>";
        echo $this->getJavaScript();
        echo "</head><body>";

        echo "<div class='container'>";
        echo "<h1>🔍 Project Scanner</h1>";

        echo "<div style='text-align: center; margin: 20px 0;'>";
        echo "<a href='?action=rules' class='btn' style='background: #9b59b6;'>⚙️ Spravovat AI Pravidla</a>";
        echo "</div>";

        if (empty($projects)) {
            echo "<p>❌ Žádné projekty nenalezeny v: " . htmlspecialchars($this->config['paths']['projects_root']) . "</p>";
        } else {
            echo "<div class='project-list'>";
            foreach ($projects as $project) {
                echo "<a href='?scan=" . urlencode($project) . "' class='project-btn'>📂 " . htmlspecialchars($project) . "</a>";
            }
            echo "</div>";
        }

        echo "<div id='results'></div>";
        echo "</div></body></html>";
    }

    /**
     * Zpracuje požadavek na skenování projektu
     * Načte strukturu projektu, zkontroluje důležité soubory a vygeneruje export
     * Zobrazí výsledky s hover okny pro metadata souborů
     *
     * @param string $projectName Název projektu ke skenování
     * @return void
     */
private function handleScanRequest(string $projectName): void
{
    $projectPath = $this->config['paths']['projects_root'] . '/' . $projectName;

    if (!is_dir($projectPath)) {
        echo "❌ Projekt '{$projectName}' neexistuje!";
        return;
    }

    // Získáme strukturu s metadaty
    $structure = $this->projectScanner->scanProjectWithMetadata($projectPath);
    $importantFilesCheck = $this->projectScanner->checkImportantFiles($projectPath);

    // Načteme AI pravidla
    $aiRules = @include __DIR__ . '/../../config/rules.php';
    if (!$aiRules) {
        $aiRules = [];
    }

    // Generujeme export
    $textExport = $this->exportService->generateTextExport(
        $projectName,
        array_column($structure, 'display'),
        $importantFilesCheck,
        $projectPath,
        $aiRules
    );

    // 🔥 OPRAVA: Přidáme JavaScript pro tuto stránku
    echo "<!DOCTYPE html><html><head><title>Project Scanner - {$projectName}</title>";
echo "<link rel='stylesheet' href='public/style.css'>";
echo "<script>
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
</script>";
echo "</head><body>";

    // Výstup výsledků
    echo "<div class='scan-results'>";
    echo "<h3>📁 Struktura projektu: <strong>{$projectName}</strong></h3>";

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

    // ZOBRAZENÍ STRUKTURY S TITLE ATRIBUTY
    echo "<div class='structure-with-hover' id='fileTree'>";
    foreach ($structure as $item) {
        $this->renderFileItemWithHover($item);
    }
    echo "</div>";

    // Kontrola důležitých souborů
    echo "<div class='important-files'>";
    echo "<h4>🎯 Kontrola důležitých souborů:</h4>";
    foreach ($importantFilesCheck as $file => $exists) {
        $status = $exists ? '✅' : '❌';
        echo "<div>{$status} {$file}</div>";
    }
    echo "</div>";

    echo "<br><a href='?' class='btn'>← Zpět na výběr projektu</a>";
    echo "</div>";
    echo "</body></html>"; // ⬅️ Taky tohle chybělo!
}

	/**
	 * Zpracuje požadavek na preview obsahu adresáře
	 * Vrací HTML s obsahem všech souborů v adresáři
	 *
	 * @param string $dirPath Cesta k adresáři
	 * @return void
	 */
	public function handleDirectoryPreview(string $dirPath): void
	{
	    if (!is_dir($dirPath)) {
	        echo "❌ Adresář '{$dirPath}' neexistuje!";
	        return;
	    }

	    $dirName = basename($dirPath);
	    echo "<!DOCTYPE html><html><head><title>Preview: {$dirName}</title>";
	    echo "<link rel='stylesheet' href='public/style.css'>";
	    echo $this->getDirectoryPreviewJavaScript();
	    echo "<style>
	        .directory-preview-container {
	            max-width: 1200px;
	            margin: 0 auto;
	            padding: 20px;
	        }
	        .batch-actions {
	            background: #2c3e50;
	            color: white;
	            padding: 15px;
	            border-radius: 8px;
	            margin: 20px 0;
	            display: flex;
	            gap: 10px;
	            flex-wrap: wrap;
	            align-items: center;
	        }
	        .batch-btn {
	            background: #e67e22;
	            color: white;
	            border: none;
	            padding: 8px 15px;
	            border-radius: 5px;
	            cursor: pointer;
	            font-size: 14px;
	        }
	        .batch-btn:hover {
	            background: #d35400;
	        }
	        .batch-btn.copy-all {
	            background: #27ae60;
	        }
	        .batch-btn.copy-all:hover {
	            background: #229954;
	        }
	        .file-preview-section {
	            margin: 20px 0;
	            background: white;
	            padding: 15px;
	            border-radius: 8px;
	            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
	            border-left: 4px solid #3498db;
	            scroll-margin-top: 80px; /* Pro hladký scroll */
	        }
	        .file-header {
	            background: #3498db;
	            color: white;
	            padding: 10px 15px;
	            border-radius: 5px;
	            margin: -15px -15px 15px -15px;
	            display: flex;
	            justify-content: space-between;
	            align-items: center;
	        }
	        .file-checkbox {
	            margin-right: 10px;
	            transform: scale(1.2);
	        }
	        .file-content {
	            background: #f8f9fa;
	            border: 1px solid #dee2e6;
	            border-radius: 5px;
	            padding: 15px;
	            font-family: 'Courier New', monospace;
	            font-size: 13px;
	            line-height: 1.4;
	            white-space: pre-wrap;
	            max-height: 500px;
	            overflow: auto;
	        }
	        .copy-btn {
	            background: #27ae60;
	            color: white;
	            border: none;
	            padding: 5px 10px;
	            border-radius: 3px;
	            cursor: pointer;
	            font-size: 12px;
	        }
	        .file-list {
	            margin: 10px 0;
	            padding: 10px;
	            background: #e8f4fd;
	            border-radius: 5px;
	        }
	        .selected-count {
	            background: #9b59b6;
	            padding: 5px 10px;
	            border-radius: 20px;
	            font-weight: bold;
	        }
	        /* 🔥 NOVÉ: KLIKATELNÉ ODKAZY V SEZNAMU SOUBORŮ */
	        .file-link-in-list {
	            color: #3498db;
	            text-decoration: none;
	            cursor: pointer;
	            padding: 2px 5px;
	            border-radius: 3px;
	            transition: all 0.2s ease;
	        }
	        .file-link-in-list:hover {
	            background-color: rgba(52, 152, 219, 0.1);
	            text-decoration: underline;
	        }
	        .file-link-in-list:active {
	            background-color: rgba(52, 152, 219, 0.2);
	        }
	    </style>";
	    echo "</head><body>";
	    echo "<div class='directory-preview-container'>";
	    echo "<h2>📁 Preview adresáře: {$dirName}</h2>";
	    echo "<a href='javascript:history.back()' class='btn' style='margin-bottom: 20px;'>← Zpět</a>";

	    // Načteme všechny soubory v adresáři
	    $files = $this->findCodeFiles($dirPath);

	    if (empty($files)) {
	        echo "<p>❌ V adresáři nebyly nalezeny žádné soubory k zobrazení.</p>";
	    } else {
	        // 🔥 BATCH AKCE - HLAVIČKA S VÝBĚREM
	        echo "<div class='batch-actions'>";
	        echo "<div style='display: flex; align-items: center; gap: 15px;'>";
	        echo "<strong>Hromadné akce:</strong>";
	        echo "<button class='batch-btn' onclick='selectAllFiles()'>✅ Vybrat vše</button>";
	        echo "<button class='batch-btn' onclick='deselectAllFiles()'>❌ Zrušit výběr</button>";
	        echo "<button class='batch-btn copy-all' onclick='copySelectedFiles()'>📋 Kopírovat vybrané</button>";
	        echo "<button class='batch-btn' onclick='exportSelectedFiles()'>💾 Export vybraných</button>";
	        echo "</div>";
	        echo "<div class='selected-count' id='selectedCount'>0 vybráno</div>";
	        echo "</div>";

	        // 🔥 NOVÉ: KLIKATELNÝ SEZNAM SOUBORŮ S ANCHOR LINKS
	        echo "<div class='file-list'>";
	        echo "<strong>Nalezené soubory ({$files['count']}):</strong><br>";
	        foreach ($files['files'] as $index => $fileInfo) {
	            $fileId = 'file_' . $index;
	            echo "<a href='#{$fileId}' class='file-link-in-list' title='Klikni pro přesun k souboru'>";
	            echo "📄 {$fileInfo['name']} ({$fileInfo['size']})";
	            echo "</a><br>";
	        }
	        echo "</div>";

	        // Zobrazíme obsah každého souboru S ZAŠKRTÁVACÍM POLÍČKEM
	        foreach ($files['files'] as $index => $fileInfo) {
	            $this->renderFilePreview($fileInfo['path'], $fileInfo['name'], $index);
	        }
	    }

	    echo "</div></body></html>";
	}
    /**
     * Najde všechny kódové soubory v adresáři
     *
     * @param string $path Cesta k adresáři
     * @return array Seznam souborů
     */
    private function findCodeFiles(string $path): array
    {
        $codeExtensions = ['php', 'js', 'css', 'html', 'txt', 'sql', 'json', 'xml', 'md'];
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = strtolower($file->getExtension());
                if (in_array($extension, $codeExtensions)) {
                    $files[] = [
                        'path' => $file->getPathname(),
                        'name' => $file->getFilename(),
                        'size' => $this->formatFileSize($file->getSize())
                    ];
                }
            }
        }

        return [
            'files' => $files,
            'count' => count($files)
        ];
    }

    /**
     * Formátuje velikost souboru
     *
     * @param int $bytes Velikost v bytech
     * @return string Naformátovaná velikost
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . 'MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 1) . 'KB';
        } else {
            return $bytes . 'B';
        }
    }

/**
 * Vykreslí preview souboru S ZAŠKRTÁVACÍM POLÍČKEM
 *
 * @param string $filePath Cesta k souboru
 * @param string $fileName Název souboru
 * @param int $index Index souboru
 * @return void
 */
private function renderFilePreview(string $filePath, string $fileName, int $index): void
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return;
    }

    $content = file_get_contents($filePath);
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    $fileId = 'file_' . $index;

    // Bezpečně escapovat obsah pro HTML atribut
    $htmlSafeContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    $htmlSafeFileName = htmlspecialchars($fileName, ENT_QUOTES, 'UTF-8');

    echo "<div class='file-preview-section' id='{$fileId}'>";
    echo "<div class='file-header'>";
    echo "<div style='display: flex; align-items: center;'>";
    echo "<input type='checkbox' class='file-checkbox' id='checkbox_{$fileId}'
                  data-filename='{$htmlSafeFileName}'
                  data-content='{$htmlSafeContent}'
                  onchange='updateSelectionCount()'>";
    echo "<strong>📄 {$fileName}</strong>";
    echo "</div>";

    // 🔥 OPRAVA: Použít onclick s voláním funkce s parametry z data atributů
    echo "<button class='copy-btn'
                  onclick='copyFileContent(this)'
                  data-filename='{$htmlSafeFileName}'
                  data-content='{$htmlSafeContent}'>📋 Kopírovat</button>";
    echo "</div>";

echo "<div class='file-content'>";
if (in_array($extension, ['php', 'html', 'js', 'css'])) {
    // ZAPNOUT output buffering
    ob_start();
    $result = highlight_string($content, true); // true = return as string
    $highlighted = ob_get_clean();

    if ($result === false && !empty($highlighted)) {
        echo $highlighted;
    } elseif ($result !== false) {
        echo $result;
    } else {
        echo '<pre>' . htmlspecialchars($content) . '</pre>';
    }
} else {
    echo '<pre>' . htmlspecialchars($content) . '</pre>';
}
echo "</div>";
    echo "</div>";
}
    /**
     * Vykreslí položku souboru nebo adresáře s hover okenem
     *
     * @param array $item Položka struktury s metadaty
     * @return void
     */
    private function renderFileItemWithHover(array $item): void
    {
        $display = htmlspecialchars($item['display']);
        $metadata = $item['metadata'];

        $titleText = "";
        if ($metadata['type'] === 'directory') {
            $titleText = "📁 {$metadata['name']}\n• Typ: Adresář\n• Cesta: {$metadata['path']}\n• Upraveno: {$metadata['modified']}";

            echo "<div class='file-item directory clickable-directory'
                      onclick=\"window.open('?action=preview_dir&path=" . urlencode($metadata['path']) . "', '_blank')\"
                      title='" . htmlspecialchars($titleText) . "'>";
            echo $display;
            echo "</div>";

        } else {
            $titleText = "📄 {$metadata['name']}\n• Velikost: {$metadata['size']}\n• Řádků: {$metadata['lines']}\n• Upraveno: {$metadata['modified']}\n• Typ: " . ($metadata['has_php'] ? 'PHP soubor' : $metadata['extension']) . "\n• Cesta: {$metadata['path']}";

            $fileStatus = $this->getFileStatusColor($metadata['path']);
            echo "<div class='file-item file {$fileStatus}' title='" . htmlspecialchars($titleText) . "'>";
            echo "<a href='?preview=" . urlencode($metadata['path']) . "' class='file-link'>";
            echo $display;
            echo "</a>";
            echo "</div>";
        }
    }

    /**
     * Určí barvu souboru podle jeho stavu
     *
     * @param string $filePath Cesta k souboru
     * @return string CSS třída pro barvu
     */
    private function getFileStatusColor(string $filePath): string
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        if ($extension === 'php') {
            return 'file-warning';
        }

        return 'file-ok';
    }

    /**
     * Vrátí JavaScript pro directory preview s batch funkcemi
     *
     * @return string JavaScript kód
     */
    private function getDirectoryPreviewJavaScript(): string
    {
        return "
        <script>
        // Funkce pro aktualizaci počtu vybraných souborů
        function updateSelectionCount() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            const selectedCount = document.getElementById('selectedCount');
            selectedCount.textContent = checkboxes.length + ' vybráno';
        }

        // Vybrat všechny soubory
        function selectAllFiles() {
            const checkboxes = document.querySelectorAll('.file-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = true);
            updateSelectionCount();
        }

        // Zrušit výběr všech souborů
        function deselectAllFiles() {
            const checkboxes = document.querySelectorAll('.file-checkbox');
            checkboxes.forEach(checkbox => checkbox.checked = false);
            updateSelectionCount();
        }

        // Kopírovat vybrané soubory
        function copySelectedFiles() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('❌ Nejprve vyberte soubory ke kopírování!');
                return;
            }

            let combinedContent = '';
            checkboxes.forEach(checkbox => {
                const fileName = checkbox.getAttribute('data-filename');
                const content = checkbox.getAttribute('data-content');
                combinedContent += `// === 📄 \${fileName} ===\\n\\n\${content}\\n\\n// === KONEC: \${fileName} ===\\n\\n`;
            });

            const textArea = document.createElement('textarea');
            textArea.value = combinedContent;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                alert('✅ ' + checkboxes.length + ' souborů zkopírováno do schránky!');
            } catch (err) {
                alert('❌ Chyba při kopírování: ' + err);
            }
            document.body.removeChild(textArea);
        }

        // Export vybraných souborů (stáhnout jako .txt)
        function exportSelectedFiles() {
            const checkboxes = document.querySelectorAll('.file-checkbox:checked');
            if (checkboxes.length === 0) {
                alert('❌ Nejprve vyberte soubory k exportu!');
                return;
            }

            let combinedContent = '';
            checkboxes.forEach(checkbox => {
                const fileName = checkbox.getAttribute('data-filename');
                const content = checkbox.getAttribute('data-content');
                combinedContent += `// === 📄 \${fileName} ===\\n\\n\${content}\\n\\n// === KONEC: \${fileName} ===\\n\\n`;
            });

            const blob = new Blob([combinedContent], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'export-vybranych-souboru.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            alert('💾 ' + checkboxes.length + ' souborů exportováno!');
        }

// Funkce pro kopírování jednotlivého souboru
function copyFileContent(buttonElement) {
    const fileName = buttonElement.getAttribute('data-filename');
    const content = buttonElement.getAttribute('data-content');

    const textArea = document.createElement('textarea');
    textArea.value = content;
    document.body.appendChild(textArea);
    textArea.select();
    try {
        document.execCommand('copy');
        alert('✅ Obsah souboru \"' + fileName + '\" zkopírován do schránky!');
    } catch (err) {
        alert('❌ Chyba při kopírování: ' + err);
    }
    document.body.removeChild(textArea);
}
        </script>
        ";
    }

    /**
     * Vrátí JavaScript kód pro interaktivní funkcionalitu aplikace
     *
     * @return string JavaScript kód
     */
    private function getJavaScript(): string
    {
        return "
        <script>
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

    /**
     * Zobrazí strukturu projektu
     *
     * @param string $projectName Název projektu
     * @return void
     */
    public function showStructure(string $projectName): void
    {
        $this->handleScanRequest($projectName);
    }

    /**
     * Zobrazí export projektu v textové podobě
     *
     * @param string $projectName Název projektu
     * @return void
     */
    public function showExport(string $projectName): void
    {
        $projectPath = $this->config['paths']['projects_root'] . '/' . $projectName;
        $structure = $this->projectScanner->scanProjectWithMetadata($projectPath);
        $importantFilesCheck = $this->projectScanner->checkImportantFiles($projectPath);

        $aiRules = @include __DIR__ . '/../../config/rules.php';
        if (!$aiRules) {
            $aiRules = [];
        }

        $textExport = $this->exportService->generateTextExport(
            $projectName,
            array_column($structure, 'display'),
            $importantFilesCheck,
            $projectPath,
            $aiRules
        );

        echo "<div id='exportArea' style='display:block; margin:15px 0'>";
        echo "<textarea id='exportText' style='width:100%; height:300px; font-family:monospace; background:#2c3e50; color:white; padding:10px; border-radius:5px;' readonly>";
        echo htmlspecialchars($textExport);
        echo "</textarea><br>";
        echo "<button onclick='copyExport()' style='background:#e67e22; margin-top:5px'>📋 Kopírovat do schránky</button>";
        echo "</div>";
    }

    /**
     * Zkopíruje obsah exportu do systémové schránky
     *
     * @return void
     */
    public function copyExport(): void
    {
        echo "<script>
            const textarea = document.getElementById('exportText');
            if (textarea) {
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
        </script>";
    }
}
?>