<?php

namespace FluentToolkit\AI\Common;

class DriverRegistry
{
    /**
     * @var array<string, DriverInterface>
     */
    private array $drivers = [];

    /**
     * @param DriverInterface[] $drivers
     */
    public function __construct(array $drivers = [])
    {
        foreach ($drivers as $driver) {
            $this->register($driver);
        }
    }

    public function register(DriverInterface $driver): void
    {
        $this->drivers[$driver->slug()] = $driver;
    }

    /**
     * @return DriverInterface[]
     */
    public function all(): array
    {
        return array_values($this->drivers);
    }
}
