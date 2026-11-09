<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Model\Rma\Plugin;

use Magento\Framework\Acl\AclResource\ProviderInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use MageOS\AdvancedReport\Model\Rma\Plugin\ReturnsAclResourcePlugin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReturnsAclResourcePlugin::class)]
final class ReturnsAclResourcePluginTest extends TestCase
{
    /**
     * @return void
     */
    public function testItKeepsTheReturnsResourceWhenTheRmaModuleIsEnabled(): void
    {
        $resources = $this->resources();

        self::assertSame($resources, $this->plugin(true)->afterGetAclResources($this->provider(), $resources));
    }

    /**
     * @return void
     */
    public function testItRemovesOnlyTheReturnsResourceWhenTheRmaModuleIsDisabled(): void
    {
        $result = $this->plugin(false)->afterGetAclResources($this->provider(), $this->resources());

        $reports = $result[0]['children'][0]['children'][0];
        self::assertSame('MageOS_AdvancedReport::reports', $reports['id']);
        self::assertSame(
            ['MageOS_AdvancedReport::carts', 'MageOS_AdvancedReport::saved_manage'],
            array_column($reports['children'], 'id')
        );
        self::assertSame('Magento_Sales::sales', $result[0]['children'][1]['id']);
    }

    /**
     * @return void
     */
    public function testItLeavesAnEmptyTreeUnchanged(): void
    {
        self::assertSame([], $this->plugin(false)->afterGetAclResources($this->provider(), []));
    }

    /**
     * @param bool $enabled
     * @return ReturnsAclResourcePlugin
     */
    protected function plugin(bool $enabled): ReturnsAclResourcePlugin
    {
        $manager = $this->createMock(ModuleManager::class);
        $manager->expects(self::once())->method('isEnabled')->with('MageOS_RMA')->willReturn($enabled);

        return new ReturnsAclResourcePlugin($manager);
    }

    /**
     * @return ProviderInterface
     */
    protected function provider(): ProviderInterface
    {
        return $this->createStub(ProviderInterface::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function resources(): array
    {
        return [[
            'id' => 'Magento_Backend::admin',
            'children' => [
                [
                    'id' => 'Magento_Reports::report',
                    'children' => [[
                        'id' => 'MageOS_AdvancedReport::reports',
                        'children' => [
                            ['id' => 'MageOS_AdvancedReport::carts', 'children' => []],
                            ['id' => 'MageOS_AdvancedReport::returns', 'children' => []],
                            ['id' => 'MageOS_AdvancedReport::saved_manage', 'children' => []],
                        ],
                    ]],
                ],
                ['id' => 'Magento_Sales::sales', 'children' => []],
            ],
        ]];
    }
}
