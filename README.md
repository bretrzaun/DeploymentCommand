# Deployment Command

[![Latest Stable Version](https://poser.pugx.org/bretrzaun/deployment-command/v)](//packagist.org/packages/bretrzaun/deployment-command)
[![Build Status](https://travis-ci.org/bretrzaun/DeploymentCommand.svg?branch=master)](https://travis-ci.org/bretrzaun/DeploymentCommand)
[![License](https://poser.pugx.org/bretrzaun/deployment-command/license)](//packagist.org/packages/bretrzaun/deployment-command)

Symfony Console command to deploy an application to remote server(s).

## Installation

Install via Composer:

```composer require bretrzaun/deployment-command```

## Configuration

For each environment create a configuration file named like the environment.

The nodes must be accessible via SSH-based authentication or a keyfile can be given.

### Example

```json
{
    "server" : {
        "nodes" : ["user@my-server"],
        "keyfile": "/path-to/keyfile",
        "target" : "/target-folder",
        "scripts" : {
            "pre-deploy-cmd" : [],
            "post-deploy-cmd" : [
                "command1",
                "command2"
            ]
        }
    },
    "scripts" : {
        "pre-deploy-cmd" : [
            "composer install --no-dev -o"
        ],
        "post-deploy-cmd" : [
            "composer install"
        ]
    }
}
```

### Options

In the `options` section the following nodes can be defined:

#### script-timeout

Process timeout in seconds for each local script. Default value: 120 seconds

#### sync-timeout

Process timeout in seconds for sync. Default value: 300 seconds


## Usage

Register the console command to a Symfony console application:

```php
$console->add(new DeploymentCommand('path/to/config-folder/'));
```
