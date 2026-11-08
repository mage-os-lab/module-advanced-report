<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\FieldExpression;

use Magento\Framework\DB\Adapter\AdapterInterface;
use MageOS\AdvancedReport\Model\DataSource\Mysql\FieldExpressionProviderInterface;
use MageOS\AdvancedReport\Model\Definition\Dataset;
use MageOS\AdvancedReport\Model\Rma\RequestType;

class ItemRequestTypeExpression implements FieldExpressionProviderInterface
{
    public const string TEMPLATE = "(CASE WHEN %s THEN '%s' ELSE '%s' END)";

    /**
     * @param ReturnStageSql $returnStageSql
     */
    public function __construct(
        protected readonly ReturnStageSql $returnStageSql,
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
            $this->returnStageSql->shippedBefore('main.order_item_id', 'rma.order_id', 'rma.created_at'),
            RequestType::RETURN->value,
            RequestType::WITHDRAWAL->value
        );
    }
}
