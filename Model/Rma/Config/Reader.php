<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Model\Rma\Config;

use Magento\Framework\Config\Dom;
use Magento\Framework\Config\FileResolverInterface;
use Magento\Framework\Config\SchemaLocatorInterface;
use Magento\Framework\Config\ValidationStateInterface;
use MageOS\AdvancedReport\Model\Config\Converter;
use MageOS\AdvancedReport\Model\Config\Reader as ConfigReader;

class Reader extends ConfigReader
{
    public const string FILE_NAME = 'report_datasets_rma.xml';
    public const string DEFAULT_SCOPE = 'global';

    /**
     * @param FileResolverInterface $fileResolver
     * @param Converter $converter
     * @param SchemaLocatorInterface $schemaLocator
     * @param ValidationStateInterface $validationState
     * @param string $fileName
     * @param array<string,string> $idAttributes
     * @param string $domDocumentClass
     * @param string $defaultScope
     */
    public function __construct(
        FileResolverInterface $fileResolver,
        Converter $converter,
        SchemaLocatorInterface $schemaLocator,
        ValidationStateInterface $validationState,
        string $fileName = self::FILE_NAME,
        array $idAttributes = [],
        string $domDocumentClass = Dom::class,
        string $defaultScope = self::DEFAULT_SCOPE,
    ) {
        parent::__construct(
            $fileResolver,
            $converter,
            $schemaLocator,
            $validationState,
            $fileName,
            $idAttributes,
            $domDocumentClass,
            $defaultScope,
        );
    }
}
