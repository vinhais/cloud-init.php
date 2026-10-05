# API reference

Each method's PHPDoc includes its complete signature, corresponding YAML key, and an `@see` reference to the official documentation. The lists below index implemented options; they do not imply support for every cloud-init module.

Parameter types describe what this library accepts. If the official schema supports additional forms, such as package versions or multiple sudo rules, that does not automatically extend the PHP signature. Omitted options follow image and cloud-init behavior; PHP argument defaults apply only when the corresponding method is called.

### CloudInit / UserData

- [`hostname`](https://docs.cloud-init.io/en/latest/reference/modules.html#set-hostname) → `CloudInit::setHostname()`.
- [`fqdn`](https://docs.cloud-init.io/en/latest/reference/modules.html#set-hostname) → `CloudInit::setFqdn()`.
- [`timezone`](https://docs.cloud-init.io/en/latest/reference/modules.html#timezone) → `CloudInit::setTimezone()`.
- [`locale`](https://docs.cloud-init.io/en/latest/reference/modules.html#locale) → `CloudInit::setLocale()`.
- [`preserve_hostname`](https://docs.cloud-init.io/en/latest/reference/modules.html#set-hostname) → `CloudInit::setPreserveHostname()`.
- [`package_update`](https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install) → `CloudInit::setPackageUpdate()`.
- [`package_upgrade`](https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install) → `CloudInit::setPackageUpgrade()`.
- [`ssh_pwauth`](https://docs.cloud-init.io/en/latest/reference/modules.html#set-passwords) → `CloudInit::setSshPasswordAuthentication()`.
- [`disable_root`](https://docs.cloud-init.io/en/latest/reference/modules.html#ssh) → `CloudInit::setDisableRoot()`.
- [`ssh_authorized_keys`](https://docs.cloud-init.io/en/latest/reference/modules.html#ssh) → `CloudInit::setSshKeys()`.
- [`ssh_authorized_keys`](https://docs.cloud-init.io/en/latest/reference/modules.html#ssh) → `CloudInit::appendSshKeys()`.
- [`packages`](https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install) → `CloudInit::setPackages()`.
- [`packages`](https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install) → `CloudInit::appendPackages()`.
- [`packages`](https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install) → `CloudInit::appendSudo()` appends `sudo`.
- [`packages`](https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install) → `CloudInit::appendCurlAndWget()` appends `curl` and `wget`.
- [`packages`](https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install) → `CloudInit::appendQemuGuestAgent()` appends `qemu-guest-agent`.
- [`users[]`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `CloudInit::appendUser()`.
- [`write_files[]`](https://docs.cloud-init.io/en/latest/reference/modules.html#write-files) → `CloudInit::appendFile()`.
- [`users[] = default`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `CloudInit::appendDefaultUser()`.
- [`runcmd[]`](https://docs.cloud-init.io/en/latest/reference/modules.html#runcmd) → `CloudInit::appendRunCommand()`.
- [`bootcmd[]`](https://docs.cloud-init.io/en/latest/reference/modules.html#bootcmd) → `CloudInit::appendBootCommand()`.

### User

- [`users[].name`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `User::make()`.
- [`users[].shell`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `User::setShell()`.
- [`users[].homedir`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `User::setHomeDirectory()`.
- [`users[].gecos`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `User::setGecos()`.
- [`users[].primary_group`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `User::setPrimaryGroup()`.
- [`users[].lock_passwd`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `User::setLockPassword()`.
- [`users[].sudo`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `User::setSudo()`.
- [`users[].groups`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `User::setGroups()`.
- [`users[].ssh_authorized_keys`](https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups) → `User::setSshKeys()`.

### File

- [`write_files[].path, write_files[].content`](https://docs.cloud-init.io/en/latest/reference/modules.html#write-files) → `File::make()`.
- [`write_files[].content`](https://docs.cloud-init.io/en/latest/reference/modules.html#write-files) → `File::setContent()`.
- [`write_files[].owner`](https://docs.cloud-init.io/en/latest/reference/modules.html#write-files) → `File::setOwner()`.
- [`write_files[].permissions`](https://docs.cloud-init.io/en/latest/reference/modules.html#write-files) → `File::setPermissions()`.
- [`write_files[].append`](https://docs.cloud-init.io/en/latest/reference/modules.html#write-files) → `File::setAppend()`.
- [`write_files[].defer`](https://docs.cloud-init.io/en/latest/reference/modules.html#write-files) → `File::setDefer()`.

For other options and modules, see the [complete cloud-init reference](https://docs.cloud-init.io/en/latest/reference/modules.html).

### MetaData

- `instance-id` → `MetaData::setInstanceId()`.
- `local-hostname` → `MetaData::setLocalHostname()`.

Reference: [NoCloud](https://docs.cloud-init.io/en/latest/reference/datasources/nocloud.html).

### NetworkConfig and Ethernet

- `version: 2` → provided by `NetworkConfig`.
- `ethernets.<id>` → `NetworkConfig::setEthernet()` and `Ethernet::make($id)`.
- `dhcp4` / `dhcp6` → `Ethernet::setDhcp4()` / `setDhcp6()`.
- `addresses` → `Ethernet::setAddresses()`.
- `nameservers.addresses` / `nameservers.search` → `Ethernet::setNameservers()` / `setSearchDomains()`.
- `match.macaddress` / `set-name` → `Ethernet::setMatchMacAddress()` / `setName()`.
- `mtu` → `Ethernet::setMtu()`.
- `routes[]` → `Ethernet::appendRoute()`.
- `routes[].to` / `routes[].via` → `Route::make($to, $via)`.
- `routes[].metric` → `Route::setMetric()`.

Reference: [Network Version 2](https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html).

### Platform-specific output

See the [adapter option index](datasources.md#adapter-option-index) for LXD configuration keys, the MAAS `user_data` parameter, and WSL filenames.

## Chunk

`UserData::chunk(MetaData $metaData, NetworkConfig $networkConfig): Chunk` captures all three documents as rendered string snapshots. `CloudInit` inherits this method.

- `Chunk::toArray()` returns `user-data`, `meta-data`, and `network-config` strings.
- `Chunk::renderString()` returns one YAML mapping with literal document strings.
- `Chunk::renderYaml($directory, $filename)` writes the package using the usual output validation.
- `Chunk::render()` and string conversion also render the package.

Only the embedded user-data receives a `#cloud-config` header. Metadata and network validation still apply. The package is an application-level transport format; NoCloud requires the extracted documents as separate files. Later edits to source builders do not affect an existing chunk.

## Exceptions

Exceptions live in the `CloudInit\Exceptions` namespace. Each validated field has a specific exception:

- Configuration: `HostnameInvalidException`, `FqdnInvalidException`, `TimezoneInvalidException`, and `LocaleInvalidException`.
- Lists: `SshKeyInvalidException` and `PackageInvalidException`, covering both list structure and individual entries.
- Users: `UserNameInvalidException`, `ShellInvalidException`, `HomeDirectoryInvalidException`, `GecosInvalidException`, `GroupInvalidException`, and `SudoRuleInvalidException`. User SSH keys also use `SshKeyInvalidException`.
- Configured files: `FilePathInvalidException`, `FileOwnerInvalidException`, and `FilePermissionsInvalidException`.
- Commands: `CommandInvalidException`.
- Metadata: `InstanceIdInvalidException`; the local hostname uses `HostnameInvalidException`.
- Networking: `NetworkConfigInvalidException`, `NetworkInterfaceInvalidException`, `NetworkAddressInvalidException`, `NameserverInvalidException`, `NetworkRouteInvalidException`, and `NetworkMtuInvalidException`.
- Datasource adapters: `LxdConfigInvalidException` and `WslInstanceNameInvalidException`.
- Output destinations: `DirectoryInvalidException` and `FilenameInvalidException`.

All the exceptions above extend `ValidationException`, which extends `InvalidArgumentException`. For example, `SshKeyInvalidException` identifies malformed lists or invalid entries without cryptographically validating a key.

Operational failures use `FileWriteException` or `YamlRenderException`, both extending `RuntimeException`. `YamlRenderException` preserves the original Symfony exception in `getPrevious()`. Native type errors remain `TypeError`.

```php
use CloudInit\CloudInit;
use CloudInit\Exceptions\SshKeyInvalidException;
use CloudInit\Exceptions\ValidationException;
use CloudInit\Exceptions\FileWriteException;
use CloudInit\Exceptions\YamlRenderException;

try {
    CloudInit::make()
        ->appendSshKeys([''])
        ->renderYaml(__DIR__, 'user-data.yaml');
} catch (SshKeyInvalidException $exception) {
    // Handle the invalid SSH key list specifically.
} catch (ValidationException $exception) {
    // Handle any other builder validation failure.
} catch (FileWriteException | YamlRenderException $exception) {
    // Handle serialization or filesystem failures.
}
```
