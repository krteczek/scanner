<?php
/**
 * Hlavní vstupní bod aplikace Scanner
 * 
 * Front controller, který načte konfiguraci akcí a volá příslušný handler.
 * Jednoduchý systém s parametrem: ?action=nazev_akce
 */

declare(strict_types=1);

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
    $errorHandler = new \Scanner\Handlers\ErrorHandler();
    
    $errorOutput = $errorHandler->handle([
        'error' => 'Systémová chyba',
        'message' => 'Došlo k neočekávané chybě v aplikaci.',
        'details' => sprintf(
            "%s: %s\nFile: %s\nLine: %d\n\nBacktrace:\n%s",
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ),
        'back_url' => '?action=list'
    ]);
    
    echo $errorOutput;
    
    // Volitelně: logování chyby
    if (function_exists('logError')) {
        logError($e);
    }
}