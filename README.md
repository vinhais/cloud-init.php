# CloudInit PHP

Generate your cloud-init files dynamically with PHP.

Requires PHP 8.5 or later.

## Installation

```bash
composer require vinhais/cloud-init.php
```

## Quick start

```php
require 'vendor/autoload.php';

use CloudInit\CloudInit;

$config = CloudInit::make()
    ->setHostname('web-01')
    ->setTimezone('UTC')
    ->setPackageUpdate()
    ->appendPackages(['nginx', 'curl'])
    ->appendRunCommand(['systemctl', 'enable', '--now', 'nginx']);

echo $config->renderString();
```

```yaml
#cloud-config
hostname: web-01
timezone: UTC
package_update: true
packages:
  - nginx
  - curl
runcmd:
  - [systemctl, enable, '--now', nginx]
```

Commands accept a shell string or an argument list:

```php
$config->appendRunCommand('echo "Ready" > /var/tmp/status');
$config->appendRunCommand(['systemctl', 'restart', 'nginx']);
```

## Package helpers

```php
$config = CloudInit::make()
    ->appendSudo()
    ->appendCurlAndWget()
    ->appendQemuGuestAgent();
```

These helpers append `sudo`, `curl`, `wget`, and `qemu-guest-agent` to `packages`, preserving existing entries and duplicates. They only request package installation; they do not configure sudo permissions or add service commands.

## Output

```php
$yaml = $config->renderString();
$path = $config->renderYaml(__DIR__, 'user-data.yaml');
```

`renderString()` returns the YAML string. `renderYaml($directory, $filename)` writes it and returns the absolute path. The directory must exist; existing files are overwritten. `render()` is an alias for `renderString()`.

## Users and files

```php
use CloudInit\CloudInit;
use CloudInit\File;
use CloudInit\User;

$config = CloudInit::make()
    ->appendDefaultUser()
    ->appendUser(
        User::make('deploy')
            ->setShell('/bin/bash')
            ->setGroups(['www-data'])
            ->setSshKeys([trim(file_get_contents(__DIR__ . '/deploy.pub'))])
            ->setLockPassword(true)
    )
    ->appendFile(
        File::make('/var/www/html/index.html', "<h1>Server provisioned</h1>\n")
            ->setOwner('www-data:www-data')
            ->setPermissions('0644')
            ->setDefer()
    );

echo $config->renderString();
```

This example reads an SSH public key from `deploy.pub`. To add keys for the default user, use `$config->appendSshKeys($keys)`.

`set*` methods replace values; `append*` methods add entries. Builders also support `when()`, `unless()`, and `tap()`.

## User-data, meta-data, and network-config

Build and save each NoCloud document separately:

```php
use CloudInit\MetaData;
use CloudInit\Network\Ethernet;
use CloudInit\NetworkConfig;
use CloudInit\UserData;

$userData = UserData::make()
    ->setHostname('web-01')
    ->appendPackages(['nginx']);

$metaData = MetaData::make()
    ->setInstanceId('iid-web-01')
    ->setLocalHostname('web-01');

$networkConfig = NetworkConfig::make()
    ->setEthernet(Ethernet::make('eth0')->setDhcp4());

$userData->renderYaml(__DIR__, 'user-data');
$metaData->renderYaml(__DIR__, 'meta-data');
$networkConfig->renderYaml(__DIR__, 'network-config');
```

`CloudInit` and `UserData` expose the same API. Only user-data includes the `#cloud-config` header. Metadata requires an instance ID. Network configuration uses Version 2 and supports Ethernet interfaces, DHCP, static addresses, DNS, and routes.

## Single-file package

Use `chunk()` to package user-data, meta-data, and network-config in one YAML file:

```php
$chunk = $userData->chunk($metaData, $networkConfig);

$yaml = $chunk->renderString();
$path = $chunk->renderYaml(__DIR__, 'cloud-init.yaml');
```

```yaml
user-data: |
  #cloud-config
  hostname: web-01
  packages:
    - nginx
meta-data: |
  instance-id: iid-web-01
  local-hostname: web-01
network-config: |
  version: 2
  ethernets:
    eth0:
      dhcp4: true
```

The package stores snapshots of all three documents, with `#cloud-config` only in user-data. This is an application transport format, not a cloud-init input format. For NoCloud, extract the strings returned by `$chunk->toArray()` into their named files before provisioning.

## Datasources

- **LXD:** user-data, vendor-data, and network-config in an LXD configuration fragment.
- **MAAS:** cloud-config and base64-encoded `user_data` for deployment requests.
- **WSL:** per-instance user-data and optional meta-data files.
- **NoCloud:** separate user-data, meta-data, and network-config files.

Other adapters are WIP. See [datasource examples and support](docs/datasources.md).

## Validation and exceptions

Invalid values throw specific exceptions, such as `SshKeyInvalidException`, extending `CloudInit\Exceptions\ValidationException`. File and serialization failures throw `FileWriteException` and `YamlRenderException`.

See the [API reference](docs/reference.md) for supported options and exception types.

To validate generated user-data on a system with cloud-init installed:

```bash
cloud-init schema --config-file user-data.yaml
```

## Examples

```bash
php examples/web-server.php
php examples/chunk.php
php examples/lxd.php
php examples/maas.php
php examples/nocloud.php
php examples/wsl.php
```

NoCloud and WSL preview their documents by default; pass an existing output directory to write files. Use `php examples/maas.php --base64` for the MAAS deployment value.

## Development

```bash
composer install
composer validate --strict
composer check
```

## License

[MIT](LICENSE).
