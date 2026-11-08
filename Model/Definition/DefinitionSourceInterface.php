<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Definition;

interface DefinitionSourceInterface
{
    /**
     * @return array{datasets?: array<string,array<string,mixed>>, presets?: array<string,array<string,mixed>>}
     */
    public function getDefinitions(): array;
}
