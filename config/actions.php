<?php
/**
 * Registr všech dostupných akcí v aplikaci
 * 
 * Každá akce odpovídá parametru ?action= v URL a mapuje se na konkrétní handler.
 * Handlery jsou umístěny v src/Handlers/.
 */

declare(strict_types=1);

return [
    // Hlavní stránka - seznam dostupných projektů
    'list' => \Scanner\Handlers\ProjectListHandler::class,
    
    // Skenování konkrétního projektu
    'scan' => \Scanner\Handlers\ProjectScanHandler::class,
    
    // Zobrazení obsahu konkrétního souboru
    'view' => \Scanner\Handlers\FileViewHandler::class,
    
    // API endpoint pro získání pravidel (vrátí JSON)
    'api_rules' => \Scanner\Handlers\RulesApiHandler::class,
    
    // Chybová stránka (používá se interně)
    'error' => \Scanner\Handlers\ErrorHandler::class,
];