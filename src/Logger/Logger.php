<?php
// /src/Logger/Logger.php

namespace App\Logger;

use App\Core\Config;

/**
 * Třída pro logování zpráv na obrazovku a do souboru s podporou úrovní logování a rotace
 *        $logger = Logger::getInstance();
 *         1. Denní rotace:
 *                $logger->setRotation('daily', 0, 7); // Denní rotace, uchovávat 7 souborů
 *                // Soubory: app-2025-10-09.log, app-2025-10-10.log, atd.
 *
 *        2. Hodinová rotace:
 *                $logger->setRotation('hourly', 0, 24); // Hodinová rotace, uchovávat 24 souborů
 *                // Soubory: app-2025-10-09-14.log, app-2025-10-09-15.log, atd.
 *
 *        3. Rotace podle velikosti:
 *                 $logger->setRotation('size', 5242880, 10); // Rotace při 5MB, uchovávat 10 souborů
 *                 //Soubory: app-2025-10-09-143025.log, app-2025-10-09-143126.log, atd.
 *
 *        4. Bez rotace:
 *                $logger->setRotation('none'); // Žádná rotace - všechno v jednom souboru
 *
 *        5. $logger->setLogLevel('ERROR'); // Pouze chyby a výjimky
 *
 *        6. $logger->setLogLevel('DEBUG'); // Všechny zprávy
 *
 *        7. $logger->setLogLevel('INFO'); // Info, warningy, chyby a výjimky
 *
 * @package App\Logger
 * @author KRS3
 * @version 2.0
 */
class Logger
{
    /** @var self|null Singleton instance třídy Logger */
    private static ?self $instance = null;

    /** @var string Cesta k souboru pro logování */
    private string $currentLogFile;

    /** @var string Základní název log souboru bez přípony */
    private string $baseLogFile;

    /** @var string Základní adresář pro logy */
    private string $baseLogDir;

    /** @var bool Určuje, zda se logy vypisují na obrazovku */
    private bool $echoOutput;

    /** @var bool Určuje, zda se logy zapisují do souboru */
    private bool $fileOutput;

    /** @var int Aktuální úroveň logování */
    private int $logLevel;

    /** @var string Typ rotace logů */
    private string $rotation;

    /** @var int Maximální velikost souboru v bytech pro rotaci podle velikosti */
    private int $maxSize;

    /** @var int Maximální počet souborů pro rotaci */
    private int $maxFiles;

    /** @var array Cache pro seznam souborů (optimalizace) */
    private array $filesCache = [];

    /** @var int Timestamp poslední kontroly cache */
    private int $cacheTime = 0;

    /** @var int Doba platnosti cache v sekundách */
    private const CACHE_TTL = 60;

    /** @var array Mapování úrovní logování na číselné hodnoty */
    private const LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4,
        'EXCEPTION' => 5,
        'NONE' => 999
    ];

    /** @var array Povolené typy rotace */
    private const ALLOWED_ROTATIONS = ['none', 'daily', 'hourly', 'size'];

    /** @var array Výchozí konfigurace */
    private const DEFAULT_CONFIG = [
        'level' => 'INFO',
        'echo' => true,
        'file' => true,
        'rotation' => 'none',
        'max_size' => 10485760, // 10MB
        'max_files' => 30,
        'file_path' => null
    ];

    /**
     * Konstruktor třídy Logger
     *
     * @param array $config Konfigurace loggeru
     * @throws \InvalidArgumentException Pokud je konfigurace neplatná
     */
    public function __construct(array $config = [])
    {
        $config = array_merge(self::DEFAULT_CONFIG, $config);

        // Nastavení úrovně logování
        $this->logLevel = self::LEVELS[strtoupper($config['level'])] ?? self::LEVELS['INFO'];

        // Nastavení výstupů
        $this->echoOutput = (bool) $config['echo'];
        $this->fileOutput = (bool) $config['file'];

        // Validace a nastavení rotace
        $this->setRotationInternal($config['rotation'], (int) $config['max_size'], (int) $config['max_files']);

        // Nastavení cesty k souboru
        if (!empty($config['file_path'])) {
            $this->setLogFileInternal($config['file_path']);
        } else {
            $logDir = Config::logs('dir', '');
            $logFile = Config::logs('file', '') ?: 'app.log';
            $this->setLogFileInternal($logDir . $logFile);
        }

        // Inicializace aktuálního log souboru
        $this->currentLogFile = $this->generateLogFileName();

        // Zajistíme, že adresář pro logy existuje
        if ($this->fileOutput) {
            $this->ensureLogDirectory();
        }
    }

    /**
     * Vrátí singleton instanci třídy Logger
     *
     * @return self Instance třídy Logger
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            // Načtení konfigurace z configu, pokud je dostupná
            $config = [
                'level' => Config::logs('level', 'INFO'),
                'echo' => Config::logs('echo', true),
                'file' => Config::logs('file', true),
                'rotation' => Config::logs('rotation', 'none'),
                'max_size' => Config::logs('max_size', 10485760),
                'max_files' => Config::logs('max_files', 30),
                'file_path' => Config::logs('dir', '') . (Config::logs('file', '') ?: 'app.log')
            ];

            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Resetuje singleton instanci (hlavně pro unit testy)
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Interní metoda pro nastavení log souboru s validací
     *
     * @param string $logFile Cesta k souboru
     * @throws \InvalidArgumentException Pokud je cesta neplatná
     * @return void
     */
    private function setLogFileInternal(string $logFile): void
    {
        // Normalizace cesty
        $logFile = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $logFile);

        // Získání adresáře
        $logDir = dirname($logFile);

        // Pokud adresář neexistuje, zkusíme jej vytvořit pro validaci
        if (!is_dir($logDir)) {
            $testDir = $logDir;
        } else {
            $testDir = realpath($logDir);
        }

        // Validace základního log adresáře z configu
        $baseConfigDir = Config::logs('dir', '');
        if (!empty($baseConfigDir)) {
            $baseConfigDir = rtrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $baseConfigDir), DIRECTORY_SEPARATOR);

            // Kontrola, že cesta začíná povoleným adresářem (ochrana proti path traversal)
            $normalizedLogDir = rtrim($logDir, DIRECTORY_SEPARATOR);
            if (strpos($normalizedLogDir, $baseConfigDir) !== 0) {
                throw new \InvalidArgumentException(
                    "Log file must be within configured logs directory: {$baseConfigDir}"
                );
            }
        }

        // Kontrola nebezpečných znaků v cestě (bez lomítek, ty jsou validní)
        if (preg_match('/[<>"|?*]/', $logFile)) {
            throw new \InvalidArgumentException("Log file path contains invalid characters");
        }

        $this->baseLogFile = $logFile;
        $this->baseLogDir = $logDir;
    }

    /**
     * Interní metoda pro nastavení rotace
     *
     * @param string $rotation Typ rotace
     * @param int $maxSize Maximální velikost
     * @param int $maxFiles Maximální počet souborů
     * @throws \InvalidArgumentException Pokud je typ rotace neplatný
     * @return void
     */
    private function setRotationInternal(string $rotation, int $maxSize, int $maxFiles): void
    {
        if (!in_array($rotation, self::ALLOWED_ROTATIONS, true)) {
            throw new \InvalidArgumentException(
                "Invalid rotation type: {$rotation}. Allowed: " . implode(', ', self::ALLOWED_ROTATIONS)
            );
        }

        $this->rotation = $rotation;
        $this->maxSize = max(1024, $maxSize); // Minimálně 1KB
        $this->maxFiles = max(0, $maxFiles);
    }

    /**
     * Zajistí existenci adresáře pro logy
     *
     * @return bool True pokud adresář existuje nebo byl úspěšně vytvořen
     */
    private function ensureLogDirectory(): bool
    {
        $logDir = dirname($this->currentLogFile);

        if (is_dir($logDir)) {
            return true;
        }

        if (!@mkdir($logDir, 0755, true)) {
            $error = error_get_last();
            if ($this->echoOutput) {
                echo "LOG ERROR: Failed to create directory: {$logDir}" .
                     ($error ? " - " . $error['message'] : "") . PHP_EOL;
            }
            return false;
        }

        return true;
    }

    /**
     * Vygeneruje název log souboru podle nastavené rotace
     *
     * @return string Název souboru
     */
    private function generateLogFileName(): string
    {
        $pathInfo = pathinfo($this->baseLogFile);
        $dirname = $pathInfo['dirname'] ?? '';
        $filename = $pathInfo['filename'] ?? 'app';
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '.log';

        $suffix = '';
        switch ($this->rotation) {
            case 'daily':
                $suffix = '-' . date('Y-m-d');
                break;
            case 'hourly':
                $suffix = '-' . date('Y-m-d-H');
                break;
            case 'size':
            case 'none':
            default:
                // Pro rotaci podle velikosti a bez rotace používáme základní název
                break;
        }

        return $dirname . DIRECTORY_SEPARATOR . $filename . $suffix . $extension;
    }

    /**
     * Zkontroluje a provede rotaci log souborů pokud je potřeba
     *
     * @return void
     */
    private function checkRotation(): void
    {
        if (!$this->fileOutput || $this->rotation === 'none') {
            return;
        }

        // Kontrola časové rotace (daily, hourly)
        if (in_array($this->rotation, ['daily', 'hourly'], true)) {
            $newLogFile = $this->generateLogFileName();
            if ($newLogFile !== $this->currentLogFile) {
                $this->currentLogFile = $newLogFile;
                $this->ensureLogDirectory();
                // Vyčistíme staré soubory při změně časového intervalu
                $this->cleanOldFiles();
            }
        }

        // Kontrola rotace podle velikosti
        if ($this->rotation === 'size' && file_exists($this->currentLogFile)) {
            if (@filesize($this->currentLogFile) >= $this->maxSize) {
                $this->rotateBySize();
            }
        }
    }

    /**
     * Provede rotaci logů podle velikosti s ochranou proti race condition
     *
     * @return void
     */
    private function rotateBySize(): void
    {
        $pathInfo = pathinfo($this->baseLogFile);
        $dirname = $pathInfo['dirname'] ?? '';
        $filename = $pathInfo['filename'] ?? 'app';
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '.log';

        // Vytvoříme název archivního souboru s časovým razítkem a mikrosekundami
        $timestamp = date('Y-m-d-His') . '-' . substr(microtime(true), -6, 6);
        $archivedFile = $dirname . DIRECTORY_SEPARATOR . $filename . '-' . $timestamp . $extension;

        // Přesuneme aktuální soubor do archivu s exkluzivním zámkem
        if (file_exists($this->currentLogFile)) {
            // Pokusíme se získat zámek na soubor
            $fp = @fopen($this->currentLogFile, 'r+');
            if ($fp !== false) {
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    // Máme exkluzivní zámek, můžeme bezpečně přesunout
                    flock($fp, LOCK_UN);
                    fclose($fp);
                    @rename($this->currentLogFile, $archivedFile);
                } else {
                    // Nemůžeme získat zámek, jiný proces právě rotuje
                    fclose($fp);
                    return;
                }
            }
        }

        // Vyčistíme staré soubory
        $this->cleanOldFiles();
    }

    /**
     * Smaže staré log soubory podle nastavení max_files s cache optimalizací
     *
     * @return void
     */
    private function cleanOldFiles(): void
    {
        if ($this->maxFiles <= 0) {
            return;
        }

        $pathInfo = pathinfo($this->baseLogFile);
        $dirname = $pathInfo['dirname'] ?? '';
        $filename = $pathInfo['filename'] ?? 'app';
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '.log';

        // Použijeme cache pro seznam souborů
        $now = time();
        if (empty($this->filesCache) || ($now - $this->cacheTime) > self::CACHE_TTL) {
            // Najdeme všechny soubory, které odpovídají patternu
            $pattern = $dirname . DIRECTORY_SEPARATOR . $filename . '-*' . $extension;
            $files = glob($pattern);

            if (!$files) {
                $this->filesCache = [];
                $this->cacheTime = $now;
                return;
            }

            // Seřadíme soubory podle data modifikace (nejnovější první)
            usort($files, function($a, $b) {
                return @filemtime($b) - @filemtime($a);
            });

            $this->filesCache = $files;
            $this->cacheTime = $now;
        } else {
            $files = $this->filesCache;
        }

        // Smažeme přebytečné soubory
        if (count($files) > $this->maxFiles) {
            $filesToDelete = array_slice($files, $this->maxFiles);
            foreach ($filesToDelete as $fileToDelete) {
                if (is_file($fileToDelete)) {
                    @unlink($fileToDelete);
                }
            }
            // Invalidujeme cache
            $this->filesCache = [];
            $this->cacheTime = 0;
        }
    }

    /**
     * Zapíše zprávu do logu, pokud úroveň logování je dostatečně vysoká
     *
     * @param string $message Zpráva k zalogování
     * @param string $level Úroveň logování (DEBUG, INFO, WARNING, ERROR, CRITICAL, EXCEPTION)
     * @return void
     */
    public function log(string $message, string $level = "INFO"): void
    {
        $levelValue = self::LEVELS[strtoupper($level)] ?? self::LEVELS['INFO'];

        // Pokud je úroveň zprávy nižší než aktuální úroveň logování, ignorovat
        if ($levelValue < $this->logLevel) {
            return;
        }

        // BEZPEČNOST: Sanitizace zprávy proti log injection
        $message = $this->escapeMessage($message);

        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$level}: {$message}" . PHP_EOL;

        // Zápis na obrazovku
        if ($this->echoOutput) {
            echo $logEntry;
        }

        // Zápis do souboru
        if ($this->fileOutput) {
            $this->checkRotation();

            if (!$this->ensureLogDirectory()) {
                return;
            }

            try {
                // Pokusíme se zapsat s exkluzivním zámkem
                $result = @file_put_contents($this->currentLogFile, $logEntry, FILE_APPEND | LOCK_EX);

                if ($result === false) {
                    $error = error_get_last();
                    if ($this->echoOutput) {
                        echo "LOG ERROR: Cannot write to file {$this->currentLogFile}" .
                             ($error ? " - " . $error['message'] : "") . PHP_EOL;
                    }
                }
            } catch (\Exception $e) {
                if ($this->echoOutput) {
                    echo "LOG ERROR: Cannot write to file {$this->currentLogFile}: " . $e->getMessage() . PHP_EOL;
                }
            }
        }
    }

    /**
     * Zapíše ladící zprávu do logu
     *
     * @param string $message Ladící zpráva k zalogování
     * @return void
     */
    public function debug(string $message): void
    {
        $this->log($message, "DEBUG");
    }

    /**
     * Zapíše informační zprávu do logu
     *
     * @param string $message Informační zpráva k zalogování
     * @return void
     */
    public function info(string $message): void
    {
        $this->log($message, "INFO");
    }

    /**
     * Zapíše varovnou zprávu do logu
     *
     * @param string $message Varovná zpráva k zalogování
     * @return void
     */
    public function warning(string $message): void
    {
        $this->log($message, "WARNING");
    }

    /**
     * Zapíše chybovou zprávu do logu
     *
     * @param string $message Chybová zpráva k zalogování
     * @return void
     */
    public function error(string $message): void
    {
        $this->log($message, "ERROR");
    }

    /**
     * Zapíše kritickou zprávu do logu
     *
     * @param string $message Kritická zpráva k zalogování
     * @return void
     */
    public function critical(string $message): void
    {
        $this->log($message, "CRITICAL");
    }

    /**
     * Zapíše výjimku do logu včetně stack trace
     *
     * @param \Throwable $e Výjimka k zalogování
     * @param string $message Volitelná zpráva k výjimce
     * @return void
     */
    public function exception(\Throwable $e, string $message = ""): void
    {
        $fullMessage = $message ? "{$message} - " : "";
        $fullMessage .= "Exception: " . $e->getMessage() . PHP_EOL;
        $fullMessage .= "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")" . PHP_EOL;
        $fullMessage .= "Stack trace:" . PHP_EOL . $e->getTraceAsString();

        // Zpráva je již escapovaná v metodě log()
        $this->log($fullMessage, "EXCEPTION");
    }

    /**
     * Nastaví úroveň logování
     *
     * @param string $level Úroveň logování (DEBUG, INFO, WARNING, ERROR, CRITICAL, EXCEPTION, NONE)
     * @throws \InvalidArgumentException Pokud je úroveň neplatná
     * @return void
     */
    public function setLogLevel(string $level): void
    {
        $levelUpper = strtoupper($level);
        if (!isset(self::LEVELS[$levelUpper])) {
            throw new \InvalidArgumentException(
                "Invalid log level: {$level}. Allowed: " . implode(', ', array_keys(self::LEVELS))
            );
        }
        $this->logLevel = self::LEVELS[$levelUpper];
    }

    /**
     * Nastaví, zda se logy vypisují na obrazovku
     *
     * @param bool $echoOutput True pro zapnutí výpisu na obrazovku
     * @return void
     */
    public function setEchoOutput(bool $echoOutput): void
    {
        $this->echoOutput = $echoOutput;
    }

    /**
     * Nastaví, zda se logy zapisují do souboru
     *
     * @param bool $fileOutput True pro zapnutí zápisu do souboru
     * @return void
     */
    public function setFileOutput(bool $fileOutput): void
    {
        $this->fileOutput = $fileOutput;
        if ($fileOutput) {
            $this->ensureLogDirectory();
        }
    }

    /**
     * Nastaví rotaci logů
     *
     * @param string $rotation Typ rotace (none, daily, hourly, size)
     * @param int $maxSize Maximální velikost souboru v bytech (pouze pro size)
     * @param int $maxFiles Maximální počet souborů
     * @throws \InvalidArgumentException Pokud je typ rotace neplatný
     * @return void
     */
    public function setRotation(string $rotation, int $maxSize = 10485760, int $maxFiles = 30): void
    {
        $this->setRotationInternal($rotation, $maxSize, $maxFiles);
        $this->currentLogFile = $this->generateLogFileName();
        if ($this->fileOutput) {
            $this->ensureLogDirectory();
        }
    }

    /**
     * Nastaví cestu k log souboru
     *
     * @param string $logFile Cesta k souboru
     * @throws \InvalidArgumentException Pokud je cesta neplatná
     * @return void
     */
    public function setLogFile(string $logFile): void
    {
        $this->setLogFileInternal($logFile);
        $this->currentLogFile = $this->generateLogFileName();

        if ($this->fileOutput) {
            $this->ensureLogDirectory();
        }
    }

    /**
     * Vrátí aktuální úroveň logování
     *
     * @return string Aktuální úroveň logování
     */
    public function getLogLevel(): string
    {
        return array_search($this->logLevel, self::LEVELS, true) ?: 'INFO';
    }

    /**
     * Vrátí aktuální cestu k log souboru
     *
     * @return string Cesta k souboru
     */
    public function getCurrentLogFile(): string
    {
        return $this->currentLogFile;
    }

    /**
     * Zkontroluje, zda je daná úroveň povolena
     *
     * @param string $level Úroveň k ověření
     * @return bool True pokud je úroveň povolena
     */
    public function isLevelEnabled(string $level): bool
    {
        $levelValue = self::LEVELS[strtoupper($level)] ?? self::LEVELS['INFO'];
        return $levelValue >= $this->logLevel;
    }

    /**
     * Ošetří speciální znaky v log zprávě pro prevenci log injection
     *
     * @param string $message Původní zpráva
     * @return string Ošetřená zpráva
     */
    private function escapeMessage(string $message): string
    {
        // Nahradíme nebezpečné znaky, které by mohly způsobit log injection
        $message = str_replace(["\0", "\r"], ['\\0', '\\r'], $message);

        // Nahradíme nové řádky mezerou nebo escaped variantou
        // aby útočník nemohl vložit falešné log záznamy
        $message = str_replace("\n", ' | ', $message);

        return $message;
    }

    /**
     * Vrátí dostupné úrovně logování
     *
     * @return array Seznam dostupných úrovní
     */
    public static function getAvailableLevels(): array
    {
        return array_keys(self::LEVELS);
    }

    /**
     * Vrátí dostupné typy rotace
     *
     * @return array Seznam dostupných typů rotace
     */
    public static function getAvailableRotations(): array
    {
        return self::ALLOWED_ROTATIONS;
    }
}