<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Model\Rma\Config;

use Magento\Framework\Module\Manager as ModuleManager;
use MageOS\AdvancedReport\Model\Rma\Config\Data;
use MageOS\AdvancedReport\Model\Rma\Config\RmaDefinitionSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RmaDefinitionSource::class)]
final class RmaDefinitionSourceTest extends TestCase
{
    /**
     * @return void
     */
    public function testItReturnsTheRmaDefinitionsWhenTheRmaModuleIsEnabled(): void
    {
        $definitions = ['datasets' => ['rma_requests' => ['code' => 'rma_requests']], 'presets' => []];
        $data = $this->createMock(Data::class);
        $data->expects(self::once())->method('get')->with(null)->willReturn($definitions);

        self::assertSame($definitions, (new RmaDefinitionSource($this->moduleManager(true), $data))->getDefinitions());
    }

    /**
     * @return void
     */
    public function testItReturnsAnEmptyArrayWhenTheRmaDataIsEmpty(): void
    {
        $data = $this->createStub(Data::class);
        $data->method('get')->willReturn(null);

        self::assertSame([], (new RmaDefinitionSource($this->moduleManager(true), $data))->getDefinitions());
    }

    /**
     * @return void
     */
    public function testItReadsNothingWhenTheRmaModuleIsDisabled(): void
    {
        $data = $this->createMock(Data::class);
        $data->expects(self::never())->method('get');

        self::assertSame([], (new RmaDefinitionSource($this->moduleManager(false), $data))->getDefinitions());
    }

    /**
     * @param bool $enabled
     * @return ModuleManager
     */
    protected function moduleManager(bool $enabled): ModuleManager
    {
        $manager = $this->createMock(ModuleManager::class);
        $manager->expects(self::once())->method('isEnabled')->with('MageOS_RMA')->willReturn($enabled);

        return $manager;
    }
}
