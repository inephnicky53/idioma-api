<?php
require __DIR__.'/../vendor/autoload.php';

use Vimeo\Vimeo;

// Load environment variables manually
$env = parse_ini_file(__DIR__.'/../.env');

$clientId = $env['VIMEO_CLIENT_ID'] ?? '';
$clientSecret = $env['VIMEO_CLIENT_SECRET'] ?? '';
$accessToken = $env['VIMEO_ACCESS_TOKEN'] ?? '';

echo "<h1>Vimeo Test Script</h1>";
echo "<h2>Configuration:</h2>";
echo "<ul>";
echo "<li>Client ID: ".(!empty($clientId) ? 'SET' : 'MISSING')."</li>";
echo "<li>Client Secret: ".(!empty($clientSecret) ? 'SET' : 'MISSING')."</li>";
echo "<li>Access Token: ".(!empty($accessToken) ? 'SET ('.substr($accessToken, 0, 10).'...)' : 'MISSING')."</li>";
echo "</ul>";

try {
    echo "<h2>Initializing Vimeo...</h2>";
    $vimeo = new Vimeo($clientId, $clientSecret, $accessToken);
    echo "<p style='color: green;'>✓ Vimeo initialized!</p>";

    // Test authenticated request
    echo "<h2>Testing authenticated request...</h2>";
    $response = $vimeo->request('/me');
    echo "<pre>";
    print_r($response);
    echo "</pre>";

    if ($response['status'] === 200) {
        echo "<p style='color: green;'>✓ Authenticated successfully! User: ".$response['body']['name']."</p>";
    } else {
        echo "<p style='color: red;'>✗ Authentication failed!</p>";
    }

    // Test upload capabilities
    echo "<h2>Checking upload capabilities...</h2>";
    $response2 = $vimeo->request('/me?fields=upload_quota');
    echo "<pre>";
    print_r($response2);
    echo "</pre>";

    // Test creating a video (this is what upload() does internally!)
    echo "<h2>Testing video creation (upload initiation)...</h2>";
    $params = [
        'name' => 'Test Video',
        'description' => 'Test Description',
        'upload' => [
            'approach' => 'tus',
            'size' => 1024 // Dummy small size for test
        ]
    ];
    $response3 = $vimeo->request('/me/videos?fields=uri,upload', $params, 'POST');
    echo "<pre>";
    print_r($response3);
    echo "</pre>";

} catch (\Exception $e) {
    echo "<p style='color: red;'>Error: ".$e->getMessage()."</p>";
    echo "<pre>".$e->getTraceAsString()."</pre>";
}
