<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Etc;

use Magento\Framework\Acl\AclResource\ProviderInterface;
use Magento\Framework\App\State;
use Magento\Framework\Config\FileResolverInterface;
use Magento\Framework\Config\SchemaLocatorInterface;
use Magento\Framework\Config\ValidationStateInterface;
use MageOS\AdvancedReport\Model\Config;
use MageOS\AdvancedReport\Model\Config\Converter;
use MageOS\AdvancedReport\Model\Config\Reader;
use MageOS\AdvancedReport\Model\Definition\Dataset;
use MageOS\AdvancedReport\Model\Definition\DefinitionFactory;
use MageOS\AdvancedReport\Model\Definition\DefinitionValidator;
use MageOS\AdvancedReport\Model\Definition\Extender\FirstReportAttributePresetExtender;
use MageOS\AdvancedReport\Model\Definition\Preset;
use Psr\Log\LoggerInterface;

/**
 * Loads the real, on-disk `etc/report_datasets.xml` (plus optional extra etc files such as
 * `report_datasets_rma.xml`) through the actual Reader/Converter/DefinitionFactory/DefinitionValidator
 * pipeline (no DB), for dataset-definition tests. Each file is read on its own and merged over the
 * previous ones like {@see \MageOS\AdvancedReport\Model\Definition\DefinitionRegistry} does.
 *
 * Shared by every `Test/Unit/Etc/*DatasetTest` so each one only asserts on the resulting
 * {@see Dataset} value object instead of repeating the loading plumbing.
 */
trait ReportDatasetsFixtureTrait
{
    protected const string REPORT_DATASETS_FILE = 'report_datasets.xml';

    /**
     * Loads, converts and validates every declared dataset, keyed by code.
     *
     * @param string[] $etcFiles
     * @return array<string,Dataset>
     */
    private function loadReportDatasets(array $etcFiles = [self::REPORT_DATASETS_FILE]): array
    {
        return $this->loadValidatedDefinitions($etcFiles)['datasets'];
    }

    /**
     * Loads, converts and validates every declared preset, keyed by code.
     *
     * @param string[] $etcFiles
     * @return array<string,Preset>
     */
    private function loadReportPresets(array $etcFiles = [self::REPORT_DATASETS_FILE]): array
    {
        return $this->loadValidatedDefinitions($etcFiles)['presets'];
    }

    /**
     * @param string[] $etcFiles
     * @return array{datasets: array<string,Dataset>, presets: array<string,Preset>}
     */
    private function loadValidatedDefinitions(array $etcFiles = [self::REPORT_DATASETS_FILE]): array
    {
        $converted = [];
        foreach ($etcFiles as $etcFile) {
            $extra = $this->readConvertedDefinitions($etcFile);
            $converted['datasets'] = array_replace($converted['datasets'] ?? [], $extra['datasets'] ?? []);
            $converted['presets'] = array_replace($converted['presets'] ?? [], $extra['presets'] ?? []);
        }

        $factory = new DefinitionFactory();
        $datasets = $factory->createDatasets($converted['datasets'] ?? []);
        $presets = $factory->createPresets($converted['presets'] ?? []);
        // No report attribute configured: the attribute preset placeholder cannot resolve, so it is dropped.
        $config = $this->createStub(Config::class);
        $config->method('getReportAttributes')->willReturn([]);
        $presets = (new FirstReportAttributePresetExtender($config))->extend($presets);

        $state = $this->createStub(State::class);
        $state->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $aclProvider = $this->createStub(ProviderInterface::class);
        $aclProvider->method('getAclResources')->willReturn([
            ['id' => 'Magento_Sales::actions_view', 'children' => []],
            ['id' => 'Magento_Customer::manage', 'children' => []],
        ]);
        $validator = new DefinitionValidator($state, $this->createStub(LoggerInterface::class), $aclProvider);

        return $validator->validate($datasets, $presets);
    }

    /**
     * @param string $etcFile
     * @return array<string,array<string,mixed>>
     */
    protected function readConvertedDefinitions(string $etcFile): array
    {
        $fileResolver = $this->createStub(FileResolverInterface::class);
        $fileResolver->method('get')->willReturn(
            [(string) file_get_contents(__DIR__ . '/../../../etc/' . $etcFile)]
        );

        $validationState = $this->createStub(ValidationStateInterface::class);
        $validationState->method('isValidationRequired')->willReturn(true);

        $schemaLocator = $this->createStub(SchemaLocatorInterface::class);
        $schemaLocator->method('getSchema')->willReturn(__DIR__ . '/../../../etc/report_datasets.xsd');
        $schemaLocator->method('getPerFileSchema')->willReturn(__DIR__ . '/../../../etc/report_datasets_file.xsd');

        return (new Reader($fileResolver, new Converter(), $schemaLocator, $validationState))->read();
    }

    /**
     * Loads a single dataset by code.
     *
     * @param string $code
     */
    private function loadReportDataset(string $code): Dataset
    {
        return $this->loadReportDatasets()[$code];
    }
}
