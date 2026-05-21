<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:import-mysql-dump',
    description: 'Import a mysqldump .sql file into the configured DATABASE_URL database',
)]
final class ImportMysqlDumpCommand extends Command
{
    public function __construct(
        #[Autowire('%env(DATABASE_URL)%')]
        private readonly string $databaseUrl,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED, 'Path to .sql dump (mysqldump output)')
            ->addOption('only-if-empty', null, InputOption::VALUE_NONE, 'Skip import when the products table already has rows');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');

        if (!is_readable($file)) {
            $io->error(sprintf('SQL file not readable: %s', $file));

            return Command::FAILURE;
        }

        $mysql = $this->findMysqlBinary();
        if ($mysql === null) {
            $io->error('mysql client not found in PATH (install default-mysql-client).');

            return Command::FAILURE;
        }

        $params = $this->parseDatabaseUrl($this->databaseUrl);
        $database = $params['path'] ?? '';
        if ($database === '') {
            $io->error('DATABASE_URL is missing a database name.');

            return Command::FAILURE;
        }

        if ($input->getOption('only-if-empty')) {
            $check = new Process([
                $mysql,
                '-h', $params['host'],
                '-P', (string) $params['port'],
                '-u', $params['user'],
                sprintf('-p%s', $params['password']),
                $database,
                '-N',
                '-e', 'SELECT COUNT(*) FROM products',
            ]);
            $check->run();
            if ($check->isSuccessful() && (int) trim($check->getOutput()) > 0) {
                $io->writeln('Products table already has data — skipping SQL import.');

                return Command::SUCCESS;
            }
        }

        $process = new Process([
            $mysql,
            '-h', $params['host'],
            '-P', (string) $params['port'],
            '-u', $params['user'],
            sprintf('-p%s', $params['password']),
            $database,
        ]);
        $process->setInput(file_get_contents($file) ?: '');
        $process->setTimeout(600);

        try {
            $process->mustRun(static function (string $type, string $buffer) use ($io): void {
                if ($type === Process::ERR) {
                    $io->write($buffer);
                }
            });
        } catch (ProcessFailedException $e) {
            $io->error('MySQL import failed: '.$e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Imported SQL dump from %s', $file));

        return Command::SUCCESS;
    }

    private function findMysqlBinary(): ?string
    {
        foreach (['mysql', 'mariadb'] as $binary) {
            $process = new Process(['which', $binary]);
            $process->run();
            if ($process->isSuccessful()) {
                return trim($process->getOutput()) ?: $binary;
            }
        }

        return null;
    }

    /**
     * @return array{host: string, port: int, user: string, password: string, path: string}
     */
    private function parseDatabaseUrl(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            throw new \InvalidArgumentException('Invalid DATABASE_URL.');
        }

        $path = isset($parts['path']) ? ltrim((string) $parts['path'], '/') : '';
        if (str_contains($path, '?')) {
            $path = strstr($path, '?', true) ?: $path;
        }

        return [
            'host' => (string) $parts['host'],
            'port' => (int) ($parts['port'] ?? 3306),
            'user' => (string) ($parts['user'] ?? 'root'),
            'password' => (string) ($parts['pass'] ?? ''),
            'path' => $path,
        ];
    }
}
