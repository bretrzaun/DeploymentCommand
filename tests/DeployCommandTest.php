<?php
namespace BretRZaun\DeployCommand\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use BretRZaun\DeploymentCommand\DeploymentCommand;
use BretRZaun\DeploymentCommand\ProcessFactory;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;
use Laravel\Envoy\ParallelSSH;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Output\OutputInterface;

class DeployCommandTest extends TestCase
{
    private function createApplication(): Application
    {
        $application = new Application();
        $application->setCatchExceptions(false);
        $application->add(new DeploymentCommand(__DIR__.'/data'));
        return $application;
    }

    public function testRunWithoutEnvironment(): void
    {
        $application = $this->createApplication();

        $command = $application->find('deploy');
        $commandTester = new CommandTester($command);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "env").');
        $commandTester->execute(array(
            'command'  => $command->getName()
        ));
    }

    public function testRunWithMissingConfiguration(): void
    {
        $application = $this->createApplication();

        $command = $application->find('deploy');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command'  => $command->getName(),
            'env' => 'does-not-exist'
        ]);
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('[ERROR] Config file', $output);
    }

    public function testRunEmptyConfiguration(): void
    {
        $application = $this->createApplication();
        $command = $application->find('deploy');
        $commandTester = new CommandTester($command);
        $commandTester->execute(array(
            'command'  => $command->getName(),
            'env' => 'test_empty'
        ));
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Deploy application (test_empty)', $output);
        $this->assertStringContainsString('[ERROR] The required options "server[nodes]", "server[target]" are missing.', $output);
    }

    public function testRun(): void
    {
        $application = $this->createApplication();
        $command = $application->find('deploy');
        #$mockSSH = $this->createMock(ParallelSSH::class);
        $mockFactory = $this->createMock(ProcessFactory::class);
        $mockProcess = $this->createMock(Process::class);

        $mockProcess
            ->method('run');
        $mockProcess
            ->method('isSuccessful')
            ->willReturn(true);

        $mockFactory->expects($this->exactly(5))
                ->method('factory')
                ->withConsecutive(
                    [$this->equalTo('command1')],
                    [$this->equalTo('ssh -i /path-to/keyfile user@my-server "cd /target-folder; remote-command1"')],
                    [
                        $this->equalTo('rsync -avz -e \'ssh -i /path-to/keyfile\' --delete . user@my-server:/target-folder')
                    ],
                    [$this->equalTo('ssh -i /path-to/keyfile user@my-server "cd /target-folder; remote-command2"')],
                    [$this->equalTo('command2')]
                )
                ->willReturn($mockProcess);

        $command->setProcessFactory($mockFactory);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'command'  => $command->getName(),
                'env' => 'test'
            ],
            [
                'verbosity' => OutputInterface::VERBOSITY_VERY_VERBOSE
            ]
          );
        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('Deploy application (test)', $output);
        $this->assertStringContainsString('- Run local script(s) (pre-deploy-cmd)', $output);
        $this->assertStringContainsString('- Run remote scripts (pre-deploy-cmd)', $output);
        $this->assertStringContainsString('- Transfer files', $output);
        $this->assertStringContainsString('  - rsync -avz -e \'ssh -i /path-to/keyfile\' --delete . user@my-server:/target-folder', $output);
        $this->assertStringContainsString('- Run remote scripts (post-deploy-cmd)', $output);
        $this->assertStringContainsString('- Run local script(s) (post-deploy-cmd)', $output);
        $this->assertStringContainsString('[OK] Deployment successful !', $output);
    }
}
