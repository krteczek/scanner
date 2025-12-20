<?php
// scanner/index.php 
/**
 * Hlavní vstupní bod aplikace Scanner
 * 
 * Front controller, který načte konfiguraci akcí a volá příslušný handler.
 * Jednoduchý systém s parametrem: ?action=nazev_akce
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h3>DEBUG MODE</h3>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'null') . "<br>";
echo "GET params: " . print_r($_GET, true) . "<br>";
flush(); // Vynutí výstup

// 1. Načtení autoloaderu
require_once __DIR__ . '/autoloader.php';

// 2. Načtení konfigurace akcí
$actionsConfig = require __DIR__ . '/config/actions.php';

// 3. Zjištění požadované akce
$requestedAction = $_GET['action'] ?? 'list';

// 4. Validace a volání handleru
try {
    // Kontrola existence akce
    if (!isset($actionsConfig[$requestedAction])) {
        throw new InvalidArgumentException(
            "Akce '$requestedAction' neexistuje. Dostupné akce: " . 
            implode(', ', array_keys($actionsConfig))
        );
    }
   
    // Načtení třídy handleru
    $handlerClass = $actionsConfig[$requestedAction];
    
    if (!class_exists($handlerClass)) {
        throw new RuntimeException(
            "Handler třída '$handlerClass' pro akci '$requestedAction' nebyla nalezena."
        );
    }
    
    // Vytvoření instance handleru
    $handler = new $handlerClass();
   
    // Kontrola implementace rozhraní
    if (!$handler instanceof \Scanner\Handlers\HandlerInterface) {
        throw new RuntimeException(
            "Handler '$handlerClass' neimplementuje HandlerInterface."
        );
    }

    // Zpracování požadavku a získání výstupu
    $output = $handler->handle($_GET);
   
    // Výstup výsledku
    echo $output;
    
} catch (Throwable $e) {
    // Globální zachycení chyb
    try {
        // Autoloader by měl ErrorHandler už načíst
        $errorHandler = new \Scanner\Handlers\ErrorHandler();
        
        $errorOutput = $errorHandler->handle([
            'error' => 'Systémová chyba',
            'message' => 'Došlo k neočekávané chybě v aplikaci.',
            'details' => sprintf(
                "Chyba: %s\nZpráva: %s\nSoubor: %s\nŘádek: %d\n\nBacktrace:\n%s",
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ),
            'back_url' => '?action=list'
        ]);
        
        echo $errorOutput;
        
    } catch (Throwable $innerError) {
        // Kdyby i ErrorHandler selhal, fallback
        echo "<h2>Kritická chyba</h2>";
        echo "<p><strong>" . htmlspecialchars($e->getMessage()) . "</strong></p>";
        echo "<p>Původní chyba: " . htmlspecialchars($innerError->getMessage()) . "</p>";
        echo "<p><a href='?action=list'>← Zpět na seznam</a></p>";
    }
}