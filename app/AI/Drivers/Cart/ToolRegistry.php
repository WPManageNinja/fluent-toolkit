<?php

namespace FluentToolkit\AI\Drivers\Cart;

class ToolRegistry
{
    /**
     * @var array<string, ToolInterface>
     */
    private array $tools = [];

    /**
     * @param ToolInterface[] $tools
     */
    public function __construct(array $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(ToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function getDefinitions(): array
    {
        return array_map(
            static fn (ToolInterface $tool): array => $tool->getDefinition(),
            array_values($this->tools)
        );
    }

    public function get(string $name): ?ToolInterface
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array|\WP_Error
     */
    public function execute(string $name, array $arguments)
    {
        $tool = $this->get($name);

        if (!$tool) {
            return new \WP_Error('unknown_tool', sprintf('Tool "%s" is not registered.', $name));
        }

        return $tool->execute($arguments);
    }
}
