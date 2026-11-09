<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Model\Rma;

use MageOS\AdvancedReport\Model\Rma\RequestType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RequestType::class)]
final class RequestTypeTest extends TestCase
{
    /**
     * @return void
     */
    public function testItLabelsEveryRequestType(): void
    {
        self::assertSame('Withdrawal before shipment', RequestType::WITHDRAWAL->label());
        self::assertSame('Return after shipment', RequestType::RETURN->label());
        self::assertSame('Mixed (partly shipped)', RequestType::MIXED->label());
    }

    /**
     * @return void
     */
    public function testItBacksEveryRequestTypeWithTheValueProducedBySql(): void
    {
        self::assertSame(['withdrawal', 'return', 'mixed'], array_column(RequestType::cases(), 'value'));
    }
}
