<?php

declare(strict_types=1);

use MageOS\AdvancedReport\Test\Tooling\XsdValidator;

require __DIR__ . '/../vendor/autoload.php';

$etc = dirname(__DIR__) . '/etc';
$validator = new XsdValidator();
$failed = false;

foreach (['report_datasets.xml', 'report_datasets_rma.xml'] as $file) {
    $errors = $validator->validateFile($etc . '/' . $file, $etc . '/report_datasets_file.xsd');

    foreach ($errors as $error) {
        fwrite(STDERR, $file . ' ' . $error . PHP_EOL);
    }
    $failed = $failed || $errors !== [];
}

exit($failed ? 1 : 0);
