<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\Label;

class RmaItemConditionLabelResolver extends AbstractRmaLookupLabelResolver
{
    public const string TABLE = 'rma_item_condition';

    /**
     * @return string
     */
    protected function getTable(): string
    {
        return self::TABLE;
    }
}
