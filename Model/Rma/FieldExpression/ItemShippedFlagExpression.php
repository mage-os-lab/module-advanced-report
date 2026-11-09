<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\FieldExpression;

use Magento\Framework\DB\Adapter\AdapterInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\FieldExpressionProviderInterface;
use MageOS\AdvancedReport\Model\Definition\Dataset;

class ItemShippedFlagExpression implements FieldExpressionProviderInterface
{
    public const string TEMPLATE = '(%s%s)';
    public const string NEGATION = 'NOT ';

    /**
     * @param ReturnStageSql $returnStageSql
     * @param bool $shipped
     */
    public function __construct(
        protected readonly ReturnStageSql $returnStageSql,
        protected readonly bool $shipped,
    ) {
    }

    /**
     * @param Dataset $dataset
     * @param AdapterInterface $connection
     * @return string
     */
    public function getExpression(Dataset $dataset, AdapterInterface $connection): string
    {
        return \sprintf(
            self::TEMPLATE,
            $this->shipped ? '' : self::NEGATION,
            $this->returnStageSql->shippedBefore('main.order_item_id', 'rma.order_id', 'rma.created_at')
        );
    }
}
