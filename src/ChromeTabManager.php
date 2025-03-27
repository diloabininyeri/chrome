<?php

namespace Zeus\Chrome;

use JsonException;

/**
 *
 */
readonly class ChromeTabManager
{

    private ?ChromeActionManager $actionManager;

    public function __construct(private string $debugUrl = 'http://0.0.0.0:9222')
    {
    }

    /**
     * @return ChromeTab[]
     */

    public function all(): array
    {
        $tabs = $this->fetchAll();
        return array_map(static fn($tab) => new ChromeTab($tab), $tabs);
    }


    public function get(string $tabId): ?ChromeTab
    {
        return array_find(
            $this->all(),
            static fn(ChromeTab $tab) => $tab->getId() === $tabId
        );
    }

    /**
     * @return ChromeTab
     */
    public function first(): ChromeTab
    {
        $chromeTabs = $this->all();
        return $chromeTabs[0] ?? throw new ChromeException('There is no open chrome\'s tab,please restart the chrome');
    }

    /**
     * @return array
     */
    private function fetchAll(): array
    {
        $url = parse_url($this->debugUrl);
        ['host' => $host, 'port' => $port, 'scheme' => $scheme] = $url;
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, "$scheme://$host:$port/json");
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curlHandle);
        curl_close($curlHandle);
        try {
            return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new DebugRequestException('tabs not found');
        }

    }

    /**
     * @return ChromeActionManager
     */

    public function getEventManager(): ChromeActionManager
    {
        $this->actionManager ??= new ChromeActionManager(
            new Chrome(
                new WebSocketClient(
                    $this->first()->getWebSocketDebuggerUrl()
                )
            ));
        return $this->actionManager;
    }
}
