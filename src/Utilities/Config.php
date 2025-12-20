<?php
// src/Utilities/Config.php
declare(strict_types=1);

namespace Scanner\Utilities;

class Config
{
    private static ?array $config = null;
    
    /**
     * Načte celou konfiguraci
     */
    public static function load(): array
    {
        if (self::$config === null) {
            $scannerRoot = realpath(__DIR__ . '/../..') ?: '';
            self::$config = require $scannerRoot . '/config/app.php';
        }
        return self::$config;
    }
    
    /**
     * Vrátí hodnotu z konfigurace
     */
    public static function get(string $key, $default = null)
    {
        $config = self::load();
        
        // Podpora pro dotazování jako 'paths.projects_root'
        if (strpos($key, '.') !== false) {
            $keys = explode('.', $key);
            $value = $config;
            
            foreach ($keys as $k) {
                if (!is_array($value) || !array_key_exists($k, $value)) {
                    return $default;
                }
                $value = $value[$k];
            }
            return $value;
        }
        
        return $config[$key] ?? $default;
    }
    
    /**
     * Získá cestu ke scanneru
     */
    public static function getScannerRoot(): string
    {
        return self::get('paths.scanner_root') ?: realpath(__DIR__ . '/../..') ?: '';
    }
    
    /**
     * Získá cestu k projektům (htdocs)
     */
    public static function getProjectsDir(): string
    {
        return self::get('paths.projects_root') ?: dirname(self::getScannerRoot());
    }
    
    /**
     * Získá cestu k public složce
     */
    public static function getPublicDir(): string
    {
        return self::get('paths.public') ?: self::getScannerRoot() . '/public';
    }
}