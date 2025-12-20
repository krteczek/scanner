<?php
declare(strict_types=1);

namespace Scanner\Core;

use Scanner\Services\ProjectScanner;
use Scanner\Services\CodeAnalyzer;
use Scanner\Services\ExportService;

/**
 * ScannerEngine - hlavní třída pro skenování projektů
 * ČISTÁ implementace bez magie, vrací přesně to co potřebujeme
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
     * Skenuje projekt a vrací kompletní analýzu
     * 
     * @return array [
     *   'structure' => array, // adresářová struktura
     *   'analysis' => array,  // výsledky analýzy kódu
     *   'stats' => array      // statistiky
     * ]
     */
    public function scanProject(string $projectPath): array
    {
        // 1. Načti strukturu projektu
        $structure = $this->getProjectStructure($projectPath);
        
        // 2. Analyzuj soubory
        $analysis = $this->analyzeProjectFiles($projectPath, $structure['files'] ?? []);
        
        // 3. Vytvoř statistiky
        $stats = $this->createStats($structure, $analysis);
        
        return [
            'structure' => $structure,
            'analysis' => $analysis,
            'stats' => $stats,
            'project_path' => $projectPath,
            'scan_time' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Získá strukturu projektu (soubory a složky)
     */
    public function getProjectStructure(string $projectPath): array
    {
        // Použij ProjectScanner
        if (method_exists($this->projectScanner, 'getFileTree')) {
            return $this->projectScanner->getFileTree($projectPath);
        }
        
        if (method_exists($this->projectScanner, 'scanProject')) {
            $result = $this->projectScanner->scanProject($projectPath);
            return is_array($result) ? ['tree' => $result] : ['tree' => []];
        }
        
        // Fallback: ruční skenování
        //return $this->scanDirectoryManually($projectPath);
        return $this->projectScanner->scan($projectPath);
    }
    
    /**
     * Analyzuje soubory projektu podle pravidel
     */
    public function analyzeProjectFiles(string $projectPath, array $files): array
    {
        $issues = [];
        
        foreach ($files as $file) {
            if (empty($file['path'])) continue;
            
            $fullPath = $projectPath . '/' . $file['path'];
            $fileIssues = $this->codeAnalyzer->analyzeFile($fullPath);
            
            foreach ($fileIssues as &$issue) {
                $issue['file'] = $file['path'];
                $issue['file_name'] = basename($file['path']);
            }
            
            $issues = array_merge($issues, $fileIssues);
        }
        
        return [
            'issues' => $issues,
            'total_issues' => count($issues),
            'files_analyzed' => count($files)
        ];
    }
    
    /**
     * Vrátí strukturu pro zobrazení (kompatibilní se starým systémem)
     */
    public function getDisplayStructure(string $projectPath): array
    {
        $structure = $this->getProjectStructure($projectPath);
        $displayItems = [];
        
        // Převod na display formát
        if (!empty($structure['tree'])) {
            foreach ($structure['tree'] as $item) {
                if (is_array($item) && isset($item['display'])) {
                    $displayItems[] = $item;
                } elseif (is_string($item)) {
                    $displayItems[] = ['display' => $item];
                }
            }
        }
        
        return $displayItems;
    }
    
    /**
     * Ruční skenování adresáře (fallback)
     */
    private function scanDirectoryManually(string $path, string $prefix = ''): array
    {
        $result = ['files' => [], 'directories' => [], 'tree' => []];
        
        if (!is_dir($path) || !is_readable($path)) {
            return $result;
        }
        
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $fullPath = $path . '/' . $item;
            $relativePath = ($prefix ? $prefix . '/' : '') . $item;
            
            if (is_dir($fullPath)) {
                $result['directories'][] = [
                    'path' => $relativePath,
                    'name' => $item
                ];
                
                $result['tree'][] = ['display' => '📁 ' . $relativePath . '/'];
                
                // Rekurze
                $subResult = $this->scanDirectoryManually($fullPath, $relativePath);
                $result['files'] = array_merge($result['files'], $subResult['files']);
                $result['directories'] = array_merge($result['directories'], $subResult['directories']);
                $result['tree'] = array_merge($result['tree'], $subResult['tree']);
                
            } else {
                $result['files'][] = [
                    'path' => $relativePath,
                    'name' => $item,
                    'size' => filesize($fullPath),
                    'extension' => pathinfo($item, PATHINFO_EXTENSION)
                ];
                
                $result['tree'][] = ['display' => '📄 ' . $relativePath];
            }
        }
        
        return $result;
    }
    
    /**
     * Vytvoří statistiky
     */
    private function createStats(array $structure, array $analysis): array
    {
        return [
            'total_files' => count($structure['files'] ?? []),
            'total_dirs' => count($structure['directories'] ?? []),
            'issues_found' => $analysis['total_issues'] ?? 0,
            'files_analyzed' => $analysis['files_analyzed'] ?? 0,
            'scan_timestamp' => time()
        ];
    }
    
    /**
     * Kompatibilní metoda pro starý kód
     */
    public function showStructure(string $projectName): array
    {
        $projectPath = dirname($this->getScannerRoot()) . '/' . $projectName;
        return $this->getDisplayStructure($projectPath);
    }
    
    /**
     * Kompatibilní metoda run()
     */
    public function run(array $params = []): array
    {
        $action = $params['action'] ?? 'scan';
        $project = $params['project'] ?? '';
        
        switch ($action) {
            case 'scan':
            case 'structure':
                $projectPath = dirname($this->getScannerRoot()) . '/' . $project;
                return $this->scanProject($projectPath);
                
            case 'analyze':
                $projectPath = dirname($this->getScannerRoot()) . '/' . $project;
                $structure = $this->getProjectStructure($projectPath);
                return $this->analyzeProjectFiles($projectPath, $structure['files'] ?? []);
                
            default:
                return ['error' => 'Neplatná akce: ' . $action];
        }
    }
    
    /**
     * Pomocná metoda pro získání cesty k scanneru
     */
    private function getScannerRoot(): string
    {
        return $this->config['paths']['scanner_root'] ?? dirname(__DIR__, 2);
    }
}