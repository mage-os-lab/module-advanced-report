<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\Label;

class RmaReasonLabelResolver extends AbstractRmaLookupLabelResolver
{
    public const string TABLE = 'rma_reason';

    /**
     * @return string
     */
    protected function getTable(): string
    {
        return self::TABLE;
    }
}
