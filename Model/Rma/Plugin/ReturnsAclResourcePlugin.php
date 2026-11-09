<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\Plugin;

use Magento\Framework\Acl\AclResource\ProviderInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use MageOS\AdvancedReport\Model\Rma\Config\RmaDefinitionSource;

class ReturnsAclResourcePlugin
{
    public const string RESOURCE_ID = 'MageOS_AdvancedReport::returns';

    /**
     * @param ModuleManager $moduleManager
     */
    public function __construct(
        protected readonly ModuleManager $moduleManager,
    ) {
    }

    /**
     * @param ProviderInterface $subject
     * @param array<int, array<string, mixed>> $result
     * @return array<int, array<string, mixed>>
     */
    public function afterGetAclResources(ProviderInterface $subject, array $result): array
    {
        if ($this->moduleManager->isEnabled(RmaDefinitionSource::MODULE_NAME)) {
            return $result;
        }

        return $this->removeResource($result);
    }

    /**
     * @param array<int, array<string, mixed>> $resources
     * @return array<int, array<string, mixed>>
     */
    protected function removeResource(array $resources): array
    {
        $kept = [];
        foreach ($resources as $resource) {
            if (($resource['id'] ?? null) === self::RESOURCE_ID) {
                continue;
            }
            if (!empty($resource['children']) && \is_array($resource['children'])) {
                $resource['children'] = $this->removeResource($resource['children']);
            }
            $kept[] = $resource;
        }

        return $kept;
    }
}
