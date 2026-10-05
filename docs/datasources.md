# Datasource adapters

These adapters generate configuration artifacts. They do not authenticate, send API requests, create instances, or run deployments. Support means the documented output format is implemented and unit-tested, not that every cloud-init module works on every target image.

## Support status

- **LXD - implemented:** a YAML `config` fragment containing user-data, vendor-data, and Version 2 network-config strings. Modern and legacy key prefixes are supported.
- **MAAS - implemented:** cloud-config user-data and its base64-encoded `user_data` deploy parameter.
- **WSL - implemented:** cloud-config user-data and optional per-instance meta-data files using WSL naming conventions.
- **NoCloud - existing support retained:** independent user-data, meta-data, and Ethernet Version 2 network-config builders.

### WIP

All other datasource-specific adapters are **WIP / not implemented**. This is a roadmap status, not a claim that cloud-init itself lacks support. The generic user-data builder may produce reusable cloud-config, but it does not implement these platforms' delivery protocols:

- Akamai - WIP
- Alibaba Cloud (AliYun) - WIP
- AltCloud - WIP
- Amazon EC2 - WIP
- Azure - WIP
- CloudCIX - WIP
- CloudSigma - WIP
- CloudStack - WIP
- Config drive - WIP
- DigitalOcean - WIP
- Exoscale - WIP
- Fallback/no datasource - WIP
- Google Compute Engine - WIP
- None - WIP
- NWCS - WIP
- OpenNebula - WIP
- OpenStack - WIP
- Oracle - WIP
- OVF - WIP
- Rbx Cloud - WIP
- Scaleway - WIP
- SmartOS - WIP
- UpCloud - WIP
- VMware - WIP
- Vultr - WIP

The catalog follows the [cloud-init datasource index](https://docs.cloud-init.io/en/latest/reference/datasources.html). No placeholder classes are exposed for WIP targets.

## LXD

```php
use CloudInit\Datasources\Lxd;
use CloudInit\Network\Ethernet;
use CloudInit\NetworkConfig;
use CloudInit\UserData;

$lxd = Lxd::make()
    ->setUserData(UserData::make()->setHostname('lxd-web')->appendPackages(['nginx']))
    ->setVendorData(UserData::make()->setPackageUpdate())
    ->setNetworkConfig(NetworkConfig::make()->setEthernet(Ethernet::make('eth0')->setDhcp4()));

$yaml = $lxd->renderString();
$payload = $lxd->toArray();
$lxd->renderYaml(__DIR__, 'lxd-config.yaml');
```

The outer document contains a `config` mapping. Each `cloud-init.*` value is a string containing a separate YAML document. Only nested user-data and vendor-data have `#cloud-config` headers; network-config starts with `version: 2`. Merge this fragment into your instance or profile configuration while retaining its other settings and devices.

`setUserData()`, `setVendorData()`, and `setNetworkConfig()` store snapshots. At least one document must be set before rendering; otherwise, `LxdConfigInvalidException` is thrown. For images requiring older keys, call `$lxd->useLegacyKeys()`. This switches the prefix to `user.*`; `useLegacyKeys(false)` switches back. See [LXD cloud-init configuration](https://documentation.ubuntu.com/lxd/default/cloud-init/).

Metadata such as instance identity is supplied by LXD. This adapter does not turn NoCloud meta-data into an invented `cloud-init.meta-data` key or implement arbitrary `user.*` metadata fields. The target image must support cloud-init and the appropriate datasource. See the [LXD datasource reference](https://docs.cloud-init.io/en/latest/reference/datasources/lxd.html).

Example:

```bash
php examples/lxd.php
```

## MAAS

```php
use CloudInit\Datasources\Maas;

$maas = Maas::make()
    ->setHostname('maas-web')
    ->setPackageUpdate()
    ->appendPackages(['nginx']);

$yaml = $maas->renderString();
$parameters = $maas->toDeployParameters();
$maas->renderYaml(__DIR__, 'maas-user-data.yaml');
```

`Maas` extends `UserData`, so all existing fluent user-data methods remain available. `renderString()` and `renderYaml()` produce raw cloud-config. `toDeployParameters()` returns `['user_data' => '<base64>']` for the [MAAS machine deploy operation](https://canonical.com/maas/docs/latest/reference/cli-reference/machine/). Do not base64-encode this value a second time.

Pass the returned parameter using your authenticated MAAS client. The API documents form parameters for deployment; the returned PHP array is not a complete HTTP request. Machine selection, authentication, allocation, metadata, and network configuration remain managed through MAAS. See the [MAAS API reference](https://canonical.com/maas/docs/latest/reference/api-reference/api-v2-generated/).

The example prints readable cloud-config by default. Use `--base64` to print the encoded deployment parameter. Neither mode deploys anything:

```bash
php examples/maas.php
php examples/maas.php --base64
```

## WSL

```php
use CloudInit\Datasources\Wsl;
use CloudInit\MetaData;

$wsl = Wsl::make()
    ->setInstanceName('Ubuntu-24.04')
    ->setPackageUpdate()
    ->appendPackages(['git', 'curl'])
    ->setMetaData(MetaData::make()->setInstanceId('iid-wsl-development'));

$yaml = $wsl->renderString();
$paths = $wsl->writeFiles(__DIR__);
```

`Wsl` extends `UserData`. `writeFiles($directory)` writes `<InstanceName>.user-data` and, only when `setMetaData()` is called, `<InstanceName>.meta-data`. It returns their absolute paths under the `user-data` and optional `meta-data` keys. The instance name affects filenames, not the Linux hostname. Missing or invalid names raise `WslInstanceNameInvalidException`. Metadata is copied when assigned and must contain an instance ID.

Use an existing `.cloud-init` directory inside the Windows user profile, passed as a path accessible to the PHP process. When generating elsewhere, copy the files there before the target instance's first initialization. `renderYaml($directory, $filename)` remains available for an explicitly chosen filename. Writes are independent and overwrite existing files. See the [WSL setup guide](https://documentation.ubuntu.com/wsl/latest/howto/cloud-init/).

WSL must have cloud-init available and integrated into its boot process, with interoperability and automount enabled. WSL does not support network-config, and its datasource does not accept user-supplied vendor-data. The metadata helper is intended for instance identity, not a full NoCloud metadata contract. See the [WSL datasource reference](https://docs.cloud-init.io/en/latest/reference/datasources/wsl.html).

Without arguments, the example previews user-data and meta-data as a named YAML stream. With a directory, it writes the separate files and reports their paths. It does not discover a Windows profile or start WSL:

```bash
php examples/wsl.php
mkdir -p /tmp/wsl-seed
php examples/wsl.php /tmp/wsl-seed Ubuntu-24.04
```

## Adapter option index

- `config.cloud-init.user-data` / `config.user.user-data` → `Lxd::setUserData()`.
- `config.cloud-init.vendor-data` / `config.user.vendor-data` → `Lxd::setVendorData()`.
- `config.cloud-init.network-config` / `config.user.network-config` → `Lxd::setNetworkConfig()`.
- Modern or legacy LXD prefix → `Lxd::useLegacyKeys()`.
- MAAS deploy `user_data` → `Maas::toDeployParameters()`.
- WSL `<InstanceName>.user-data` → `Wsl::setInstanceName()` and `writeFiles()`.
- WSL `<InstanceName>.meta-data` → `Wsl::setMetaData()` and `writeFiles()`.

All adapters support string rendering, explicit file rendering, and fluent conditionals. General user-data options and validation behavior are documented in the [README](../README.md).
