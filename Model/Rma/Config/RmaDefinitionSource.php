<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\Config;

use Magento\Framework\Module\Manager as ModuleManager;
use MageOS\AdvancedReport\Model\Definition\DefinitionSourceInterface;

class RmaDefinitionSource implements DefinitionSourceInterface
{
    public const string MODULE_NAME = 'MageOS_RMA';

    /**
     * @param ModuleManager $moduleManager
     * @param Data $data
     */
    public function __construct(
        protected readonly ModuleManager $moduleManager,
        protected readonly Data $data,
    ) {
    }

    /**
     * @return array{datasets?: array<string,array<string,mixed>>, presets?: array<string,array<string,mixed>>}
     */
    public function getDefinitions(): array
    {
        if (!$this->moduleManager->isEnabled(self::MODULE_NAME)) {
            return [];
        }

        return $this->data->get(null) ?? [];
    }
}
