<?php
namespace BretRZaun\DeploymentCommand;

use Symfony\Component\Process\Process;

class ProcessFactory
{
    public function factory($cmd) : Process
    {
        if (is_string($cmd)) {
            return Process::fromShellCommandline($cmd);
        }
        return new Process($cmd);
    }
}
