<?php
declare(strict_types=1);

namespace App\Command;

use EasyRdf\Format;
use EasyRdf\Graph;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\VarExporter\VarExporter;

#[AsCommand(name: 'app:convert')]
class Convert extends Command
{
    protected static $defaultName = 'app:convert';

    public function __construct(
        private Graph $graph,
    ) {
        parent::__construct(self::$defaultName);
    }

    protected function configure(): void
    {
        $inputFormats = [];
        $outputFormats = [];

        foreach (Format::getFormats() as $format) {
            if ($format->getSerialiserClass()) {
                $outputFormats[$format->getLabel()] = $format->getName();
            }
            if ($format->getParserClass()) {
                $inputFormats[$format->getLabel()] = $format->getName();
            }
        }

        $inputFormatList = implode(', ', $inputFormats);
        $outputFormatList = implode(', ', $outputFormats);

        $help = <<<EOF
This command allows you to convert a file from one format to another.

Supported input formats:
  $inputFormatList

Supported output formats:
  $outputFormatList
EOF;

        $this
            ->setDescription('Converts a file from one format to another')
            ->addArgument('input', InputArgument::OPTIONAL, 'The input source (file or URL)')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'From input format')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'To output format', 'jsonld')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'The output file')
            ->setHelp($help)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isVerbose = $input->getOption('verbose');

        $fromFormat = $input->getOption('from') ?? 'guess';
        if ($isVerbose && 'guess' === $fromFormat) {
            $output->writeln('<info>Will guess input format...</info>');
        }

        if (!$input->getArgument('input')) {
            if ($isVerbose) {
                $output->writeln('<info>Reading from STDIN...</info>');
            }

            $in = 'php://stdin';
            $this->graph->load($in, $fromFormat);
        } elseif (preg_match('~^(http|https)://~', $input->getArgument('input'))) {
            if ($isVerbose) {
                $output->writeln('<info>Reading from URL...</info>');
            }

            $in = $input->getArgument('input');
            $this->graph->load($in, $fromFormat);
        } else {
            $fileName = $input->getArgument('input');
            if ($isVerbose) {
                $output->write(sprintf(
                    '<info>Reading from file: "%s" ... </info>',
                    $fileName,
                ));
            }

            $data = file_get_contents($fileName);
            if ($data === false) {
                $output->writeln("<error>FAILED</error>");
                return Command::FAILURE;
            }

            if ($isVerbose) {
                $output->writeln(sprintf(
                    '<info>%d kB.</info>',
                    round(strlen($data) / 1024),
                ));
            }

            $this->graph->parse($data, $fromFormat, $fileName);
        }

        $toFormat = $input->getOption('to');
        $out = $input->getOption('output') ?? 'php://stdout';

        $serialized = $this->graph->serialise($toFormat);
        if ('php' === $toFormat) {
            // Very most definitely not memory efficient
            file_put_contents(
                $out,
                "<?php\nreturn " . VarExporter::export($serialized) . ";\n"
            );
        } else {
            file_put_contents($out, $serialized);
        }

        return Command::SUCCESS;
    }
}
