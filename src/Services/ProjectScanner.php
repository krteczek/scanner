<?php
// scanner/src/Services/ProjectScanner.php

/**
 * Service pro skenování struktury projektů s metadaty souborů
 * Zajišťuje rekurzivní průchod adresářovou strukturou
 * Generuje stromové zobrazení s informacemi o souborech
 * Poskytuje kontrolu důležitých souborů a ignorování systémových cest
 *
 * @package Scanner\Services
 * @author KRS3
 * @version 2.1
 */

declare(strict_types=1);

namespace Scanner\Services;

use RuntimeException;

class ProjectScanner
{
    /** @var array Konfigurace aplikace s cestami a pravidly */
    private array $config;

    /**
     * Inicializuje projektový scanner s konfigurací
     * Připravuje scanner pro práci s definovanými cestami a pravidly
     *
     * @param array $config Konfigurace aplikace
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Získá seznam projektů v kořenovém adresáři projektů
     * Prohledává definovaný kořenový adresář a vrací seznam podadresářů
     *
     * @return array Seznam názvů projektových adresářů
     * @throws RuntimeException Pokud nelze načíst nebo přistupovat ke kořenovému adresáři
     */
    public function getProjects(): array
    {
        $rootPath = $this->config['paths']['projects_root'];
        $projects = [];
        $items = @scandir($rootPath);

        if ($items === false) {
            throw new RuntimeException("Nelze načíst adresář: $rootPath");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $rootPath . '/' . $item;

            if (is_dir($fullPath)) {
                $projects[] = $item;
            }
        }
        return $projects;
    }

    /**
     * Rekurzivně proskenuje projektový adresář a vrátí strukturu s metadaty
     * Prochází všechny soubory a adresáře, aplikuje ignore pravidla
     * Vrací kompletní strukturu s metadaty pro každou položku
     *
     * @param string $path Cesta k adresáři pro skenování
     * @param string $prefix Prefix pro stromové zobrazení (pro rekurzi)
     * @return array Stromová struktura projektu s metadaty
     * @throws RuntimeException Pokud nelze načíst adresář
     */
    public function scanProjectWithMetadata(string $path, string $prefix = ''): array
    {
        $output = [];
        $items = @scandir($path);

        if ($items === false) {
            throw new RuntimeException("Nelze načíst adresář: $path");
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $fullPath = $path . '/' . $item;

            if ($this->shouldIgnore($fullPath, $item)) continue;

            $metadata = $this->getFileMetadata($fullPath);
            
            if (is_dir($fullPath)) {
                $output[] = [
                    'type' => 'directory',
                    'display' => $prefix . '📁 ' . $item . '/',
                    'metadata' => $metadata
                ];
                $output = array_merge($output, $this->scanProjectWithMetadata($fullPath, $prefix . '│   '));
            } else {
                $sizeInfo = $metadata['size_bytes'] > 0 ? ' (' . $metadata['size'] . ')' : '';
                $output[] = [
                    'type' => 'file', 
                    'display' => $prefix . '📄 ' . $item . $sizeInfo,
                    'metadata' => $metadata
                ];
            }
        }
        return $output;
    }

    /**
     * Získá metadata o souboru nebo adresáři
     * Shromažďuje informace o velikosti, čase modifikace, počtu řádků a typech
     * Pro PHP soubory navíc počítá řádky kódu
     *
     * @param string $filePath Cesta k souboru nebo adresáři
     * @return array Metadata souboru obsahující name, path, size, lines, modified, type, extension, has_php
     */
    public function getFileMetadata(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $stats = stat($filePath);
        $lines = 0;
        
        if (is_file($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
            $lines = count(file($filePath));
        }
        
        return [
            'name' => basename($filePath),
            'path' => $filePath,
            'size' => $this->formatFileSize($stats['size']),
            'size_bytes' => $stats['size'],
            'lines' => $lines,
            'modified' => date('Y-m-d H:i:s', $stats['mtime']),
            'type' => is_dir($filePath) ? 'directory' : 'file',
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION),
            'has_php' => (pathinfo($filePath, PATHINFO_EXTENSION) === 'php')
        ];
    }

    /**
     * Původní metoda pro kompatibilitu - scanuje projekt a vrací strukturu
     * Zachovává původní rozhraní vracením pouze zobrazených řetězců
     *
     * @param string $path Cesta k adresáři pro skenování
     * @param string $prefix Prefix pro stromové zobrazení
     * @return array Pole zobrazených řetězců struktury projektu
     */
    public function scanProject(string $path, string $prefix = ''): array
    {
        $structureWithMetadata = $this->scanProjectWithMetadata($path, $prefix);
        return array_column($structureWithMetadata, 'display');
    }

    /**
     * Zkontroluje existenci důležitých souborů v projektu
     * Porovnává seznam důležitých souborů z konfigurace s aktuálním stavem
     *
     * @param string $projectPath Kořenová cesta projektu
     * @return array Asociativní pole [název_souboru => existuje]
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
     * Zkontroluje zda má být cesta ignorována na základě patternů z konfigurace
     * Používá se pro vynechání systémových adresářů a záložních souborů
     * Podporuje patterny pro celé cesty i konkrétní soubory
     *
     * @param string $path Cesta k souboru/adresáři pro kontrolu
     * @param string $itemName Název souboru/adresáře (pro kontrolu koncovek)
     * @return bool True pokud má být cesta ignorována
     */
    private function shouldIgnore(string $path, string $itemName): bool
    {
        $ignorePatterns = $this->config['ignore_patterns'] ?? [];

        // Kontrola patternů z konfigurace
        foreach ($ignorePatterns as $pattern) {
            // Pro adresáře - hledáme pattern v celé cestě
            if (strpos($path, $pattern) !== false) {
                return true;
            }
            
            // Pro soubory - kontrola koncovek
            if (is_file($path)) {
                // Pattern je jen znak (např. '~') - kontrola konce názvu souboru
                if (strlen($pattern) === 1 && substr($itemName, -1) === $pattern) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * Formátuje velikost souboru do čitelného formátu s jednotkami
     * Automaticky vybírá vhodnou jednotku (B, KB, MB) podle velikosti
     *
     * @param int $bytes Velikost souboru v bytech
     * @return string Naformátovaná velikost souboru s jednotkou
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
     * Získá relativní cestu vůči základní cestě
     * Odstraní základní cestu z absolutní cesty pro zjednodušení zobrazení
     *
     * @param string $absolutePath Absolutní cesta k souboru/adresáři
     * @param string $basePath Základní cesta pro relativní výpočet
     * @return string Relativní cesta vzhledem k základní cestě
     */
    public function getRelativePath(string $absolutePath, string $basePath): string
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_replace($basePath, '', $absolutePath);
    }
}
?>