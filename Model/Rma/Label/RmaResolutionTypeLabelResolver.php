<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\Label;

class RmaResolutionTypeLabelResolver extends AbstractRmaLookupLabelResolver
{
    public const string TABLE = 'rma_resolution_type';

    /**
     * @return string
     */
    protected function getTable(): string
    {
        return self::TABLE;
    }
}
