<?php
// scanner/src/Services/CodeAnalyzer.php

/**
 * Hlavní analyzátor kódu pro kontrolu kvality PHP projektů
 * Poskytuje detailní analýzu PHP Doc, namespaces, loggerů a syntaxe
 * S generováním strukturovaných reportů s návrhy na opravy
 * 
 * @package Scanner\Services
 * @author KRS3
 * @version 2.1
 */

declare(strict_types=1);

namespace Scanner\Services;

use RuntimeException;

class CodeAnalyzer
{
    private array $config;
    
    /**
     * Definice typů problémů s jejich závažností a návody na opravu
     * 
     * @var array<string, array{
     *   description: string,
     *   severity: 'critical'|'error'|'warning'|'info',
     *   suggestion: string,
     *   example: string
     * }>
     */
    private const PROBLEM_DEFINITIONS = [
        'missing_phpdoc_class' => [
            'description' => 'Třída nemá PHP Doc komentář',
            'severity' => 'warning',
            'suggestion' => 'Přidej /** ... */ dokumentační komentář před definici třídy',
            'example' => '/**\n * Popis třídy\n * @author ...\n */\nclass ClassName'
        ],
        'missing_phpdoc_method' => [
            'description' => 'Metoda nemá PHP Doc komentář', 
            'severity' => 'warning',
            'suggestion' => 'Dokumentuj metodu pomocí /** ... */ před definicí metody',
            'example' => '/**\n * Popis metody\n * @param string $param Popis parametru\n * @return bool Návratová hodnota\n */'
        ],
        'missing_logger' => [
            'description' => 'Soubor nemá logger přestože by měl',
            'severity' => 'warning', 
            'suggestion' => 'Přidej use ...\\Logger; a používej $this->logger->info() pro důležité operace',
            'example' => 'use App\\Logger\\Logger;\n\nclass Service {\n    private Logger $logger;\n    \n    public function action() {\n        $this->logger->info(\'Action executed\');\n    }\n}'
        ],
        'no_namespace' => [
            'description' => 'Soubor nemá namespace přestože by měl',
            'severity' => 'error',
            'suggestion' => 'Přidej namespace podle struktury adresáře (PSR-4)',
            'example' => 'namespace App\\Controllers;\n\nclass UserController'
        ],
        'no_strict_types' => [
            'description' => 'Chybí declare(strict_types=1)',
            'severity' => 'error',
            'suggestion' => 'Přidej declare(strict_types=1); na začátek souboru za <?php',
            'example' => '<?php\ndeclare(strict_types=1);'
        ],
        'syntax_error' => [
            'description' => 'Syntax error v PHP kódu',
            'severity' => 'critical',
            'suggestion' => 'Oprav syntaxi podle chybové hlášky PHP',
            'example' => ''
        ]
    ];
    
    /**
     * Inicializuje analyzátor s konfigurací
     *
     * @param array $config Konfigurace aplikace
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Hlavní akce analyzátoru - spustí analýzu kódu
     * Alias pro analyzeCodeQuality pro zachování kompatibility
     *
     * @param string $projectPath Cesta k projektu
     * @param array $rules Pravidla pro analýzu
     * @return array Výsledky analýzy
     */
    public function action(string $projectPath, array $rules): array
    {
        return $this->analyzeCodeQuality($projectPath, $rules);
    }

    /**
     * Analyzuje PHP soubory projektu s detailním reportingem problémů
     * Prochází všechny PHP soubory a kontroluje kvalitu kódu
     *
     * @param string $projectPath Cesta k projektu
     * @param array $aiRules Pravidla pro analýzu
     * @return array Výsledky analýzy s detailními problémy
     */
    public function analyzeCodeQuality(string $projectPath, array $aiRules): array
    {
        $analysis = [
            'soubory_s_problemy' => [],
            'soubory_bez_phpdoc' => [],
            'soubory_bez_loggeru' => [], 
            'soubory_bez_namespaces' => [],
            'celkem_souboru' => 0,
            'celkem_radku' => 0,
            'problemy_podle_zavaznosti' => [
                'critical' => [],
                'error' => [],
                'warning' => [],
                'info' => []
            ]
        ];

        $phpFiles = $this->findPhpFiles($projectPath);

        foreach ($phpFiles as $phpFile) {
            $analysis['celkem_souboru']++;
            $fileAnalysis = $this->analyzePhpFile($phpFile, $aiRules);
            $analysis['celkem_radku'] += $fileAnalysis['radku'];

            if (!empty($fileAnalysis['problemy'])) {
                $analysis['soubory_s_problemy'][$phpFile] = $fileAnalysis['problemy'];
                
                foreach ($fileAnalysis['problemy'] as $problem) {
                    $analysis['problemy_podle_zavaznosti'][$problem['severity']][] = $problem;
                }
            }

            if (!$fileAnalysis['ma_phpdoc']) {
                $analysis['soubory_bez_phpdoc'][] = $phpFile;
            }

            if (!$fileAnalysis['ma_logger'] && $this->shouldHaveLogger($phpFile)) {
                $analysis['soubory_bez_loggeru'][] = $phpFile;
            }

            if (!$fileAnalysis['ma_namespace'] && $this->shouldHaveNamespace($phpFile)) {
                $analysis['soubory_bez_namespaces'][] = $phpFile;
            }
        }

        return $analysis;
    }

    /**
     * Analyzuje jednotlivý PHP soubor s detailními problémy
     * Kontroluje PHP Doc, namespaces, logger, strict types a syntax
     *
     * @param string $filePath Cesta k PHP souboru
     * @param array $aiRules Pravidla pro analýzu
     * @return array Výsledky analýzy souboru s problémy
     */
    private function analyzePhpFile(string $filePath, array $aiRules): array
    {
        $content = file_get_contents($filePath);
        $analysis = [
            'ma_phpdoc' => false,
            'ma_logger' => false, 
            'ma_strict_types' => false,
            'ma_namespace' => false,
            'radku' => count(file($filePath)),
            'problemy' => [],
            'chyby' => []
        ];

        $phpdocCheck = $this->checkPhpDocWithDetails($content, $filePath);
        $analysis['ma_phpdoc'] = $phpdocCheck['has_phpdoc'];
        $analysis['problemy'] = array_merge($analysis['problemy'], $phpdocCheck['problems']);

        if (!str_contains($content, "declare(strict_types=1)")) {
            $analysis['problemy'][] = $this->createProblem(
                'no_strict_types', 
                $filePath,
                'Chybí strict types declaration'
            );
        } else {
            $analysis['ma_strict_types'] = true;
        }

        $namespaceCheck = $this->checkNamespacesWithDetails($content, $filePath);
        $analysis['ma_namespace'] = $namespaceCheck['has_namespace'];
        $analysis['problemy'] = array_merge($analysis['problemy'], $namespaceCheck['problems']);

        $loggerCheck = $this->checkLoggerWithDetails($content, $filePath);
        $analysis['ma_logger'] = $loggerCheck['has_logger'];
        $analysis['problemy'] = array_merge($analysis['problemy'], $loggerCheck['problems']);

        if ($this->config['system']['check_syntax'] ?? true) {
            $syntaxProblems = $this->checkSyntaxWithDetails($filePath);
            $analysis['problemy'] = array_merge($analysis['problemy'], $syntaxProblems);
        }

        return $analysis;
    }

    /**
     * Kontroluje PHP Doc komentáře s detailním problém reportingem
     * Hledá chybějící dokumentaci pro třídy a metody
     *
     * @param string $content Obsah souboru
     * @param string $filePath Cesta k souboru
     * @return array Výsledky kontroly PHP Doc
     */
    private function checkPhpDocWithDetails(string $content, string $filePath): array
    {
        $result = ['has_phpdoc' => false, 'problems' => []];
        
        if (preg_match('/class\s+(\w+)/', $content, $classMatches)) {
            $className = $classMatches[1];
            $classDocPattern = '/\/\*\*[\s\S]*?\*\/\s*class\s+' . $className . '/';
            
            if (!preg_match($classDocPattern, $content)) {
                $result['problems'][] = $this->createProblem(
                    'missing_phpdoc_class',
                    $filePath,
                    "Třída {$className} nemá PHP Doc komentář"
                );
            } else {
                $result['has_phpdoc'] = true;
            }
        }
        
        if (preg_match_all('/function\s+(\w+)\s*\(/m', $content, $methodMatches)) {
            foreach ($methodMatches[1] as $methodName) {
                if ($methodName === '__construct' || $methodName === '__destruct') {
                    continue;
                }
                
                $methodDocPattern = '/\/\*\*[\s\S]*?\*\/\s*function\s+' . $methodName . '\s*\(/';
                if (!preg_match($methodDocPattern, $content)) {
                    $result['problems'][] = $this->createProblem(
                        'missing_phpdoc_method',
                        $filePath, 
                        "Metoda {$methodName} nemá PHP Doc komentář"
                    );
                }
            }
        }
        
        return $result;
    }

    /**
     * Kontroluje namespaces s detailním problém reportingem
     * Validuje přítomnost namespaces podle PSR-4 standardů
     *
     * @param string $content Obsah souboru
     * @param string $filePath Cesta k souboru
     * @return array Výsledky kontroly namespaces
     */
    private function checkNamespacesWithDetails(string $content, string $filePath): array
    {
        $result = ['has_namespace' => false, 'problems' => []];
        
        $hasNamespace = preg_match('/^namespace\s+[a-zA-Z0-9_\\\\]+;/m', $content) === 1;
        $result['has_namespace'] = $hasNamespace;
        
        if (!$hasNamespace && $this->shouldHaveNamespace($filePath)) {
            $result['problems'][] = $this->createProblem(
                'no_namespace',
                $filePath,
                'Soubor by měl mít namespace podle PSR-4'
            );
        }
        
        return $result;
    }

    /**
     * Kontroluje přítomnost loggeru s detailním problém reportingem
     * Validuje použití loggeru v service a controller třídách
     *
     * @param string $content Obsah souboru
     * @param string $filePath Cesta k souboru
     * @return array Výsledky kontroly loggeru
     */
    private function checkLoggerWithDetails(string $content, string $filePath): array
    {
        $result = ['has_logger' => false, 'problems' => []];
        
        $hasLogger = strpos($content, "Logger::") !== false ||
                   preg_match('/use.*Logger/', $content) ||
                   strpos($content, "\\Logger") !== false;
        
        $result['has_logger'] = $hasLogger;
        
        if (!$hasLogger && $this->shouldHaveLogger($filePath)) {
            $result['problems'][] = $this->createProblem(
                'missing_logger',
                $filePath, 
                'Soubor by měl mít logger pro důležité operace'
            );
        }
        
        return $result;
    }

    /**
     * Kontroluje syntaxi PHP souboru s detailním problém reportingem
     * Používá PHP lint pro validaci syntaxe
     *
     * @param string $filePath Cesta k souboru
     * @return array Pole syntax problémů
     * @throws RuntimeException Pokud nelze najít PHP binárku
     */
    private function checkSyntaxWithDetails(string $filePath): array
    {
        $problems = [];
        
        try {
            $phpBinary = $this->getPhpBinary();
            $output = [];
            $returnCode = 0;

            $command = '"' . $phpBinary . '" -l ' . escapeshellarg($filePath) . ' 2>&1';
            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $problems[] = $this->createProblem(
                    'syntax_error',
                    $filePath,
                    'Syntax error: ' . implode(" ", $output)
                );
            }
        } catch (RuntimeException $e) {
            $problems[] = $this->createProblem(
                'syntax_error',
                $filePath,
                'Chyba PHP binárky: ' . $e->getMessage()
            );
        }
        
        return $problems;
    }

    /**
     * Vytvoří strukturovaný problém pro reporting
     * Používá definice z PROBLEM_DEFINITIONS pro konzistentní zprávy
     *
     * @param string $type Typ problému
     * @param string $filePath Cesta k souboru
     * @param string $customMessage Vlastní zpráva
     * @return array Strukturovaný problém
     */
    private function createProblem(string $type, string $filePath, string $customMessage = ''): array
    {
        $definition = self::PROBLEM_DEFINITIONS[$type] ?? [
            'description' => 'Neznámý problém',
            'severity' => 'warning',
            'suggestion' => 'Zkontroluj soubor',
            'example' => ''
        ];
        
        return [
            'type' => $type,
            'file' => $filePath,
            'filename' => basename($filePath),
            'description' => $customMessage ?: $definition['description'],
            'severity' => $definition['severity'],
            'suggestion' => $definition['suggestion'],
            'example' => $definition['example'] ?? ''
        ];
    }

    /**
     * Inteligentně detekuje PHP binárku pro aktuální systém
     * Prioritizuje systémové PHP, pak konfiguraci, nakonec automatickou detekci
     *
     * @return string Cesta k PHP binárce
     * @throws RuntimeException Pokud nelze najít platnou PHP binárku
     */
    public function getPhpBinary(): string
    {
        $os = strtoupper(substr(PHP_OS, 0, 3));
        
        if ($os !== 'WIN') {
            $systemPhp = shell_exec('which php 2>/dev/null');
            if ($systemPhp && $this->isValidPhpBinary(trim($systemPhp))) {
                return trim($systemPhp);
            }
        }
        
        $config = $this->config['system'] ?? [];
        
        if ($os === 'WIN') {
            $windowsPath = $config['windows_php_path'] ?? 'C:\\xampp\\php\\php.exe';
            if ($this->isValidPhpBinary($windowsPath)) {
                return $windowsPath;
            }
        } else {
            $linuxPath = $config['linux_php_path'] ?? '/opt/lampp/bin/php';
            if ($this->isValidPhpBinary($linuxPath)) {
                return $linuxPath;
            }
            
            $macPath = $config['mac_php_path'] ?? '/usr/bin/php';
            if ($this->isValidPhpBinary($macPath)) {
                return $macPath;
            }
        }
        
        return $this->detectPhpBinary();
    }

    /**
     * Detekuje PHP binárku procházením všech možných cest
     * Prohledává standardní instalační cesty na různých OS
     *
     * @return string Cesta k PHP binárce
     * @throws RuntimeException Pokud nelze najít PHP
     */
    private function detectPhpBinary(): string
    {
        $possible_paths = [
            '/opt/lampp/bin/php',
            '/usr/bin/php',
            '/usr/local/bin/php',
            'php',
            'C:\\xampp\\php\\php.exe',
            'C:\\Program Files\\xampp\\php\\php.exe',
            'php.exe'
        ];
        
        foreach ($possible_paths as $path) {
            if ($this->isValidPhpBinary($path)) {
                return $path;
            }
        }
        
        throw new RuntimeException('Nelze najít PHP binárku! Zkontrolujte konfiguraci nebo nainstalujte PHP.');
    }

    /**
     * Ověří zda je PHP binárka platná a spustitelná
     * Kontroluje existenci souboru a spustitelnost příkazem php -v
     *
     * @param string $path Cesta k PHP binárce
     * @return bool True pokud je binárka platná
     */
    private function isValidPhpBinary(string $path): bool
    {
        if (strpos($path, '/') === false && strpos($path, '\\') === false) {
            $output = [];
            $returnCode = 0;
            exec("$path -v 2>/dev/null", $output, $returnCode);
            return $returnCode === 0;
        }
        
        return file_exists($path) && is_executable($path);
    }

    /**
     * Najde všechny PHP soubory v projektu pomocí rekurzivního iteratoru
     * Prochází všechny adresáře a vrací kompletní seznam PHP souborů
     *
     * @param string $path Cesta k projektu
     * @return array Seznam PHP souborů
     */
    private function findPhpFiles(string $path): array
    {
        $phpFiles = [];

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
     * Určí zda by soubor měl mít namespace podle PSR-4
     * Soubory v src/, app/ a třídy by měly mít namespace
     * Konfigurační soubory a vstupní body mohou být bez namespace
     *
     * @param string $filePath Cesta k souboru
     * @return bool True pokud by měl mít namespace
     */
    private function shouldHaveNamespace(string $filePath): bool
    {
        $shouldHavePatterns = [
            '/src\//',
            '/app\//',
            '/Controller\.php$/',
            '/Service\.php$/',
            '/Model\.php$/'
        ];

        $canBeWithoutPatterns = [
            '/index\.php$/',
            '/autoloader\.php$/',
            '/autoload\.php$/',
            '/config\//',
            '/public\//'
        ];

        foreach ($canBeWithoutPatterns as $pattern) {
            if (preg_match($pattern, $filePath)) {
                return false;
            }
        }

        foreach ($shouldHavePatterns as $pattern) {
            if (preg_match($pattern, $filePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Určí zda by soubor měl mít logger
     * Service třídy, Controllers a Auth soubory by měly mít logger
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