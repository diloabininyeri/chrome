<?php

namespace Zeus\Chrome;

use JsonException;

class Command
{
    private array $command;

    /**
     * @param array $command
     */
    public function __construct(array $command = [])
    {
        $this->command = $command;
    }

    /***
     * @return array
     */
    public function toArray(): array
    {
        return $this->command;
    }

    /***
     * @return string
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /***
     * @param array $commandArray
     * @return self
     */
    public static function fromArray(array $commandArray): self
    {
        $instance = new self();
        $instance->command = $commandArray;
        return $instance;
    }

    /***
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function addCommand(string $key, mixed $value): void
    {
        $this->command[$key] = $value;
    }

    /**
     * @return string
     */
    public function safeToJson(): string
    {
        try {
            return $this->toJson();
        } catch (JsonException) {
            return '{}';
        }
    }

    /***
     * @return void
     */
    public function clear(): void
    {
        $this->command = [];
    }

    /***
     * @param array $commands
     * @return void
     */
    public function addMultipleCommands(array $commands): void
    {
        $this->command = array_merge($this->command, $commands);
    }

    /***
     * @return void
     */
    public function printCommand(): void
    {
        echo "Command:\n";
        print_r($this->command);
    }

    /***
     * @return int
     */
    public function getCommandCount(): int
    {
        return count($this->command);
    }
}
