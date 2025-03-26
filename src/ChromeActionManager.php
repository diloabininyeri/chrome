<?php

namespace Zeus\Chrome;

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
    }

    /***
     * @param string $tabId
     * @param string $jsCode
     * @param bool $returnResult
     * @return array
     */
    public function executeJs(string $tabId,#[Language("JavaScript")]string $jsCode,bool $returnResult = false): array
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
            $response = $this->fetchResponse();
            if (isset($response['result'])) {
                return $response;
            }
            usleep(100000);  // sleep 100ms to prevent high CPU usage
        }

        throw new MaxWaitException("Timeout occurred while executing JS on tab $tabId.");

    }


    /**
     * @param string $url
     * @return array
     */
    public function openNewTab(string $url = 'about:blank'): array
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Target.createTarget',
            'params' => ['url' => $url]
        ];
        $this->execute($command);
        return $this->fetchResponse();
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
        return $this->fetchResponse();
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
    public function takeScreenshot(string $tabId, string $format = 'png', int $quality = 100): array
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
        return $this->fetchResponse();
    }

    /**
     * @param string $url
     * @return string
     */
    public function visit(string $url): string
    {

        $navigateId = $this->nextId();
        $this->execute([
            'id' => $navigateId,
            'method' => 'Page.navigate',
            'params' => ['url' => $url]
        ]);

        $this->execute([
            'id' => $this->nextId(),
            'method' => 'Page.enable'
        ]);
        while (true) {
            $response = $this->chrome->read();
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

        $response = $this->fetchResponse();
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


    /**
     * @param string $tabId
     * @param string $url
     * @return array
     */
    public function navigate(string $tabId, string $url): array
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
        return $this->fetchResponse();
    }

    /**
     * @return array
     */
    public function getDomDocument(): array
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'DOM.getDocument',
            'params' => []
        ];
        $this->execute($command);
        return $this->fetchResponse();

    }


    /**
     * @param string $tabId
     * @return string
     */
    public function fetchTitle(string $tabId): string
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Page.getTitle',
            'params' => ['tabId' => $tabId]
        ];
        $this->execute($command);
        return $this->fetchResponse()['result']['title'];
    }

    /**
     * @param string $tabId
     * @return string
     */
    public function fetchUrl(string $tabId): string
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Page.getUrl',
            'params' => ['tabId' => $tabId]
        ];
        $this->execute($command);
        return $this->fetchResponse()['result']['url'];
    }

    /**
     * @param string $tabId
     * @return string
     */
    public function fetchContent(string $tabId): string
    {
        $command = [
            'id' => $this->nextId(),
            'method' => 'Page.getContent',
            'params' => ['tabId' => $tabId]
        ];
        $this->execute($command);
        $response = $this->fetchResponse();
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
        $metrics = $this->fetchResponse()['result']['metrics'];
        return $metrics['browserProcessMemoryMB'] / 1024;
    }

    /**
     * @return int
     */
    private function nextId(): int
    {
        return ++$this->id;
    }

    /**
     * @return array
     */
    public function fetchResponse(): array
    {
        return $this->chrome->read();
    }

    /**
     * @return Chrome
     */
    public function getChrome(): Chrome
    {
        return $this->chrome;
    }

    public function input(string $tabId,string $id,int|string|null $value):array
    {
        //todo
       $this->executeJs($tabId,"(function (){ return  document.querySelector('$id').value=$value;})()");
        return $this->fetchResponse();
    }
}
