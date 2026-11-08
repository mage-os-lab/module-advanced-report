<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\Label;

class RmaStatusLabelResolver extends AbstractRmaLookupLabelResolver
{
    public const string TABLE = 'rma_status';

    /**
     * @return string
     */
    protected function getTable(): string
    {
        return self::TABLE;
    }
}
