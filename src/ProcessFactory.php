<?php
namespace BretRZaun\DeploymentCommand;

use Symfony\Component\Process\Process;

class ProcessFactory
{
    public function factory($cmd) : Process
    {
        if (is_string($cmd)) {
            $cmd = explode(' ', $cmd);
        }
        return new Process($cmd);
    }
}
