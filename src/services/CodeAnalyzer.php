<?php
// scanner/src/Services/CodeAnalyzer.php

/**
 * Analyzátor kódu pro kontrolu kvality
 *
 * @package Scanner\Services
 * @author KRS3
 * @version 2.0
 */

declare(strict_types=1);

namespace Scanner\Services;

class CodeAnalyzer
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
     * Analyzuje PHP soubory a kontroluje kvalitu kódu
     *
     * @param string $projectPath Cesta k projektu
     * @param array $aiRules Pravidla pro analýzu
     * @return array Výsledky analýzy
     */
    public function analyzeCodeQuality(string $projectPath, array $aiRules): array
    {
        $analysis = [
            'soubory_s_chybami' => [],
            'soubory_bez_phpdoc' => [],
            'soubory_bez_loggeru' => [],
            'celkem_souboru' => 0,
            'celkem_radku' => 0
        ];

        $phpFiles = $this->findPhpFiles($projectPath);

        foreach ($phpFiles as $phpFile) {
            $analysis['celkem_souboru']++;
            $fileAnalysis = $this->analyzePhpFile($phpFile, $aiRules);
            $analysis['celkem_radku'] += $fileAnalysis['radku'];

            if (!$fileAnalysis['ma_phpdoc']) {
                $analysis['soubory_bez_phpdoc'][] = $phpFile;
            }

            if (!$fileAnalysis['ma_logger'] && $this->shouldHaveLogger($phpFile)) {
                $analysis['soubory_bez_loggeru'][] = $phpFile;
            }

            if (!empty($fileAnalysis['chyby'])) {
                $analysis['soubory_s_chybami'][$phpFile] = $fileAnalysis['chyby'];
            }
        }

        return $analysis;
    }

    /**
     * Najde všechny PHP soubory v projektu
     *
     * @param string $path Cesta k projektu
     * @return array Seznam PHP souborů
     */
private function findPhpFiles(string $path): array
{
    $phpFiles = [];

    // ✅ VŠECHNY PHP BUILT-IN TŘÍDY S \\ NA ZAČÁTKU
    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }

    return $phpFiles;
}

    /**
     * Analyzuje jednotlivý PHP soubor
     *
     * @param string $filePath Cesta k PHP souboru
     * @param array $aiRules Pravidla pro analýzu
     * @return array Výsledky analýzy souboru
     */
    private function analyzePhpFile(string $filePath, array $aiRules): array
    {
        $content = file_get_contents($filePath);
        $analysis = [
            'ma_phpdoc' => false,
            'ma_logger' => false,
            'ma_strict_types' => false,
            'radku' => count(file($filePath)),
            'chyby' => []
        ];

        // Kontrola PHP Doc
        if (preg_match('/\/\*\*[\s\S]*?\*\//', $content)) {
            $analysis['ma_phpdoc'] = true;
        }

        // Kontrola strict_types
        if (strpos($content, "declare(strict_types=1)") !== false) {
            $analysis['ma_strict_types'] = true;
        }

        // Kontrola loggeru
        if (strpos($content, "Logger::") !== false ||
            strpos($content, "use.*Logger") !== false ||
            strpos($content, "\\Logger") !== false) {
            $analysis['ma_logger'] = true;
        }

        // Kontrola základní syntaxe


		if ($this->config['system']['check_syntax'] ?? true) {
	        $output = [];
	        $returnCode = 0;

	        // Použij plnou cestu k PHP na Windows
	        $phpBinary = $this->config['system']['php_binary'] ?? 'C:\\xampp\\php\\php.exe';
	        $command = '"' . $phpBinary . '" -l ' . escapeshellarg($filePath) . ' 2>&1';

	        exec($command, $output, $returnCode);

	        if ($returnCode !== 0) {
	            $analysis['chyby'][] = implode(" ", $output);
	        }
	    }

        return $analysis;
    }

    /**
     * Určí zda by soubor měl mít logger
     *
     * @param string $filePath Cesta k souboru
     * @return bool True pokud by měl mít logger
     */
    private function shouldHaveLogger(string $filePath): bool
    {
        $patterns = [
            '/app\/Services\//',
            '/app\/Controllers\//',
            '/app\/Auth\//',
            '/Controller\.php$/',
            '/Service\.php$/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $filePath)) {
                return true;
            }
        }

        return false;
    }
}
?>