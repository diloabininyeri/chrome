## php chrome client for the Chrome dev tools
to start the chrome client
```console
google-chrome --headless --remote-debugging-port=9222 --remote-debugging-host=127.0.0.1 --no-sandbox --remote-allow-origins=*
```

```php
<?php

use Zeus\Chrome\ChromeTabManager;

require_once 'vendor/autoload.php';


$chromeTabManager = new ChromeTabManager();


$chromeActionManager = $chromeTabManager->getEventManager();


$google = $chromeActionManager->openNewTab('https://www.google.com');

$response = $chromeActionManager->takeScreenshot(tabId: $google, imagePath: __DIR__ . '/google.png');

echo $response; ///home/zeus/PhpstormProjects/chrome/google.png

```
**development will continue**