<?php

namespace Zeus\Chrome;

use JsonException;

/**
 *
 */
readonly class ChromeTabManager
{

    public function __construct(private string $debugUrl)
    {
    }

    public function all(): array
    {
        $tabs = $this->fetchAll();
        return array_map(static fn($tab) => new ChromeTab($tab), $tabs);
    }


    public function get(string $tabId): ?ChromeTab
    {
        return array_find(
            $this->all(),
            static fn(ChromeTab $tab) => $tab->getId() === $tabId);
    }

    /**
     * @return ChromeTab
     */
    public function first(): ChromeTab
    {
        return $this->all()[0];
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
        } catch (JsonException $e) {
            throw new DebugRequestException("Unable to fetch tabs: {$e->getMessage()}");
        }

    }

    /**
     * @return ChromeActionManager
     */

    public function listen(): ChromeActionManager
    {
        return new ChromeActionManager(
            new Chrome(
                new WebSocketClient(
                    $this->first()->getWebSocketDebuggerUrl()
                )
            ));
    }
}
