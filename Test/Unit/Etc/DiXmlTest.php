<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Etc;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use MageOS\AdvancedReport\Model\Rma\Config\RmaDefinitionSource;
use MageOS\AdvancedReport\Test\Unit\Helper\XPathTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

final class DiXmlTest extends TestCase
{
    use XPathTestTrait;

    private const string DI_XML_PATH = __DIR__ . '/../../../etc/di.xml';
    private const string EXPRESSION_NAMESPACE = 'MageOS\\AdvancedReport\\Model\\DataSource\\Mysql\\FieldExpression\\';
    protected const string RMA_EXPRESSION_NAMESPACE = 'MageOS\\AdvancedReport\\Model\\Rma\\FieldExpression\\';
    protected const array RMA_DATASETS = ['rma_requests', 'rma_items', 'order_returns', 'order_item_returns'];

    public function testItRegistersShipmentCountAndCreditMemoCountProvidersOnTheFieldExpressionResolver(): void
    {
        $xpath = $this->xpath();
        $base = '/config/type[@name="MageOS\\AdvancedReport\\Model\\DataSource\\Mysql\\FieldExpressionResolver"]'
            . '/arguments/argument[@name="providers"]/item';

        self::assertSame(
            self::EXPRESSION_NAMESPACE . 'ShipmentCountExpression',
            trim($this->element($xpath, $base . '[@name="shipment_count"]')->textContent)
        );
        self::assertSame(
            self::EXPRESSION_NAMESPACE . 'CreditMemoCountExpression',
            trim($this->element($xpath, $base . '[@name="credit_memo_count"]')->textContent)
        );
    }

    public function testItRegistersTheMainAndQuoteAliasCartProvidersInTheFieldExpressionResolverPool(): void
    {
        $xpath = $this->xpath();
        $base = '/config/type[@name="MageOS\\AdvancedReport\\Model\\DataSource\\Mysql\\FieldExpressionResolver"]'
            . '/arguments/argument[@name="providers"]/item';

        foreach (['Converted', 'Abandoned', 'Recent', 'State'] as $suffix) {
            self::assertSame(
                self::EXPRESSION_NAMESPACE . 'Cart' . $suffix . 'Expression',
                trim($this->element($xpath, $base . '[@name="cart_' . strtolower($suffix) . '"]')->textContent)
            );
            $virtual = self::EXPRESSION_NAMESPACE . 'CartItems' . $suffix . 'Expression';
            self::assertSame(
                $virtual,
                trim($this->element($xpath, $base . '[@name="cart_items_' . strtolower($suffix) . '"]')->textContent)
            );
            $type = $this->element($xpath, '/config/virtualType[@name="' . $virtual . '"]');
            self::assertSame(self::EXPRESSION_NAMESPACE . 'Cart' . $suffix . 'Expression', $type->getAttribute('type'));
            self::assertSame(
                'quote',
                trim($this->element($xpath, 'arguments/argument[@name="alias"]', $type)->textContent)
            );
        }
    }

    public function testItIsRegisteredAsTheFirstShipmentWaitExpressionProviderInDiXml(): void
    {
        $item = $this->element(
            $this->xpath(),
            '/config/type[@name="MageOS\\AdvancedReport\\Model\\DataSource\\Mysql\\FieldExpressionResolver"]'
            . '/arguments/argument[@name="providers"]/item[@name="first_shipment_wait"]'
        );

        self::assertSame(self::EXPRESSION_NAMESPACE . 'FirstShipmentWaitExpression', trim($item->textContent));
    }

    public function testItDeclaresTheSalesDocumentCountVirtualTypesWithTheShipmentAndCreditMemoTables(): void
    {
        $xpath = $this->xpath();
        $type = self::EXPRESSION_NAMESPACE . 'SalesDocumentCountExpression';
        $parameters = array_map(
            static fn (ReflectionParameter $parameter): string => $parameter->getName(),
            (new ReflectionClass($type))->getConstructor()?->getParameters() ?? []
        );

        foreach ([
            'ShipmentCountExpression' => ['sales_shipment', 'sales_shipment_item', false],
            'CreditMemoCountExpression' => ['sales_creditmemo', 'sales_creditmemo_item', true],
        ] as $name => [$documentTable, $itemTable, $excludesCanceled]) {
            $node = $this->element($xpath, '/config/virtualType[@name="' . self::EXPRESSION_NAMESPACE . $name . '"]');
            self::assertSame($type, $node->getAttribute('type'));

            foreach (self::queryNodes($xpath, 'arguments/argument', $node) as $argument) {
                self::assertInstanceOf(DOMElement::class, $argument);
                self::assertContains($argument->getAttribute('name'), $parameters);
            }
            $argumentQuery = static fn (string $argName): string => 'arguments/argument[@name="' . $argName . '"]';
            $documentArgument = $this->element($xpath, $argumentQuery('documentTable'), $node);
            self::assertSame('string', $documentArgument->getAttribute('xsi:type'));
            self::assertSame($documentTable, trim($documentArgument->textContent));
            $itemArgument = $this->element($xpath, $argumentQuery('itemTable'), $node);
            self::assertSame('string', $itemArgument->getAttribute('xsi:type'));
            self::assertSame($itemTable, trim($itemArgument->textContent));

            if (!$excludesCanceled) {
                self::assertSame(0, self::queryNodes($xpath, $argumentQuery('excludedStates'), $node)->length);
                continue;
            }
            $states = $this->element($xpath, $argumentQuery('excludedStates'), $node);
            self::assertSame('array', $states->getAttribute('xsi:type'));
            foreach (['canceled' => 'STATE_CANCELED', 'open' => 'STATE_OPEN'] as $itemName => $constant) {
                $item = $this->element($xpath, 'item[@name="' . $itemName . '"]', $states);
                self::assertSame('const', $item->getAttribute('xsi:type'));
                self::assertSame('Magento\\Sales\\Model\\Order\\Creditmemo::' . $constant, trim($item->textContent));
            }
        }
    }

    public function testItRequiresMagentoReportsAbandonedForTheCartsAndCartItemsDatasets(): void
    {
        $xpath = $this->xpath();
        $coreResources = '/config/type[@name="MageOS\\AdvancedReport\\Model\\Security\\DatasetAccess"]'
            . '/arguments/argument[@name="coreResources"]';

        foreach (['carts', 'cart_items'] as $code) {
            $values = [];
            foreach (self::queryNodes($xpath, $coreResources . '/item[@name="' . $code . '"]/item') as $node) {
                self::assertInstanceOf(DOMElement::class, $node);
                $values[] = trim($node->textContent);
            }
            self::assertContains('Magento_Reports::abandoned', $values, $code);
            self::assertContains('Magento_Sales::sales_order', $values, $code);
        }
    }

    public function testItRegistersEveryRmaExpressionProviderOnTheFieldExpressionResolver(): void
    {
        $xpath = $this->xpath();
        $base = '/config/type[@name="MageOS\\AdvancedReport\\Model\\DataSource\\Mysql\\FieldExpressionResolver"]'
            . '/arguments/argument[@name="providers"]/item';

        foreach ([
            'rma_request_type' => 'RequestTypeExpression',
            'rma_is_withdrawal' => 'IsWithdrawalExpression',
            'rma_is_return_after_shipment' => 'IsReturnAfterShipmentExpression',
            'rma_is_mixed_request' => 'IsMixedRequestExpression',
            'rma_request_units' => 'RequestUnitsExpression',
            'rma_days_from_shipment' => 'DaysFromShipmentExpression',
            'rma_item_request_type' => 'ItemRequestTypeExpression',
            'rma_item_is_withdrawal' => 'ItemIsWithdrawalExpression',
            'rma_item_is_return_after_shipment' => 'ItemIsReturnAfterShipmentExpression',
            'rma_item_requested_value' => 'ItemRequestedValueExpression',
            'rma_order_has_request' => 'OrderHasRequestExpression',
            'rma_order_request_count' => 'OrderRequestCountExpression',
            'rma_order_has_withdrawal' => 'OrderHasWithdrawalExpression',
            'rma_order_has_return_after_shipment' => 'OrderHasReturnAfterShipmentExpression',
            'rma_order_item_units_requested' => 'OrderItemUnitsRequestedExpression',
        ] as $code => $name) {
            $item = $this->element($xpath, $base . '[@name="' . $code . '"]');
            self::assertSame('object', $item->getAttribute('xsi:type'), $code);
            self::assertSame(self::RMA_EXPRESSION_NAMESPACE . $name, trim($item->textContent), $code);
        }
    }

    public function testItDeclaresTheRmaFlagVirtualTypesWithTheirTypeAndArgument(): void
    {
        $xpath = $this->xpath();

        foreach ([
            'IsWithdrawalExpression' => ['RequestTypeFlagExpression', 'type', 'string', 'withdrawal'],
            'IsReturnAfterShipmentExpression' => ['RequestTypeFlagExpression', 'type', 'string', 'return'],
            'IsMixedRequestExpression' => ['RequestTypeFlagExpression', 'type', 'string', 'mixed'],
            'ItemIsWithdrawalExpression' => ['ItemShippedFlagExpression', 'shipped', 'boolean', 'false'],
            'ItemIsReturnAfterShipmentExpression' => ['ItemShippedFlagExpression', 'shipped', 'boolean', 'true'],
            'OrderHasWithdrawalExpression' => ['OrderRequestStageExpression', 'shipped', 'boolean', 'false'],
            'OrderHasReturnAfterShipmentExpression' => ['OrderRequestStageExpression', 'shipped', 'boolean', 'true'],
        ] as $name => [$type, $argumentName, $argumentType, $value]) {
            $node = $this->element(
                $xpath,
                '/config/virtualType[@name="' . self::RMA_EXPRESSION_NAMESPACE . $name . '"]'
            );
            self::assertSame(self::RMA_EXPRESSION_NAMESPACE . $type, $node->getAttribute('type'), $name);
            $parameters = array_map(
                static fn (ReflectionParameter $parameter): string => $parameter->getName(),
                (new ReflectionClass(self::RMA_EXPRESSION_NAMESPACE . $type))->getConstructor()?->getParameters() ?? []
            );
            self::assertContains($argumentName, $parameters, $name);
            self::assertSame(1, self::queryNodes($xpath, 'arguments/argument', $node)->length, $name);
            $argument = $this->element($xpath, 'arguments/argument[@name="' . $argumentName . '"]', $node);
            self::assertSame($argumentType, $argument->getAttribute('xsi:type'), $name);
            self::assertSame($value, trim($argument->textContent), $name);
        }
    }

    public function testItRequiresRmaManageAndSalesOrderForTheRmaDatasets(): void
    {
        $xpath = $this->xpath();
        $coreResources = '/config/type[@name="MageOS\\AdvancedReport\\Model\\Security\\DatasetAccess"]'
            . '/arguments/argument[@name="coreResources"]';

        foreach (self::RMA_DATASETS as $code) {
            $values = [];
            foreach (self::queryNodes($xpath, $coreResources . '/item[@name="' . $code . '"]/item') as $node) {
                self::assertInstanceOf(DOMElement::class, $node);
                $values[] = trim($node->textContent);
            }
            self::assertContains('MageOS_RMA::rma_manage', $values, $code);
            self::assertContains('Magento_Sales::sales_order', $values, $code);
        }
    }

    public function testItListsTheRmaDatasetsAsTimeRelativeOnTheReportRunner(): void
    {
        $xpath = $this->xpath();
        $base = '/config/type[@name="MageOS\\AdvancedReport\\Model\\Query\\ReportRunner"]'
            . '/arguments/argument[@name="timeRelativeDatasets"]/item';

        foreach (self::RMA_DATASETS as $code) {
            $item = $this->element($xpath, $base . '[@name="' . $code . '"]');
            self::assertSame('string', $item->getAttribute('xsi:type'), $code);
            self::assertSame($code, trim($item->textContent));
        }
    }

    public function testItRegistersTheRmaDefinitionSourceOnTheDefinitionRegistry(): void
    {
        $item = $this->element(
            $this->xpath(),
            '/config/type[@name="MageOS\\AdvancedReport\\Model\\Definition\\DefinitionRegistry"]'
            . '/arguments/argument[@name="definitionSources"]/item[@name="rma"]'
        );

        self::assertSame('object', $item->getAttribute('xsi:type'));
        self::assertSame(RmaDefinitionSource::class, trim($item->textContent));
    }

    private function xpath(): DOMXPath
    {
        $document = new DOMDocument();
        $document->load(self::DI_XML_PATH);

        return new DOMXPath($document);
    }

    private function element(DOMXPath $xpath, string $query, ?DOMNode $context = null): DOMElement
    {
        $node = self::queryNodes($xpath, $query, $context)->item(0);
        self::assertInstanceOf(DOMElement::class, $node, $query);

        return $node;
    }

    /**
     * Object arguments injected via di.xml must satisfy the constructor parameter type, otherwise
     * the object manager fails at runtime (and admin blocks render empty in default mode).
     */
    #[DataProvider('objectArgumentProvider')]
    public function testItInjectsObjectArgumentsCompatibleWithTheConstructorParameterType(
        string $typeName,
        string $argumentName,
        string $objectClass,
    ): void {
        self::assertTrue(class_exists($typeName), $typeName . ' does not exist');
        $constructor = (new ReflectionClass($typeName))->getConstructor();
        self::assertNotNull($constructor, $typeName . ' has no constructor');

        $parameterType = null;
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getName() === $argumentName) {
                $parameterType = $parameter->getType();
            }
        }
        self::assertInstanceOf(ReflectionNamedType::class, $parameterType, $typeName . '::$' . $argumentName);
        if ($parameterType->isBuiltin()) {
            self::assertContains($parameterType->getName(), ['array', 'object', 'mixed']);

            return;
        }

        self::assertTrue(
            is_a($objectClass, $parameterType->getName(), true),
            \sprintf('%s::$%s expects %s, di.xml injects %s', $typeName, $argumentName, $parameterType->getName(), $objectClass)
        );
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function objectArgumentProvider(): array
    {
        $document = new DOMDocument();
        $document->load(self::DI_XML_PATH);
        $xpath = new DOMXPath($document);

        $cases = [];
        foreach (self::queryNodes($xpath, '/config/type/arguments/argument[@xsi:type="object"]') as $node) {
            self::assertInstanceOf(DOMElement::class, $node);
            $type = $node->parentNode?->parentNode;
            self::assertInstanceOf(DOMElement::class, $type);
            $typeName = $type->getAttribute('name');
            $objectClass = preg_replace('/\\\\Proxy$/', '', trim($node->textContent));
            $cases[$typeName . '::' . $node->getAttribute('name')] = [
                $typeName,
                $node->getAttribute('name'),
                (string) $objectClass,
            ];
        }

        return $cases;
    }
}
