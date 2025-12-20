<?php
// test_scanner_methods_direct.php
require_once 'autoloader.php';

$config = ['ignore_patterns' => ['vendor/', '.git/', '~']];
$scanner = new \Scanner\Services\ProjectScanner($config);

echo "<pre>=== PROJECTSCANNER CAPABILITIES ===\n\n";
echo "Class: " . get_class($scanner) . "\n\n";

echo "Public methods:\n";
$methods = get_class_methods($scanner);
foreach ($methods as $method) {
    $reflection = new ReflectionMethod($scanner, $method);
    $visibility = $reflection->isPublic() ? 'public' : 
                 ($reflection->isProtected() ? 'protected' : 'private');
    echo "  $visibility $method()\n";
}

echo "\nTesting scanDirectoryManually (if exists):\n";
if (method_exists($scanner, 'scanDirectoryManually')) {
    $reflection = new ReflectionMethod($scanner, 'scanDirectoryManually');
    echo "  Exists, visibility: " . 
         ($reflection->isPublic() ? 'public' : 
          ($reflection->isProtected() ? 'protected' : 'private')) . "\n";
} else {
    echo "  Does not exist!\n";
}
echo "</pre>";