<?php
// scanner/src/Utilities/FileHelper.php

/**
 * Pomocné utility pro práci se soubory a adresáři
 * Poskytuje metody pro kontrolu přístupnosti, relativní cesty a informací o souborech
 *
 * @package Scanner\Utilities
 * @author Petr
 * @version 2.0
 */
declare(strict_types=1);

namespace Scanner\Utilities;

class FileHelper
{
    /**
     * Zkontroluje zda soubor existuje a je čitelný
     *
     * @param string $filePath Cesta k souboru
     * @return bool True pokud je soubor přístupný
     */
    public static function isFileAccessible(string $filePath): bool
    {
        return file_exists($filePath) && is_readable($filePath);
    }

    /**
     * Získá relativní cestu vzhledem k základní cestě
     *
     * @param string $path Absolutní cesta
     * @param string $basePath Základní cesta
     * @return string Relativní cesta
     */
    public static function getRelativePath(string $path, string $basePath): string
    {
        return str_replace($basePath . '/', '', $path);
    }

    /**
     * Vytvoří adresář pokud neexistuje
     *
     * @param string $directory Cesta k adresáři
     * @param int $permissions Práva adresáře
     * @return bool True pokud byl adresář vytvořen nebo již existuje
     */
    public static function createDirectory(string $directory, int $permissions = 0755): bool
    {
        if (!is_dir($directory)) {
            return mkdir($directory, $permissions, true);
        }
        return true;
    }

/**
     * Vrátí seznam podadresářů v daném adresáři
     * 
     * @param string $directory Cesta k adresáři
     * @return array<string> Seznam názvů podadresářů
     */
    public static function getDirectories(string $directory): array
    {
        if (!is_dir($directory) || !is_readable($directory)) {
            return [];
        }
        
        $items = scandir($directory);
        if ($items === false) {
            return [];
        }
        
        $directories = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $fullPath = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                $directories[] = $item;
            }
        }
        
        sort($directories);
        return $directories;
    }
    
    /**
     * Získá informace o souboru pro zobrazení
     *
     * @param string $filePath Cesta k souboru
     * @return array Informace o souboru
     */
    public static function getFileInfo(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return ['error' => 'Soubor neexistuje'];
        }

        return [
            'size' => filesize($filePath),
            'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
            'permissions' => substr(sprintf('%o', fileperms($filePath)), -4),
            'extension' => pathinfo($filePath, PATHINFO_EXTENSION)
        ];
    }
}
?>