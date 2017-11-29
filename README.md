# Deployment Command

Symfony Console command to deploy an application to remote server(s).

## Installation

Install via Composer:

```composer require bretrzaun/deploy-command```

## Configuration

For each environment create configuration file named like the environment.

### Example

```
{
    "server" : {
        "nodes" : ["user@my-server"],
        "keyfile" : "the-key-file-to-use",
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

## Usage

Register the console command to a Symfony console application:

```
$console->add(new DeploymentCommand('path/to/config-folder/'));
```
