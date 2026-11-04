<?php

declare(strict_types=1);

namespace MageOS\AdvancedReport\Test\Unit\Tooling;

use MageOS\AdvancedReport\Test\Tooling\XsdValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(XsdValidator::class)]
final class XsdValidatorTest extends TestCase
{
    private const string ETC = __DIR__ . '/../../../etc';

    public function testItReportsNoErrorsForTheShippedReportDatasetsXml(): void
    {
        $errors = (new XsdValidator())->validateFile(
            self::ETC . '/report_datasets.xml',
            self::ETC . '/report_datasets_file.xsd'
        );

        self::assertSame([], $errors);
    }

    public function testItReportsAnErrorForAJoinWithUnknownCardinalityValue(): void
    {
        $errors = $this->validateXml(
            '<config><dataset code="a"><join code="j" cardinality="many-to-many"/></dataset></config>'
        );

        self::assertCount(1, $errors);
        self::assertStringContainsString('cardinality', $errors[0]);
    }

    public function testItReportsAnErrorForADatasetWithoutCodeAttribute(): void
    {
        $errors = $this->validateXml('<config><dataset label="No code"/></config>');

        self::assertCount(1, $errors);
        self::assertStringContainsString("'code' is required", $errors[0]);
    }

    public function testItReportsLineNumbersOfLibxmlValidationErrors(): void
    {
        $errors = $this->validateXml("<config>\n<dataset code=\"a\"/>\n<dataset label=\"x\"/>\n</config>");

        self::assertCount(1, $errors);
        self::assertStringStartsWith('Line 3:', $errors[0]);
    }

    public function testDatasetFileWithMarketingAreaValidatesAgainstFileSchema(): void
    {
        $errors = $this->validateXml('<config><dataset code="a" label="A" acl="MageOS_AdvancedReport::marketing" area="marketing"/></config>');

        self::assertSame([], $errors);
    }

    public function testMergedDefinitionWithMarketingAreaValidatesAgainstMergedSchema(): void
    {
        $errors = $this->validateXml(
            '<config><dataset code="a" label="A" acl="MageOS_AdvancedReport::marketing" area="marketing"/></config>',
            self::ETC . '/report_datasets.xsd'
        );

        self::assertSame([], $errors);
    }

    public function testUnknownAreaIsStillRejectedBySchema(): void
    {
        foreach ([self::ETC . '/report_datasets_file.xsd', self::ETC . '/report_datasets.xsd'] as $xsd) {
            $errors = $this->validateXml('<config><dataset code="a" label="A" acl="MageOS_AdvancedReport::marketing" area="bogus"/></config>', $xsd);

            self::assertCount(1, $errors);
            self::assertStringContainsString('area', $errors[0]);
        }
    }

    public function testAclDeclaresMarketingResourceUnderAdvancedReport(): void
    {
        $acl = simplexml_load_file(self::ETC . '/acl.xml');
        self::assertNotFalse($acl);
        $nodes = $acl->xpath(
            '//resource[@id="MageOS_AdvancedReport::reports"]/resource[@id="MageOS_AdvancedReport::marketing"]'
        );

        self::assertIsArray($nodes);
        self::assertCount(1, $nodes);
        self::assertSame('Marketing', (string)$nodes[0]['title']);
        self::assertSame('28', (string)$nodes[0]['sortOrder']);
    }

    /**
     * @return list<string>
     */
    private function validateXml(string $xml, string $xsd = self::ETC . '/report_datasets_file.xsd'): array
    {
        $path = tempnam(sys_get_temp_dir(), 'xsd');
        self::assertIsString($path);
        file_put_contents($path, $xml);

        try {
            return (new XsdValidator())->validateFile($path, $xsd);
        } finally {
            unlink($path);
        }
    }
}
