<?php
// scanner/src/Services/ProjectScanner.php
/**
 * Service pro skenování projektů
 *
 * @package Scanner\Services
 * @author KRS3
 * @version 2.0
 */

declare(strict_types=1);

namespace Scanner\Services;

class ProjectScanner
{
    private array $config;

    /**
     * Constructor
     *
     * @param array $config Konfigurace aplikace
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Získá seznam projektů v kořenovém adresáři
     *
     * @return array Seznam názvů projektových adresářů
     * @throws RuntimeException Pokud nelze načíst adresář
     */
    public function getProjects(): array
    {
        $rootPath = $this->config['paths']['projects_root'];

		 // DEBUG: kam se díváme?
    //echo "🔍 Skenuji adresář: " . $rootPath . "<br>";
    //echo "🔍 Adresář existuje: " . (is_dir($rootPath) ? 'ANO' : 'NE') . "<br>";

        $projects = [];
        $items = @scandir($rootPath);

        if ($items === false) {
            throw new RuntimeException("Nelze načíst adresář: $rootPath");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $rootPath . '/' . $item;

            // Ignorovat scanner adresář
            //if ($item === 'scanner') continue;

            if (is_dir($fullPath)) {
                $projects[] = $item;
            }
        }
        return $projects;
    }

    /**
     * Rekurzivně proskenuje projektový adresář a vrátí strukturu
     *
     * @param string $path Cesta k adresáři pro skenování
     * @param string $prefix Prefix pro stromové zobrazení
     * @return array Stromová struktura projektu
     * @throws RuntimeException Pokud nelze načíst adresář
     */
    public function scanProject(string $path, string $prefix = ''): array
    {
        $output = [];
        $items = @scandir($path);

        if ($items === false) {
            throw new RuntimeException("Nelze načíst adresář: $path");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $path . '/' . $item;

            // Přeskočit ignorované patterny
            if ($this->shouldIgnore($fullPath)) continue;

            if (is_dir($fullPath)) {
                $output[] = $prefix . '📁 ' . $item . '/';
                $output = array_merge($output, $this->scanProject($fullPath, $prefix . '│   '));
            } else {
                $fileSize = filesize($fullPath);
                $sizeInfo = $fileSize > 0 ? ' (' . $this->formatFileSize($fileSize) . ')' : '';
                $output[] = $prefix . '📄 ' . $item . $sizeInfo;
            }
        }
        return $output;
    }

    /**
     * Zkontroluje existenci důležitých souborů v projektu
     *
     * @param string $projectPath Kořenová cesta projektu
     * @return array Výsledky s informací o existenci souborů
     */
    public function checkImportantFiles(string $projectPath): array
    {
        $results = [];
        $importantFiles = $this->config['important_files'] ?? [];

        foreach ($importantFiles as $file) {
            $fullPath = $projectPath . '/' . $file;
            $results[$file] = file_exists($fullPath);
        }
        return $results;
    }

    /**
     * Zkontroluje zda má být cesta ignorována na základě patternů
     *
     * @param string $path Cesta k souboru/adresáři pro kontrolu
     * @return bool True pokud má být cesta ignorována
     */
    private function shouldIgnore(string $path): bool
    {
        $ignorePatterns = $this->config['ignore_patterns'] ?? [];

        foreach ($ignorePatterns as $pattern) {
            if (strpos($path, $pattern) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Formátuje velikost souboru do čitelného formátu
     *
     * @param int $bytes Velikost souboru v bytech
     * @return string Naformátovaná velikost souboru
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
}
?>