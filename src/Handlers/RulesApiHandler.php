<?php
/**
 * Handler pro API endpoint pravidel
 * 
 * Odpovídá akci: ?action=api_rules
 * Vrátí JSON se všemi pravidly pro analýzu.
 */

declare(strict_types=1);

namespace Scanner\Handlers;

class RulesApiHandler implements HandlerInterface
{
    /**
     * Zpracuje požadavek na API pravidel
     */
    public function handle(array $params = []): string
    {
        // 1. Načtení pravidel
        $baseDir = realpath(__DIR__ . '/../../../') ?: '';
        $rulesFile = $baseDir . '/config/rules.php';
        
        if (!file_exists($rulesFile)) {
            return $this->jsonResponse([
                'error' => true,
                'message' => 'Soubor s pravidly nebyl nalezen',
                'path' => $rulesFile
            ], 404);
        }
        
        // 2. Načtení a validace pravidel
        try {
            $rules = require $rulesFile;
            
            if (!is_array($rules)) {
                throw new \Exception('Pravidla musí být pole');
            }
            
            // 3. Připravit odpověď
            $response = [
                'success' => true,
                'timestamp' => date('Y-m-d H:i:s'),
                'total_rules' => count($rules),
                'rules' => $rules,
                'metadata' => [
                    'php_version' => PHP_VERSION,
                    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'endpoint' => 'rules_api',
                    'documentation' => 'Toto API vrací všechna pravidla pro analýzu kódu.'
                ]
            ];
            
            return $this->jsonResponse($response);
            
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'error' => true,
                'message' => 'Chyba při načítání pravidel',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Vytvoří JSON odpověď s HTTP hlavičkami
     */
    private function jsonResponse(array $data, int $statusCode = 200): string
    {
        // Nastavení HTTP hlaviček
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            header('Cache-Control: no-cache, must-revalidate');
        }
        
        // Konverze na JSON
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            // Fallback na chybu JSON enkódování
            $errorData = [
                'error' => true,
                'message' => 'Chyba při generování JSON',
                'json_error' => json_last_error_msg()
            ];
            return json_encode($errorData, JSON_PRETTY_PRINT);
        }
        
        return $json;
    }
}