<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\Label;

use MageOS\AdvancedReport\Model\DataSource\Mysql\ReportConnection;
use MageOS\AdvancedReport\Model\DataSource\ReportConnectionUnavailableException;
use MageOS\AdvancedReport\Model\Label\AbstractLabelResolver;
use PDOException;
use Zend_Db_Adapter_Exception;

abstract class AbstractRmaLookupLabelResolver extends AbstractLabelResolver
{
    public const string ID_COLUMN = 'entity_id';
    public const string LABEL_COLUMN = 'label';

    /**
     * @param ReportConnection $reportConnection
     */
    public function __construct(
        protected readonly ReportConnection $reportConnection,
    ) {
    }

    /**
     * @return string
     */
    abstract protected function getTable(): string;

    /**
     * @param string[] $values
     * @param array<string, mixed> $context
     * @throws ReportConnectionUnavailableException
     * @throws PDOException
     * @throws Zend_Db_Adapter_Exception
     * @return array<int|string, string>
     */
    protected function loadLabels(array $values, array $context): array
    {
        $connection = $this->reportConnection->get();
        $select = $connection->select()
            ->from(
                $this->reportConnection->getTableName($this->getTable()),
                [self::ID_COLUMN, self::LABEL_COLUMN]
            )
            ->where(self::ID_COLUMN . ' IN (?)', array_map(intval(...), $values));

        return array_map(
            static fn (mixed $label): string => (string)__((string)$label),
            $connection->fetchPairs($select)
        );
    }
}
