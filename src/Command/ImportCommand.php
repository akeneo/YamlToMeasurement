<?php

declare(strict_types=1);

namespace App\Command;

use Akeneo\Pim\ApiClient\AkeneoPimClientBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * @author    Julien Sanchez <julien@akeneo.com>
 * @copyright 2020 Akeneo SAS (https://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class ImportCommand extends Command
{
    private const STATUS_CODE_UPDATED = 204;
    private const STATUS_CODE_CREATED = 201;

    protected static $defaultName = 'app:import';

    /** @var SymfonyStyle */
    private $io;

    /** @var AkeneoPimClientBuilder */
    private $clientBuilder;

    public function __construct(
        AkeneoPimClientBuilder $clientBuilder
    ) {
        parent::__construct(static::$defaultName);

        $this->clientBuilder = $clientBuilder;
    }

    protected function configure()
    {
        $this
            ->setDescription('Import a YAML file as measurement family list')
            ->addArgument('filePath', InputArgument::REQUIRED, 'The filePath of the file to import.')
            ->addOption('apiUsername', null, InputOption::VALUE_OPTIONAL, 'The username of the user.', getenv('AKENEO_API_USERNAME'))
            ->addOption('apiPassword', null, InputOption::VALUE_OPTIONAL, 'The password of the user.', getenv('AKENEO_API_PASSWORD'))
            ->addOption('apiClientId', null, InputOption::VALUE_OPTIONAL, '', getenv('AKENEO_API_CLIENT_ID'))
            ->addOption('apiClientSecret', null, InputOption::VALUE_OPTIONAL, '', getenv('AKENEO_API_CLIENT_SECRET'))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $filePath = $input->getArgument('filePath');

        $apiClient = $this->clientBuilder->buildAuthenticatedByPassword(
            $input->getOption('apiClientId'),
            $input->getOption('apiClientSecret'),
            $input->getOption('apiUsername'),
            $input->getOption('apiPassword')
        );

        try {
            $measurementFamilyRawData = Yaml::parseFile($filePath)['measures_config'];
        } catch (ParseException $e) {
            $this->io->error($e->getMessage());

            exit;
        }

        $this->io->title('Measurement family migration tool');
        $this->io->text([
            'Welcome to this migration tool made to help migrate your measurement families from your Custom measurement files.',
            'If you want to automate this process or don\'t want to use default values, add the --no-interaction flag when you call this command.'
        ]);

        if (!$this->io->confirm('Do you want to proceed?')) {
            return;
        }

        $this->io->newLine(2);

        $this->io->title(
            'Start importing file'
        );

        $measurementFamilies = array_map(function ($measurementFamilyCode) use ($measurementFamilyRawData) {
            $measurementFamily = $measurementFamilyRawData[$measurementFamilyCode];

            return $this->convertMeasurementFamily($measurementFamily, $measurementFamilyCode);
        }, array_keys($measurementFamilyRawData));

        $response = $apiClient->getMeasurementFamilyApi()->upsertList(array_values(array_filter($measurementFamilies)));

        $this->processResponse($response);

        $this->io->newLine(2);
    }

    private function convertMeasurementFamily($measurementFamily, $measurementFamilyCode)
    {
        $convertedMeasurementFamily = [
            'code' => (string) $measurementFamilyCode
        ];

        if (isset($measurementFamily['standard'])) {
            $convertedMeasurementFamily['standard_unit_code'] = (string) $measurementFamily['standard'];
        }

        if (!empty($measurementFamily['units'])) {
            $convertedMeasurementFamily['units'] = array_reduce(
                array_keys($measurementFamily['units']),
                function (array $units, string $unitCode) use ($measurementFamily) {
                    $units[$unitCode] = $this->convertUnit($measurementFamily['units'][$unitCode], $unitCode);

                    return $units;
                },
                []
            );
        }

        return $convertedMeasurementFamily;
    }

    private function convertUnit($unit, $unitCode)
    {
        $convertedUnit = [
            'code' => (string) $unitCode
        ];

        $convertions = $unit['convert'];
        if (!empty($convertions)) {
            $convertedUnit['convert_from_standard'] = array_map(function ($operation) {
                $operator = array_keys($operation)[0];
                $value = array_values($operation)[0];

                return [
                    'operator' => (string) array_keys($operation)[0],
                    'value' => is_int($value) ? sprintf('%d', $value) : rtrim(sprintf('%.35f', $value), '.0')
                ];
            }, $convertions);
        }

        if (isset($unit['symbol'])) {
            $convertedUnit['symbol'] = (string) $unit['symbol'];
        }

        return $convertedUnit;
    }

    private function processResponse(array $response)
    {
        $createOrUpdatedCode = 0;
        $errorCount = 0;

        foreach ($response as $measurementFamilyResponse) {
            switch ($measurementFamilyResponse['status_code']) {
                case self::STATUS_CODE_UPDATED:
                case self::STATUS_CODE_CREATED:
                    $createOrUpdatedCode++;
                    break;

                default:
                    $errorCount++;
                    $this->io->error(sprintf('An error occured during the import of the "%s" measurement family: "%s"', $measurementFamilyResponse['code'], $measurementFamilyResponse['message']));
                    foreach ($measurementFamilyResponse['errors'] as $error) {
                        $this->io->error(sprintf('Error on field "%s": "%s"', $error['property'], $error['message']));
                    }
                    break;
            }
        }

        if (0 !== $createOrUpdatedCode) {
            $this->io->success(sprintf(
                'Done (%d created or updated, %d error(s))',
                $createOrUpdatedCode,
                $errorCount
            ));
        }
    }
}
