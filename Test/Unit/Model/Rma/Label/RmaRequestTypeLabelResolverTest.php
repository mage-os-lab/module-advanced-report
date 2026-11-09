<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Model\Rma\Label;

use MageOS\AdvancedReport\Model\Rma\Label\RmaRequestTypeLabelResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RmaRequestTypeLabelResolver::class)]
final class RmaRequestTypeLabelResolverTest extends TestCase
{
    /**
     * @return void
     */
    public function testItLabelsEachRequestTypeValue(): void
    {
        self::assertSame(
            [
                'withdrawal' => 'Withdrawal before shipment',
                'return' => 'Return after shipment',
                'mixed' => 'Mixed (partly shipped)',
            ],
            (new RmaRequestTypeLabelResolver())->resolve(['withdrawal', 'return', 'mixed'])
        );
    }

    /**
     * @return void
     */
    public function testItKeepsUnknownValuesUnchanged(): void
    {
        self::assertSame(['bogus' => 'bogus'], (new RmaRequestTypeLabelResolver())->resolve(['bogus']));
    }

    /**
     * @return void
     */
    public function testItLabelsRequestsWithoutItemsAsNone(): void
    {
        self::assertSame(['' => (string)__('(none)')], (new RmaRequestTypeLabelResolver())->resolve(['']));
    }
}
