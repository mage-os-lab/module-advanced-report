<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma;

enum RequestType: string
{
    case WITHDRAWAL = 'withdrawal';
    case RETURN = 'return';
    case MIXED = 'mixed';

    /**
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::WITHDRAWAL => (string)__('Withdrawal before shipment'),
            self::RETURN => (string)__('Return after shipment'),
            self::MIXED => (string)__('Mixed (partly shipped)'),
        };
    }
}
