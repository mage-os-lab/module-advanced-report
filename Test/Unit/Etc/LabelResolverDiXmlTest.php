<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Etc;

use DOMDocument;
use DOMElement;
use DOMXPath;
use MageOS\AdvancedReport\Model\Label\CartStateLabelResolver;
use MageOS\AdvancedReport\Model\Label\CustomerLabelResolver;
use MageOS\AdvancedReport\Model\Label\GenderLabelResolver;
use MageOS\AdvancedReport\Model\Label\GuestLabelResolver;
use MageOS\AdvancedReport\Model\Label\MonthLabelResolver;
use MageOS\AdvancedReport\Model\Rma\Label\RmaItemConditionLabelResolver;
use MageOS\AdvancedReport\Model\Rma\Label\RmaReasonLabelResolver;
use MageOS\AdvancedReport\Model\Rma\Label\RmaRequestTypeLabelResolver;
use MageOS\AdvancedReport\Model\Rma\Label\RmaResolutionTypeLabelResolver;
use MageOS\AdvancedReport\Model\Rma\Label\RmaStatusLabelResolver;
use MageOS\AdvancedReport\Test\Unit\Helper\XPathTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LabelResolverDiXmlTest extends TestCase
{
    use XPathTestTrait;

    /**
     * @return array<string, array{class-string}>
     */
    public static function resolverProvider(): array
    {
        return [
            'customer' => [CustomerLabelResolver::class],
            'month' => [MonthLabelResolver::class],
            'gender' => [GenderLabelResolver::class],
            'cart state' => [CartStateLabelResolver::class],
            'guest' => [GuestLabelResolver::class],
            'rma status' => [RmaStatusLabelResolver::class],
            'rma reason' => [RmaReasonLabelResolver::class],
            'rma resolution type' => [RmaResolutionTypeLabelResolver::class],
            'rma item condition' => [RmaItemConditionLabelResolver::class],
            'rma request type' => [RmaRequestTypeLabelResolver::class],
        ];
    }

    #[DataProvider('resolverProvider')]
    public function testItIsRegisteredInTheLabelResolverPool(string $class): void
    {
        $document = new DOMDocument();
        $document->load(__DIR__ . '/../../../etc/di.xml');
        $nodes = self::queryNodes(
            new DOMXPath($document),
            '/config/type[@name="MageOS\\AdvancedReport\\Model\\Label\\LabelResolverPool"]'
            . '/arguments/argument[@name="resolvers"]/item[@name="' . $class . '"]'
        );

        self::assertSame(1, $nodes->length);
        $node = $nodes->item(0);
        self::assertInstanceOf(DOMElement::class, $node);
        self::assertSame($class, trim($node->textContent));
    }
}
