<?php
declare(strict_types=1);

namespace Scanner\Services;

class ProjectScanner
{
    private array $config;
    
public function __construct(array $config)
{
	echo "<div style='background:#e0f7fa;padding:5px;margin:2px;'>";
echo "📢 ProjectScanner::" . __FUNCTION__ . "() called";
echo "</div>";
    $this->config = $config;
    
    error_log("=== PROJECTSCANNER CONSTRUCTOR ===");
    error_log("Config received, keys: " . implode(', ', array_keys($config)));
    
    // Speciálně zkontroluj ignore_patterns
    if (isset($config['ignore_patterns'])) {
        error_log("ignore_patterns FOUND, count: " . count($config['ignore_patterns']));
        error_log("First few: " . implode(', ', array_slice($config['ignore_patterns'], 0, 5)));
    } else {
        error_log("NO ignore_patterns in config!");
    }
}


    /**
     * Scanuje projekt a vrací strukturu s metadaty
     * @return array [files, directories, stats]
     */
    public function scan(string $projectPath): array
    {
echo "<div style='background:#e0f7fa;padding:5px;margin:2px;'>";
echo "📢 ProjectScanner::" . __FUNCTION__ . "() called";
echo "</div>"; 
 
        $result = [
            'files' => [],
            'directories' => [],
            'stats' => ['total_files' => 0, 'total_size' => 0]
        ];
        
        $this->scanDirectory($projectPath, $result, '');
        
        return $result;
    }
    
    private function scanDirectory(string $path, array &$result, string $relativePath): void
    {
    	
    	echo "<div style='background:#e0f7fa;padding:5px;margin:2px;'>";
echo "📢 ProjectScanner::" . __FUNCTION__ . "() called";
echo "</div>";


        if (!is_dir($path) || !is_readable($path)) {
            return;
        }
        
        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $path . '/' . $item;
            $itemRelativePath = ($relativePath ? $relativePath . '/' : '') . $item;
            
            if (is_dir($fullPath)) {
                // Přeskoč ignorované složky
if ($this->shouldIgnore($itemRelativePath)) {
    error_log("ProjectScanner: FIRST IGNORE CALL for '$itemRelativePath'");
    continue;
}                
                $result['directories'][] = [
                    'path' => $itemRelativePath,
                    'name' => $item
                ];
                
                $this->scanDirectory($fullPath, $result, $itemRelativePath);
                
            } elseif (is_file($fullPath)) {
                // Přeskoč ignorované soubory
// Na řádku 62 (druhé volání):
if ($this->shouldIgnore($itemRelativePath)) {
    error_log("ProjectScanner: SECOND IGNORE CALL for '$itemRelativePath'");
    continue;
}                
                $result['files'][] = [
                    'path' => $itemRelativePath,
                    'name' => $item,
                    'size' => filesize($fullPath),
                    'extension' => pathinfo($item, PATHINFO_EXTENSION),
                    'modified' => filemtime($fullPath)
                ];
                
                $result['stats']['total_files']++;
                $result['stats']['total_size'] += filesize($fullPath);
            }
        }
    }
private function shouldIgnore(string $path): bool
{    
echo "<div style='background:#e0f7fa;padding:5px;margin:2px;'>";
echo "📢 ProjectScanner::" . __FUNCTION__ . "() called";
echo "</div>";
    // DEBUG 1: Co dostáváme?
    error_log("=== SHOULD_IGNORE CALLED ===");
    error_log("Path to check: '$path'");
    error_log("Config ignore_patterns exists: " . 
             (isset($this->config['ignore_patterns']) ? 'YES' : 'NO'));
    
    $ignorePatterns = $this->config['ignore_patterns'] ?? [];
    print_r($ignorePatterns); echo "kooook";
    error_log("Ignore patterns count: " . count($ignorePatterns));
    error_log("Ignore patterns: " . implode(', ', $ignorePatterns));
    
    foreach ($ignorePatterns as $index => $pattern) {
        error_log("  Checking pattern $index: '$pattern' against '$path'");
        
        // 1. Přesná shoda
        if ($path === $pattern) {
            error_log("    ✅ EXACT MATCH: $path == $pattern");
            return true;
        }
        
        // 2. Adresář (končí /)
        if (substr($pattern, -1) === '/') {
            // Kontrola: "vendor/" matchne "vendor/" i "vendor/composer"
            if (strpos($path . '/', $pattern) === 0) {
                error_log("    ✅ DIRECTORY MATCH: $path starts with $pattern");
                return true;
            }
        }
        
        // 3. Soubor končící na ~
        if ($pattern === '~' && substr($path, -1) === '~') {
            error_log("    ✅ BACKUP FILE MATCH: $path ends with ~");
            return true;
        }
        
        // 4. Přesný název souboru
        if (basename($path) === $pattern) {
            error_log("    ✅ FILENAME MATCH: basename($path) == $pattern");
            return true;
        }
        
        error_log("    ❌ NO MATCH for pattern '$pattern'");
    }
    
    error_log("=== NO IGNORE PATTERN MATCHED ===");
    return false;
}}