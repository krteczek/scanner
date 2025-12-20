<?php
// autoloader.php v KOŘENI scanneru
declare(strict_types=1);

spl_autoload_register(function (string $className): void {
    // Debug: logování hledaných tříd
    // error_log("Autoloader hledá: $className");
    
    // Převést namespace na cestu
    $prefix = 'Scanner\\';
    
    // Kontrola zda třída patří do našeho namespace
    if (strpos($className, $prefix) !== 0) {
        return; // Nechat jinému autoloaderu
    }
    
    // Odstranit prefix a převést na cestu
    $relativeClass = substr($className, strlen($prefix));
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
    
    // Načíst pokud existuje
    if (file_exists($file)) {
        require $file;
        return;
    }
    
    // Debug: pokud třída nebyla nalezena
    error_log("Autoloader: Třída $className nebyla nalezena v $file");
});