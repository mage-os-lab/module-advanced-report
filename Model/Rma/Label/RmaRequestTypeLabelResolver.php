<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\Label;

use MageOS\AdvancedReport\Model\Label\AbstractLabelResolver;
use MageOS\AdvancedReport\Model\Rma\RequestType;

class RmaRequestTypeLabelResolver extends AbstractLabelResolver
{
    /**
     * @param string[] $values
     * @param array<string, mixed> $context
     * @return array<int|string, string>
     */
    protected function loadLabels(array $values, array $context): array
    {
        $labels = [];
        foreach (RequestType::cases() as $type) {
            $labels[$type->value] = $type->label();
        }

        return $labels;
    }
}
