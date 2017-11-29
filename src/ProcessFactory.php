<?php
namespace BretRZaun\DeploymentCommand;

use Symfony\Component\Process\Process;

class ProcessFactory
{
    public function factory($cmd) : Process
    {
      return new Process($cmd);
    }
}
