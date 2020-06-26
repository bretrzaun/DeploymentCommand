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
        $this->assertStringContainsString('[WARNING] No servers configured', $output);
        $this->assertStringContainsString('[OK] Deployment successful !', $output);
    }

    public function testRun(): void
    {
        $application = $this->createApplication();
        $command = $application->find('deploy');
        $mockSSH = $this->createMock(ParallelSSH::class);
        $mockFactory = $this->createMock(ProcessFactory::class);
        $mockProcess = $this->createMock(Process::class);

        $mockProcess
            ->method('run');
        $mockProcess
            ->method('isSuccessful')
            ->willReturn(true);

        $mockFactory->expects($this->exactly(3))
                ->method('factory')
                ->withConsecutive(
                    [$this->equalTo('command1')],
                    [
                        $this->equalTo(['rsync', '-avz', '--delete ', '.', 'user@my-server:/target-folder'])
                    ],
                    [$this->equalTo('command2')]
                )
                ->willReturn($mockProcess);

        $mockSSH
            ->method('run');

        $command->setProcessFactory($mockFactory);
        $command->setRemoteProcessor($mockSSH);
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'command'  => $command->getName(),
                'env' => 'test',
                '-vvv' => true
            ]
          );
        $output = $commandTester->getDisplay();

        #dump($output);
        $this->assertStringContainsString('Deploy application (test)', $output);
        $this->assertStringContainsString('- Run local script(s) (pre-deploy-cmd)', $output);
        $this->assertStringContainsString('- Run remote scripts (pre-deploy-cmd)', $output);
        $this->assertStringContainsString('- Transfer files', $output);
        $this->assertStringContainsString('- Run remote scripts (post-deploy-cmd)', $output);
        $this->assertStringContainsString('- Run local script(s) (post-deploy-cmd)', $output);
        $this->assertStringContainsString('[OK] Deployment successful !', $output);
    }
}
