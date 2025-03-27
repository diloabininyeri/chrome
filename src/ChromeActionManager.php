<?php

namespace Zeus\Chrome;

use Closure;
use JetBrains\PhpStorm\Language;

/**
 *
 */
class ChromeActionManager
{

    /**
     * @var int
     */
    private int $id = 0;

    private array $messageQueue = [];

    /**
     * @param Chrome $chrome
     */
    public function __construct(private readonly Chrome $chrome)
    {
        $this->chrome->connect();
    }

    /**
     * @param array $args
     * @return void
     */
    private function execute(array $args): void
    {
        $this->chrome->send(Command::fromArray($args)->safeToJson());
        $this->bufferMessages($args['id']);
    }

    /***
     * @param string $tabId
     * @param string $jsCode
     * @param bool $returnResult
     * @return array
     */
    public function executeJs(string $tabId, #[Language("JavaScript")] string $jsCode, bool $returnResult = false): array
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Runtime.evaluate',
            'params' => [
                'expression' => $jsCode,
                'returnByValue' => $returnResult,
                'contextId' => $tabId
            ]
        ];
        $this->execute($command);
        $startTime = microtime(true);
        while ((microtime(true) - $startTime) * 1000 < 300000) {
            $response = $this->fetchResponse($command['id']);
            if (isset($response['result'])) {
                return $response;
            }
            usleep(100000);  // sleep 100ms to prevent high CPU usage
        }

        throw new MaxWaitException("Timeout occurred while executing JS on tab $tabId.");

    }


    /**
     * @param string $url
     * @return mixed
     */
    public function openNewTab(string $url = 'about:blank'): mixed
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Target.createTarget',
            'params' => ['url' => $url]
        ];
        $this->execute($command);
        return $this->fetchResponse($command['id'])['result']['targetId'];
    }

    /**
     * @param string $tabId
     * @return array
     */
    public function closeTab(string $tabId): array
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Target.closeTarget',  // Correct method to close the tab
            'params' => ['targetId' => $tabId]
        ];
        $this->execute($command);
        return $this->fetchResponse($command['id']);
    }


    public function closeAllTabs(): void
    {

        $command = [
            'id' => $this->nextId(),
            'method' => 'Target.getTargets'
        ];


        $this->execute($command);
        $response = $this->fetchResponse($command['id']);
        /*
                if (!isset($response['result']['targetInfos'])) {
                    throw new \Exception("Sekmeler alınamadı!");
                }*/


        foreach ($response['result']['targetInfos'] as $target) {
            if (isset($target['targetId'])) {
                $this->closeTab($target['targetId']);
            }
        }

    }

    /**
     * @param string $tabId
     * @param string $url
     * @return void
     */
    public function navigateTo(string $tabId, string $url): void
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Page.navigate',
            'params' => [
                'tabId' => $tabId,
                'url' => $url
            ]
        ];
        $this->execute($command);
    }

    /**
     * @return void
     */
    public function waitForNavigation(): void
    {
        while (true) {
            $response = $this->chrome->read();
            if (isset($response['method']) && in_array($response['method'], ['Page.frameStoppedLoading', 'Page.loadEventFired'])) {
                break;
            }
        }
    }

    /**
     * @param int $timeout
     * @return void
     */
    public function waitForDocumentLoaded(int $timeout = 5000): void
    {
        $this->execute([
            'id' => $this->nextId(),
            'method' => 'Page.enable',
        ]);

        $startTime = microtime(true);

        while (true) {
            $response = $this->chrome->read();

            if ((microtime(true) - $startTime) * 1000 > $timeout) {
                throw new MaxWaitException("Timeout while waiting for document to load.");
            }

            if (isset($response['method']) && $response['method'] === 'Page.loadEventFired') {
                return;
            }
            usleep(100000);

        }
    }


    /**
     * @param string $tabId
     * @param string $format
     * @param int $quality
     * @return array
     */
    public function takeScreenshot(string $tabId, string $format = 'png', int $quality = 100,?string $imagePath=null): string
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Page.captureScreenshot',
            'params' => [
                'tabId' => $tabId,
                'format' => $format,
                'quality' => $quality
            ]
        ];
        $this->execute($command);
        $response = $this->fetchResponse($command['id']);
        $data= $response['result']['data'];
        if ($imagePath) {
            file_put_contents($imagePath, base64_decode($data));
            return $imagePath;
        }
        return $data;

    }

    /***
     * @param string $url
     * @param Closure|null $closure
     * @return string
     */
    public function visit(string $url,?Closure $closure=null): string
    {

        $navigateId = $this->nextId();
        $this->execute([
            'id' => $navigateId,
            'method' => 'Page.navigate',
            'params' => ['url' => $url]
        ]);

        $nextId = $this->nextId();
        $this->execute([
            'id' => $nextId,
            'method' => 'Page.enable'
        ]);
        while (true) {
            $response = $this->fetchResponse($nextId);
            if (isset($response['method']) && $response['method'] === 'Page.frameStoppedLoading') {
                break;
            }
        }

        $htmlId = $this->nextId();
        $this->execute([
            'id' => $htmlId,
            'method' => 'Runtime.evaluate',
            'params' => [
                'expression' => 'document.documentElement.outerHTML',
                'returnByValue' => true
            ]
        ]);

        $response = $this->fetchResponse($htmlId);
        if ($closure!== null) {
          return  $closure($response);
        }
        return $response['result']['result']['value'] ?? '';
    }

    /**
     * @param int $timeout
     * @return void
     */
    public function waitForPageLoad(int $timeout = 5000): void
    {

        $this->execute([
            'id' => $this->nextId(),
            'method' => 'Page.enable',
        ]);

        $startTime = microtime(true);

        while (true) {
            $response = $this->chrome->read();
            if ((microtime(true) - $startTime) * 1000 > $timeout) {
                throw new MaxWaitException("Timeout while waiting for page load.");
            }

            if (isset($response['method']) && $response['method'] === 'Page.loadEventFired') {
                return;
            }


            usleep(100000);
        }
    }


    /***
     * @param string $tabId
     * @param string $url
     * @param Closure|null $closure
     * @return array
     */
    public function navigate(string $tabId, string $url,?Closure $closure=null): array
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Page.navigate',
            'params' => [
                'url' => $url,
                'tabId' => $tabId
            ]
        ];
        $this->execute($command);
        if($closure) {
            return $closure($this->fetchResponse($command['id']));
        }
        return $this->fetchResponse($command['id']);
    }

    /****
     * @param Closure|null $closure
     * @return array
     */
    public function getDomDocument(?Closure $closure=null): array
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'DOM.getDocument',
            'params' => []
        ];
        $this->execute($command);
        if ($closure) {
            return $closure($this->fetchResponse($command['id']));
        }
        return $this->fetchResponse($command['id']);

    }


    /***
     * @param string $tabId
     * @param Closure|null $closure
     * @return string
     */
    public function fetchTitle(string $tabId,?Closure $closure=null): string
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Page.getTitle',
            'params' => ['tabId' => $tabId]
        ];
        $this->execute($command);
        if ($closure) {
            return $closure($this->fetchResponse($command['id']));
        }
        return $this->fetchResponse($command['id'])['result']['title'];
    }

    /***
     * @param string $tabId
     * @param Closure|null $closure
     * @return string
     */
    public function fetchUrl(string $tabId,?Closure $closure=null): string
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Page.getUrl',
            'params' => ['tabId' => $tabId]
        ];
        $this->execute($command);
        if ($closure) {
            return $closure($this->fetchResponse($command['id']));
        }
        return $this->fetchResponse($command['id'])['result']['url'];
    }

    /***
     * @param string $tabId
     * @param Closure|null $closure
     * @return string
     */
    public function fetchContent(string $tabId,?Closure $closure=null): string
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Page.getContent',
            'params' => ['tabId' => $tabId]
        ];
        $this->execute($command);
        $response = $this->fetchResponse($command['id']);
        if ($closure) {
            return $closure($response);
        }
        return $response['result']['content'];
    }

    /**
     * @return float
     */
    public function fetchResponseTime(): float
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Performance.getMetrics'
        ];
        $this->execute($command);
        $metrics = $this->fetchResponse($command['id'])['result']['metrics'];
        return $metrics['browserProcessMemoryMB'] / 1024;
    }

    /**
     * @return int
     */
    private function nextId(): int
    {
        return ++$this->id;
    }

    /***
     * @param int $id
     * @return array
     *
     */
    public function fetchResponse(int $id): array
    {
        return $this->messageQueue[$id];
    }

    /**
     * @return Chrome
     */
    public function getChrome(): Chrome
    {
        return $this->chrome;
    }

    public function input(string $tabId, string $id, int|string|null $value): array
    {
        return $this->executeJs($tabId, "(function (){ return  document.querySelector('$id').value=$value;})()");

    }


    public function bufferMessages(int $id): void
    {
        $this->messageQueue[$id] = $this->chrome->read();
    }
}
