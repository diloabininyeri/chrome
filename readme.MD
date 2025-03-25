## php chrome client for the Chrome dev tools
```php

<?php

use Zeus\Chrome\Chrome;
use Zeus\Chrome\Command;
use Zeus\Chrome\WebSocketClient;

require_once 'vendor/autoload.php';


$chrome = new Chrome(new WebSocketClient('ws://127.0.0.1:9222/devtools/page/657DB57DB7E9B0AA76FD5B1C838771FD'));

$chrome->connect();

$chrome->on('Page.loadEventFired', function () use ($chrome) {


    $screenshotCommand = [
        "id" => 5,
        "method" => "Page.captureScreenshot",
        "params" => [
            "format" => "png",  // You can also use "jpeg" if needed
            "quality" => 100     // Quality for jpeg (1-100)
        ]
    ];

    $chrome->send(Command::fromArray($screenshotCommand)->safeToJson());

    $response = $chrome->read();
    header("Content-Type: image/png");

    echo base64_decode($response['result']['data']);


});

// Command to navigate to Google
$navigateCommand = [
    "id" => 1,
    "method" => "Page.navigate",
    "params" => [
        "url" => "https://www.google.com"
    ]
];

$chrome->send(Command::fromArray($navigateCommand)->safeToJson());


$response = $chrome->read();


$chrome->trigger('Page.loadEventFired', $response);


$chrome->disconnect();

```
**development will continue**