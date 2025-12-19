<?php
/**
 * Rozhraní pro všechny handlery v aplikaci
 * 
 * Každý handler musí implementovat metodu handle(), která přijímá parametry
 * z URL a vrací HTML (nebo jiný) výstup jako string.
 */

declare(strict_types=1);

namespace Scanner\Handlers;

interface HandlerInterface
{
    /**
     * Zpracuje požadavek a vrátí výstup
     * 
     * @param array $params Parametry z URL (např. $_GET)
     * @return string HTML výstup (nebo JSON pro API)
     */
    public function handle(array $params = []): string;
}