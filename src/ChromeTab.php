<?php

namespace Zeus\Chrome;

/**
 *
 */
readonly class ChromeTab
{

    /**
     * @param array $attributes
     */
    public function __construct(private array $attributes = array())
    {
    }

    /**
     * @param string $name
     * @return mixed
     */
    public function getAttribute(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    /**
     * @return string
     */
    public function getWebSocketDebuggerUrl():string
    {
        return $this->getAttribute('webSocketDebuggerUrl');
    }

    /**
     * @return string
     */
    public function getId():string
    {
        return $this->getAttribute('id');
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->getAttribute('type');
    }

    public function getTitle():string
    {
        return $this->getAttribute('title');
    }

    public function getDevtoolsFrontendUrl(): string
    {
        return $this->getAttribute('devtoolsFrontendUrl');
    }
}
