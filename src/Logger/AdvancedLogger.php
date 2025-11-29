<?php
// AdvancedLogger.php (Striktní Singleton verze)

declare(strict_types=1);

namespace Scanner\Logger;

/**
 * Rozšířený logger s pokročilým filtrováním a vlastním formátováním
 * Implementuje striktní Singleton a rozšiřuje základní Logger
 *
 * @package Scanner\Logger
 * @author KRS3
 * @version 2.0
 */
class AdvancedLogger extends Logger
{
    /** @var self|null Singleton instance třídy AdvancedLogger */
    private static ?self $advancedInstance = null;
    
    /** @var int Minimální úroveň logování pro pokročilé filtrování */
    private int $minLevel;

    /** @var array Custom mapování úrovní logování */
    private const ADVANCED_LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4,
        'EXCEPTION' => 5
    ];

    /**
     * Konstruktor - musí být protected protože dědí z Loggeru
     * a chceme zabránit instanciování pomocí operátoru 'new'
     *
     * @param array $config Konfigurace pro rodičovský Logger
     */
    protected function __construct(array $config = []) 
    {
        // Předat VŠECHNY potřebné parametry rodiči
        parent::__construct(array_merge([
            'file_path' => 'app.log',
            'echo' => true,
            'level' => 'DEBUG',
            'file' => true,           // ← DŮLEŽITÉ: povolit zápis do souboru!
            'rotation' => 'none'      // ← Pro jednoduchost
        ], $config));
        
        $minLevelUpper = strtoupper($config['min_level'] ?? 'DEBUG');
        $this->minLevel = self::ADVANCED_LEVELS[$minLevelUpper] ?? self::ADVANCED_LEVELS['INFO'];
    }

    /**
     * Vrátí singleton instanci třídy AdvancedLogger
     *
     * @param array $config Konfigurace pro logger (použije se POUZE při prvním volání)
     * @return self Instance AdvancedLoggeru
     */
    public static function getInstance(array $config = []): self
    {
        if (self::$advancedInstance === null) {
            // Při prvním volání vytvoříme instanci a předáme konfiguraci
            self::$advancedInstance = new static($config);
        }
        return self::$advancedInstance;
    }

    /**
     * Zapíše zprávu do logu s pokročilým filtrováním a formátováním
     *
     * @param string $message Zpráva k zalogování
     * @param string $level Úroveň logování
     * @return void
     */
    public function log(string $message, string $level = "INFO"): void
    {
        $levelUpper = strtoupper($level);
        $levelValue = self::ADVANCED_LEVELS[$levelUpper] ?? self::ADVANCED_LEVELS['INFO'];

        // 1. Vlastní FILTROVÁNÍ
        if ($levelValue < $this->minLevel) {
            return;
        }

        // 2. Vlastní FORMÁTOVÁNÍ (vč. milisekund)
        $timestamp = date('Y-m-d H:i:s.v');
        $formattedMessage = sprintf(
            "[%s] %-8s %s",
            $timestamp,
            $levelUpper,
            $message
        );
        
        // 3. VÝPIS NA OBRAZOVKU
        if ($this->echoOutput) {
            echo $formattedMessage . PHP_EOL;
        }
        
        // 4. ZÁPIS DO SOUBORU: Zavoláme rodiče s úrovní 'NONE', aby zprávu zapsal 
        // bez dalšího filtrování a zajistil rotaci, uzamykání a sanitizaci.
        parent::log($formattedMessage, 'NONE');
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

        $this->log($fullMessage, "EXCEPTION");
    }

    /**
     * Nastaví minimální úroveň logování
     *
     * @param string $level Úroveň logování
     * @return void
     */
    public function setMinLevel(string $level): void
    {
        $levelUpper = strtoupper($level);
        $this->minLevel = self::ADVANCED_LEVELS[$levelUpper] ?? self::ADVANCED_LEVELS['INFO'];
    }

    /**
     * Vrátí aktuální minimální úroveň logování
     *
     * @return string Aktuální minimální úroveň
     */
    public function getMinLevel(): string
    {
        return array_search($this->minLevel, self::ADVANCED_LEVELS, true) ?: 'INFO';
    }
}