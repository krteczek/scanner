<?php
// AdvancedLogger.php (Striktní Singleton verze)

declare(strict_types=1);

namespace Scanner\Logger;

/**
 * Rozšířený logger s pokročilým filtrováním a vlastním formátováním.
 * Implementuje striktní Singleton.
 */
class AdvancedLogger extends Logger
{
    /** @var self|null Singleton instance třídy AdvancedLogger */
    private static ?self $advancedInstance = null;
    
    // Custom mapování úrovní, kompatibilní s rodičem
    private const ADVANCED_LEVELS = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4,
        'EXCEPTION' => 5
    ];

    private int $minLevel;

    /**
     * Konstruktor musí být protected, protože dědí z Loggeru a 
     * chceme zabránit instanciování pomocí operátoru 'new'.
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
     * Vrátí singleton instanci třídy AdvancedLogger.
     * Zde by se mohla provádět custom externí konfigurace, pokud je potřeba.
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

    // PŘEPSANÁ metoda log, která provede filtrování a vlastní formátování.
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

    // --- Pomocné metody (zůstávají stejné, volají opravenou log()) ---

    public function debug(string $message): void { $this->log($message, "DEBUG"); }
    public function info(string $message): void { $this->log($message, "INFO"); }
    public function warning(string $message): void { $this->log($message, "WARNING"); }
    public function error(string $message): void { $this->log($message, "ERROR"); }
    public function critical(string $message): void { $this->log($message, "CRITICAL"); }

    public function exception(\Throwable $e, string $message = ""): void
    {
        $fullMessage = $message ? "{$message} - " : "";
        $fullMessage .= "Exception: " . $e->getMessage() . PHP_EOL;
        $fullMessage .= "File: " . $e->getFile() . " (Line: " . $e->getLine() . ")" . PHP_EOL;
        $fullMessage .= "Stack trace:" . PHP_EOL . $e->getTraceAsString();

        $this->log($fullMessage, "EXCEPTION");
    }
}
