## php chrome client for the Chrome dev tools
to start the chrome client
```console
google-chrome --headless --remote-debugging-port=9222 --remote-debugging-host=127.0.0.1 --no-sandbox --remote-allow-origins=*
```
```php
<?php

use Zeus\Chrome\ChromeTabManager;

require_once 'vendor/autoload.php';


$chromeTabManager = new ChromeTabManager('http://127.0.0.1:9222');


$chromeActionManager = $chromeTabManager->listen();


$openNewTab = $chromeActionManager->openNewTab('https://www.google.com');
$tabId = $openNewTab['result']['targetId'];

$takeScreenshot = $chromeActionManager->takeScreenshot($tabId);

$image = $takeScreenshot['result']['data'];
file_put_contents('image.png', base64_decode($image));



$chromeActionManager->closeTab($tabId);
$chromeActionManager->getChrome()->disconnect();

```
**development will continue**