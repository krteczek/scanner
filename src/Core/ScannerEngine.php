<?php
declare(strict_types=1);

namespace Scanner\Core;

use Scanner\Services\ProjectScanner;
use Scanner\Services\CodeAnalyzer;
use Scanner\Services\ExportService;

/**
 * ScannerEngine - hlavní třída pro skenování projektů
 * Používá konzistentní formát: ['files' => [...], 'directories' => [...], 'stats' => [...], 'tree' => [...]]
 */
class ScannerEngine
{
    private array $config;
    private ProjectScanner $projectScanner;
    private CodeAnalyzer $codeAnalyzer;
    private ExportService $exportService;
    
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->projectScanner = new ProjectScanner($config);
        $this->codeAnalyzer = new CodeAnalyzer($config['rules'] ?? []);
        $this->exportService = new ExportService($config);
    }
    
    /**
     * Skenuje projekt a vrací kompletní analýzu v konzistentním formátu
     * 
     * @return array [
     *   'structure' => [
     *     'files' => array,      // soubory s metadaty
     *     'directories' => array, // adresáře s metadaty
     *     'tree' => array,       // zobrazení pro UI
     *     'stats' => array       // základní statistiky
     *   ],
     *   'analysis' => array,     // výsledky analýzy kódu
     *   'stats' => array,        // globální statistiky
     *   'project_path' => string,
     *   'scan_time' => string
     * ]
     */
    public function scanProject(string $projectPath): array
    {
        // 1. Načti strukturu projektu v konzistentním formátu
        $structure = $this->getProjectStructure($projectPath);
        
        // 2. Analyzuj soubory (pouze skutečné soubory, ne display položky)
        $analysis = $this->analyzeProjectFiles($projectPath, $structure['files'] ?? []);
        
        // 3. Vytvoř globální statistiky
        $stats = $this->createGlobalStats($structure, $analysis);
        
        return [
            'structure' => $structure,
            'analysis' => $analysis,
            'stats' => $stats,
            'project_path' => $projectPath,
            'scan_time' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Získá strukturu projektu v konzistentním formátu
     * Vždy používá ProjectScanner::scan() který garantuje formát
     * 
     * @return array [
     *   'files' => [
     *     ['path' => string, 'name' => string, 'size' => int, 'extension' => string, ...]
     *   ],
     *   'directories' => [
     *     ['path' => string, 'name' => string]
     *   ],
     *   'tree' => array,  // generováno z files a directories
     *   'stats' => array  // základní statistiky
     * ]
     */
    public function getProjectStructure(string $projectPath): array
    {
        // VŽDY použijeme scan() pro konzistentní formát
        $scanResult = $this->projectScanner->scan($projectPath);
        
        // Zajistíme, že máme všechny klíče
        $structure = [
            'files' => $scanResult['files'] ?? [],
            'directories' => $scanResult['directories'] ?? [],
            'stats' => $scanResult['stats'] ?? ['total_files' => 0, 'total_size' => 0]
        ];
        
        // Vytvoříme 'tree' pro zobrazení
        $structure['tree'] = $this->createTreeDisplay($structure['files'], $structure['directories']);
        
        return $structure;
    }
    
    /**
     * Analyzuje soubory projektu podle pravidel
     * 
     * @param array $files Formát: [['path' => string, 'name' => string, 'extension' => string, ...], ...]
     * @return array [
     *   'issues' => array,        // nalezené problémy
     *   'total_issues' => int,    // celkový počet problémů
     *   'files_analyzed' => int   // počet analyzovaných souborů
     * ]
     */
    public function analyzeProjectFiles(string $projectPath, array $files): array
    {
        // Kontrola formátu - musíme mít pole s 'path' klíčem
        if (empty($files)) {
            return [
                'issues' => [],
                'total_issues' => 0,
                'files_analyzed' => 0
            ];
        }
        
        // Zavoláme analyzér s všemi soubory najednou
        $result = $this->codeAnalyzer->analyzeProject($files, $projectPath);
        
        return [
            'issues' => $result['issues'] ?? [],
            'total_issues' => $result['stats']['issues_found'] ?? 0,
            'files_analyzed' => $result['stats']['files_analyzed'] ?? 0
        ];
    }
    
    /**
     * Vytvoří zobrazení stromové struktury pro UI
     * 
     * @param array $files Formát: [['path' => string, ...], ...]
     * @param array $directories Formát: [['path' => string, ...], ...]
     * @return array [['display' => string, 'metadata' => array], ...]
     */
/**
 * Vytvoří zobrazení stromové struktury pro UI s přirozeným řazením
 * 
 * @param array $files Formát: [['path' => string, ...], ...]
 * @param array $directories Formát: [['path' => string, ...], ...]
 * @return array [['display' => string, 'metadata' => array], ...]
 */
private function createTreeDisplay(array $files, array $directories): array
{
    // 1. Vytvoříme asociativní pole pro rychlé vyhledávání
    $filesByDir = [];
    $dirsByParent = [];
    
    // 2. Rozebereme soubory a složky podle jejich adresářů
    foreach ($files as $file) {
        $dir = dirname($file['path']);
        if ($dir === '.') $dir = '';
        
        if (!isset($filesByDir[$dir])) {
            $filesByDir[$dir] = [];
        }
        $filesByDir[$dir][] = $file;
    }
    
    foreach ($directories as $dir) {
        $parent = dirname($dir['path']);
        if ($parent === '.') $parent = '';
        
        if (!isset($dirsByParent[$parent])) {
            $dirsByParent[$parent] = [];
        }
        $dirsByParent[$parent][] = $dir;
    }
    
    // 3. Rekurzivní funkce pro vytvoření stromu
    $tree = [];
    $this->addTreeItems('', $dirsByParent, $filesByDir, $tree, 0);
    
    return $tree;
}

/**
 * Rekurzivně přidává položky do stromu
 */
private function addTreeItems(
    string $currentDir, 
    array &$dirsByParent, 
    array &$filesByDir, 
    array &$tree, 
    int $depth
): void {
    // 1. Nejprve adresáře v aktuální složce
    if (isset($dirsByParent[$currentDir])) {
        // Seřadíme adresáře přirozeně
        usort($dirsByParent[$currentDir], function($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });
        
        foreach ($dirsByParent[$currentDir] as $dir) {
            $displayPath = $dir['path'];
            $tree[] = [
                'display' => str_repeat('│   ', $depth) . '📁 ' . $dir['name'] . '/',
                'metadata' => [
                    'type' => 'directory',
                    'path' => $dir['path'],
                    'name' => $dir['name'],
                    'depth' => $depth
                ]
            ];
            
            // Rekurzivně zpracujeme podadresáře
            $this->addTreeItems($dir['path'], $dirsByParent, $filesByDir, $tree, $depth + 1);
        }
    }
    
    // 2. Pak soubory v aktuální složce
    if (isset($filesByDir[$currentDir])) {
        // Seřadíme soubory přirozeně
        usort($filesByDir[$currentDir], function($a, $b) {
            return strnatcasecmp($a['name'], $b['name']);
        });
        
        foreach ($filesByDir[$currentDir] as $file) {
            $tree[] = [
                'display' => str_repeat('│   ', $depth) . '📄 ' . $file['name'],
                'metadata' => [
                    'type' => 'file',
                    'path' => $file['path'],
                    'name' => $file['name'],
                    'size' => $file['size'] ?? 0,
                    'extension' => $file['extension'] ?? '',
                    'modified' => $file['modified'] ?? null,
                    'depth' => $depth
                ]
            ];
        }
    }
}    
    /**
     * Vytvoří globální statistiky z analýzy
     */
    private function createGlobalStats(array $structure, array $analysis): array
    {
        return [
            'total_files' => count($structure['files']),
            'total_dirs' => count($structure['directories']),
            'total_items' => count($structure['files']) + count($structure['directories']),
            'issues_found' => $analysis['total_issues'] ?? 0,
            'files_analyzed' => $analysis['files_analyzed'] ?? 0,
            'project_size' => $structure['stats']['total_size'] ?? 0,
            'scan_timestamp' => time()
        ];
    }
    
    /**
     * Kompatibilní metoda pro starý kód - vrací pouze tree display
     * 
     * @return array [['display' => string, 'metadata' => array], ...]
     */
    public function showStructure(string $projectName): array
    {
        $projectPath = dirname($this->getScannerRoot()) . '/' . $projectName;
        $structure = $this->getProjectStructure($projectPath);
        return $structure['tree'];
    }
    
    /**
     * Kompatibilní metoda run() pro staré volání
     */
    public function run(array $params = []): array
    {
        $action = $params['action'] ?? 'scan';
        $project = $params['project'] ?? '';
        
        if (empty($project)) {
            return ['error' => 'Nebyl specifikován projekt'];
        }
        
        $projectPath = dirname($this->getScannerRoot()) . '/' . $project;
        
        switch ($action) {
            case 'scan':
            case 'structure':
                // Vrací kompletní strukturu
                return $this->scanProject($projectPath);
                
            case 'analyze':
                // Vrací pouze analýzu
                $structure = $this->getProjectStructure($projectPath);
                return $this->analyzeProjectFiles($projectPath, $structure['files']);
                
            case 'display':
                // Vrací pouze tree display (kompatibilita)
                return [
                    'tree' => $this->showStructure($project),
                    'project' => $project
                ];
                
            default:
                return ['error' => 'Neplatná akce: ' . $action];
        }
    }
    
    /**
     * Exportuje výsledky skenování
     * 
     * @param array $scanResult Výsledek z scanProject()
     * @param string $format Formát exportu (json, html, txt)
     * @return string Exportovaná data
     */
    public function exportResults(array $scanResult, string $format = 'json'): string
    {
        if (method_exists($this->exportService, 'export')) {
            return $this->exportService->export($scanResult, $format);
        }
        
        // Fallback: JSON export
        return json_encode($scanResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Získá podrobné informace o konkrétním souboru
     * 
     * @param string $projectPath Cesta k projektu
     * @param string $filePath Relativní cesta k souboru
     * @return array Podrobné informace o souboru
     */
    public function getFileDetails(string $projectPath, string $filePath): array
    {
        $fullPath = $projectPath . '/' . ltrim($filePath, '/');
        
        if (!file_exists($fullPath)) {
            return ['error' => 'Soubor neexistuje: ' . $filePath];
        }
        
        $structure = $this->getProjectStructure($projectPath);
        
        // Najdeme soubor ve struktuře
        foreach ($structure['files'] as $file) {
            if ($file['path'] === $filePath) {
                $file['full_path'] = $fullPath;
                $file['content_exists'] = is_readable($fullPath);
                $file['content'] = $file['content_exists'] ? file_get_contents($fullPath) : '';
                $file['lines'] = $file['content_exists'] ? count(file($fullPath)) : 0;
                
                // Analýza tohoto konkrétního souboru
                $file['analysis'] = $this->codeAnalyzer->analyzeProject([$file], $projectPath);
                
                return $file;
            }
        }
        
        return ['error' => 'Soubor nenalezen ve struktuře: ' . $filePath];
    }
    
    /**
     * Pomocná metoda pro získání cesty k scanneru
     */
    private function getScannerRoot(): string
    {
        return $this->config['paths']['scanner_root'] ?? dirname(__DIR__, 2);
    }
    
    /**
     * Získá konfiguraci scanneru
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Nastaví nová pravidla pro analýzu
     */
    public function setRules(array $rules): void
    {
        $this->codeAnalyzer = new CodeAnalyzer($rules);
    }
    
    /**
     * Kontroluje, zda je cesta platným projektovým adresářem
     */
    public function isValidProject(string $projectPath): bool
    {
        if (!is_dir($projectPath) || !is_readable($projectPath)) {
            return false;
        }
        
        // Můžeme přidat další kontroly (např. existuje composer.json, package.json, atd.)
        $requiredFiles = ['composer.json', 'package.json', 'README.md', '.git'];
        
        foreach ($requiredFiles as $file) {
            if (file_exists($projectPath . '/' . $file)) {
                return true;
            }
        }
        
        // Pokud nemá žádný ze standardních souborů, zkontrolujme alespoň, že obsahuje nějaké soubory
        $items = scandir($projectPath);
        $itemCount = count(array_diff($items, ['.', '..']));
        
        return $itemCount > 0;
    }
}