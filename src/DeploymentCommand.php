<?php
namespace BretRZaun\DeploymentCommand;

use Laravel\Envoy\ParallelSSH;
use Laravel\Envoy\RemoteProcessor;
use Laravel\Envoy\Task;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Exception\RuntimeException;

class DeploymentCommand extends Command
{

    protected $configpath;
    protected $env;

    /**
     * @var SymfonyStyle
     */
    protected $io;

    /**
     * @var OutputInterface
     */
    protected $output;
    protected $config;
    protected $remoteProcessor;
    protected $processFactory;

    public function __construct($configpath)
    {
        parent::__construct();
        $this->configpath = $configpath;
        $this->remoteProcessor = new ParallelSSH();
        $this->processFactory = new ProcessFactory();
    }

    protected function configure()
    {
        $this
            ->setName('deploy')
            ->setDescription('Deploy application')
            ->addArgument('env', InputArgument::REQUIRED, 'Environment')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->io = new SymfonyStyle($input, $output);
        $this->env = $input->getArgument('env');

        $this->io->section("Deploy application ({$this->env})");

        $this->loadConfig();

        try {
            $this
                ->runScriptsLocal('pre-deploy-cmd')
                ->runScriptsRemote('pre-deploy-cmd')
                ->syncFiles()
                ->runScriptsRemote('post-deploy-cmd')
                ;
            $this->output->writeln('');
            $this->io->success(
                'Deployment successful !'
            );
        } catch (\Throwable $e) {
            $this->io->error($e->getMessage());
        } finally {
            $this->runScriptsLocal('post-deploy-cmd');
        }
    }

    protected function loadConfig()
    {
        $filename = $this->configpath.'/'.$this->env.'.json';
        if (!file_exists($filename)) {
            throw new RuntimeException("Config file $filename not found");
        }
        $this->config = json_decode(file_get_contents($filename));
        return $this;
    }

    protected function runScriptsLocal($name)
    {
        if (!isset($this->config->scripts->$name)) {
            return $this;
        }
        $scripts = $this->config->scripts->$name;
        if (empty($scripts)) {
            return $this;
        }
        if (!is_array($scripts)) {
            $scripts = [$scripts];
        }

        $this->output->writeln(' - <info>Run local script(s) (' .$name. ')</info>');
        foreach ($scripts as $cmd) {
            if ($this->output->isVerbose()) {
                $this->output->writeln('   - <comment>' .$cmd. '</comment>');
            }
            #$task = new Process($cmd);
            $task = $this->processFactory->factory($cmd);
            $task->run(function ($type, $data) {
                if ($type === Process::OUT && $this->output->isVeryVerbose()) {
                    $this->output->writeln($data);
                }
            });
            if (!$task->isSuccessful()) {
                throw new RuntimeException($task->getErrorOutput());
            }
        }
        return $this;
    }

    protected function runScriptsRemote($name)
    {
        if (!isset($this->config->server->scripts->$name)) {
            return $this;
        }
        $scripts = $this->config->server->scripts->$name;
        if (!is_array($scripts)) {
            $scripts = [$scripts];
        }
        if (empty($scripts)) {
            return $this;
        }
        $this->output->writeln(" - <info>Run remote scripts ($name)</info>");
        foreach ($scripts as $cmd) {
            $cmd = "cd {$this->config->server->target}; ".$cmd;
            if ($this->output->isVerbose()) {
                $this->output->writeln("   - <comment>$cmd</comment>");
            }

            // Task aufbauen
            $task = new Task(
                $this->config->server->nodes,
                null,
                $cmd,
                true
            );

            // Task ausführen
            $ssh = $this->remoteProcessor;
            $exitcodes = $ssh->run($task, function ($type, $host, $output) {
                if ($type == Process::ERR) {
                    $this->io->error($host.': '.trim($output));
                } else {
                    if ($this->output->isVeryVerbose()) {
                        $this->output->writeln(trim("$host [$type]: $output"));
                    }
                }
            });

            if ($exitcodes != 0) {
                $this->io->error("Error running $name-scripts");
                break;
            }
        }
        return $this;
    }

    protected function syncFiles()
    {
        if (isset($this->config->server->nodes)) {
            $this->output->writeln(' - <info>Transfer files</info>');
            $ignoreFile = $this->configpath.'/'.$this->env.'.ignore';
            foreach ($this->config->server->nodes as $node) {
                $cmd = 'rsync -avz --delete ';
                if (file_exists($ignoreFile)) {
                    $cmd .= "--exclude-from={$ignoreFile} ";
                }
                $cmd .= ". $node:{$this->config->server->target}";
                if ($this->output->isVerbose()) {
                    $this->output->writeln("   - <comment>$cmd</comment>");
                }
                #$task = new Process($cmd);
                $task = $this->processFactory->factory($cmd);
                $task->setTimeout(600);
                $task->run(function ($type, $data) {
                    if ($type == Process::OUT && $this->output->isVerbose()) {
                        $this->output->writeln(trim($data));
                    }
                });
                if (!$task->isSuccessful()) {
                    throw new RuntimeException($task->getErrorOutput());
                }
            }
        } else {
            $this->io->warning('No servers configured');
        }
        return $this;
    }

    public function setRemoteProcessor(RemoteProcessor $processor)
    {
        $this->remoteProcessor = $processor;
    }

    public function setProcessFactory(ProcessFactory $factory)
    {
        $this->processFactory = $factory;
    }
}
