<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
$kernel = new Kernel('test', true);
$kernel->boot();

// 1. Get Routes from Application
$router = $kernel->getContainer()->get('router');
$allRoutes = $router->getRouteCollection()->all();

$apiRoutes = [];
foreach ($allRoutes as $name => $route) {
    // Filter internal routes
    if (strpos($name, '_profiler') !== false || strpos($name, '_wdt') !== false || strpos($name, '_error') !== false || strpos($name, '_preview_error') !== false || strpos($name, 'nelmio_apidoc') !== false) {
        continue;
    }

    // Only consider routes starting with /api if you have a specific prefix, OR just all routes.
    // The previous output suggested routes like /actividades, /auth, etc.
    // We'll take all non-internal routes.

    $path = $route->getPath();
    $methods = $route->getMethods() ?: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
    foreach ($methods as $method) {
        $apiRoutes[$path][$method] = $name;
    }
}

// 2. Generate OpenAPI JSON from command
// We use open_process or shell_exec
$cmd = 'php bin/console nelmio:apidoc:dump --format=json --no-debug';
$output = shell_exec($cmd);

if (!$output) {
    echo "Failed to execute nelmio:apidoc:dump\n";
    exit(1);
}

// Try parsing
$openapiData = json_decode($output, true);
if (!$openapiData) {
    // Try converting if encoding is weird
    $clean = mb_convert_encoding($output, 'UTF-8', 'UTF-16LE');
    $openapiData = json_decode($clean, true);
}

if (!$openapiData) {
    echo "Failed to parse generated OpenAPI JSON. Output start: " . substr($output, 0, 50) . "\n";
    exit(1);
}

$docRoutes = [];
if (isset($openapiData['paths'])) {
    foreach ($openapiData['paths'] as $path => $methods) {
        foreach ($methods as $method => $details) {
            if (in_array(strtoupper($method), ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'])) {
                $docRoutes[$path][strtoupper($method)] = true;
            }
        }
    }
}

// 3. Compare

ob_start();

echo "Analysis Results:\n";
echo "=================\n";


$missingInDoc = [];
foreach ($apiRoutes as $path => $methods) {
    foreach ($methods as $method => $name) {
        // Match path. OpenAPI paths use {param}, Symfony uses {param}. Should match.
        // However, allow for minor differences if needed.
        if (!isset($docRoutes[$path][$method])) {
            $missingInDoc[] = "$method $path ($name)";
        }
    }
}

if (empty($missingInDoc)) {
    echo "All endpoints are documented! \n";
} else {
    echo "Missing endpoints in OpenAPI (" . count($missingInDoc) . "):\n";
    foreach ($missingInDoc as $missing) {
        echo " - [MISSING] $missing\n";
    }
}

$extraInDoc = [];
foreach ($docRoutes as $path => $methods) {
    foreach ($methods as $method => $val) {
        if (!isset($apiRoutes[$path][$method])) {
            $extraInDoc[] = "$method $path";
        }
    }
}

if (!empty($extraInDoc)) {
    echo "\nEndpoints in OpenAPI but not in Application (Ghost endpoints) (" . count($extraInDoc) . "):\n";
    foreach ($extraInDoc as $extra) {
        echo " - [GHOST] $extra\n";
    }
}

$outputContent = ob_get_clean();
file_put_contents(__DIR__ . '/../coverage_report.txt', $outputContent);
echo $outputContent;
