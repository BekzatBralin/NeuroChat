// config.php
header('Content-Type: application/json');
echo json_encode([
    'paths' => [
        'api'      => '/api.php',
        'upload'   => '/upload.php',
        'history'  => '/history.php',
        'stream'   => '/stream.php',
        'title'    => '/title.php',
    ]
]);