<?php

declare(strict_types=1);

namespace CloudInit;

use CloudInit\Concerns\Conditionable;
use CloudInit\Concerns\RendersYaml;
use CloudInit\Exceptions\CommandInvalidException;
use CloudInit\Exceptions\FqdnInvalidException;
use CloudInit\Exceptions\HostnameInvalidException;
use CloudInit\Exceptions\LocaleInvalidException;
use CloudInit\Exceptions\PackageInvalidException;
use CloudInit\Exceptions\SshKeyInvalidException;
use CloudInit\Exceptions\TimezoneInvalidException;
use CloudInit\Support\Validation;
use Stringable;

/**
 * Build cloud-config user-data with explicit, fluent and typed options.
 *
 * @phpstan-consistent-constructor
 *
 * @phpstan-type Configuration array{
 *     hostname?: string,
 *     fqdn?: string,
 *     timezone?: string,
 *     locale?: string,
 *     preserve_hostname?: bool,
 *     package_update?: bool,
 *     package_upgrade?: bool,
 *     ssh_pwauth?: bool,
 *     disable_root?: bool,
 *     ssh_authorized_keys?: list<string>,
 *     packages?: list<string>,
 *     users?: list<'default'|array<string, string|bool|list<string>>>,
 *     write_files?: list<array<string, string|bool>>,
 *     runcmd?: list<string|non-empty-list<string>>,
 *     bootcmd?: list<string|non-empty-list<string>>
 * }
 */
class UserData implements Stringable
{
    use Conditionable;
    use RendersYaml;

    /**
     * Validated options ready for YAML serialization.
     *
     * @var Configuration
     */
    private array $options = [];

    /**
     * Create a builder with no configured options.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Create an empty builder; unset options are omitted from output.
     *
     * @return static
     */
    public static function make(): static
    {
        return new static();
    }

    /**
     * Package this user-data with metadata and networking in one YAML document.
     *
     * The package contains named document strings for application transport.
     * It is not a directly consumable NoCloud seed or cloud-config document.
     *
     * @param  \CloudInit\MetaData  $metaData  Metadata with an explicit instance ID.
     * @param  \CloudInit\NetworkConfig  $networkConfig  Configured Version 2 network document.
     * @return \CloudInit\Chunk
     *
     * @throws \CloudInit\Exceptions\ValidationException
     * @throws \CloudInit\Exceptions\YamlRenderException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/datasources/nocloud.html
     */
    public function chunk(MetaData $metaData, NetworkConfig $networkConfig): Chunk
    {
        return new Chunk($this, $metaData, $networkConfig);
    }

    /**
     * Set the machine hostname.
     *
     * Cloud-config option: hostname.
     *
     * @param  string  $hostname  Non-blank hostname, for example web-01.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\HostnameInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#set-hostname
     */
    public function setHostname(string $hostname): self
    {
        Validation::notBlank($hostname, 'hostname', HostnameInvalidException::class);
        $this->options['hostname'] = $hostname;

        return $this;
    }

    /**
     * Set the fully qualified domain name.
     *
     * Cloud-config option: fqdn.
     *
     * @param  string  $fqdn  Non-blank fully qualified name, for example web-01.example.com.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\FqdnInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#set-hostname
     */
    public function setFqdn(string $fqdn): self
    {
        Validation::notBlank($fqdn, 'fqdn', FqdnInvalidException::class);
        $this->options['fqdn'] = $fqdn;

        return $this;
    }

    /**
     * Set the system timezone.
     *
     * Cloud-config option: timezone.
     *
     * @param  string  $timezone  IANA timezone recognized by PHP, for example America/Sao_Paulo.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\TimezoneInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#timezone
     */
    public function setTimezone(string $timezone): self
    {
        Validation::notBlank($timezone, 'timezone', TimezoneInvalidException::class);
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), true)) {
            throw new TimezoneInvalidException('Timezone must be a recognized IANA identifier.');
        }

        $this->options['timezone'] = $timezone;

        return $this;
    }

    /**
     * Set the system locale.
     *
     * Cloud-config option: locale.
     *
     * @param  string  $locale  Non-blank locale identifier, for example pt_BR.UTF-8.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\LocaleInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#locale
     */
    public function setLocale(string $locale): self
    {
        Validation::notBlank($locale, 'locale', LocaleInvalidException::class);
        $this->options['locale'] = $locale;

        return $this;
    }

    /**
     * Control whether cloud-init preserves the existing hostname.
     *
     * Cloud-config option: preserve_hostname.
     *
     * @param  bool  $preserve_hostname  True to preserve the current hostname; false to allow updates.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#set-hostname
     */
    public function setPreserveHostname(bool $preserve_hostname = true): self
    {
        $this->options['preserve_hostname'] = $preserve_hostname;

        return $this;
    }

    /**
     * Update the package index before installation.
     *
     * Cloud-config option: package_update.
     *
     * @param  bool  $package_update  Whether to request a package index update.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install
     */
    public function setPackageUpdate(bool $package_update = true): self
    {
        $this->options['package_update'] = $package_update;

        return $this;
    }

    /**
     * Upgrade installed packages.
     *
     * Cloud-config option: package_upgrade.
     *
     * @param  bool  $package_upgrade  Whether to request upgrades of installed packages.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install
     */
    public function setPackageUpgrade(bool $package_upgrade = true): self
    {
        $this->options['package_upgrade'] = $package_upgrade;

        return $this;
    }

    /**
     * Control SSH password authentication.
     *
     * Cloud-config option: ssh_pwauth.
     *
     * @param  bool  $ssh_pwauth  True to enable SSH password authentication; false to disable it.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#set-passwords
     */
    public function setSshPasswordAuthentication(bool $ssh_pwauth = true): self
    {
        $this->options['ssh_pwauth'] = $ssh_pwauth;

        return $this;
    }

    /**
     * Control root SSH login disabling.
     *
     * Cloud-config option: disable_root.
     *
     * @param  bool  $disable_root  Whether cloud-init should disable root SSH login.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#ssh
     */
    public function setDisableRoot(bool $disable_root = true): self
    {
        $this->options['disable_root'] = $disable_root;

        return $this;
    }

    /**
     * Replace the ssh_authorized_keys list, preserving order and duplicates.
     *
     * Cloud-config option: ssh_authorized_keys.
     *
     * @param  list<string>  $values  Public key strings; an empty list clears the configured list.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\SshKeyInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#ssh
     */
    public function setSshKeys(array $values): self
    {
        $validated = Validation::strings($values, 'ssh_authorized_keys', SshKeyInvalidException::class);
        $this->options['ssh_authorized_keys'] = $validated;

        return $this;
    }

    /**
     * Append to the ssh_authorized_keys list, preserving order and duplicates.
     *
     * Cloud-config option: ssh_authorized_keys.
     *
     * @param  list<string>  $values  Public key strings to append; an empty list adds nothing.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\SshKeyInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#ssh
     */
    public function appendSshKeys(array $values): self
    {
        $validated = Validation::strings($values, 'ssh_authorized_keys', SshKeyInvalidException::class);
        $this->options['ssh_authorized_keys'] = [...($this->options['ssh_authorized_keys'] ?? []), ...$validated];

        return $this;
    }

    /**
     * Replace the packages list, preserving order and duplicates.
     *
     * Cloud-config option: packages.
     *
     * @param  list<string>  $values  Package names; version tuples and manager-specific objects are not accepted.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\PackageInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install
     */
    public function setPackages(array $values): self
    {
        $validated = Validation::strings($values, 'packages', PackageInvalidException::class);
        $this->options['packages'] = $validated;

        return $this;
    }

    /**
     * Append to the packages list, preserving order and duplicates.
     *
     * Cloud-config option: packages.
     *
     * @param  list<string>  $values  Package names to append; an empty list adds nothing.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\PackageInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install
     */
    public function appendPackages(array $values): self
    {
        $validated = Validation::strings($values, 'packages', PackageInvalidException::class);
        $this->options['packages'] = [...($this->options['packages'] ?? []), ...$validated];

        return $this;
    }

    /**
     * Append sudo to the packages list, preserving existing entries.
     *
     * Cloud-config option: packages. Repeated calls preserve duplicates.
     *
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install
     */
    public function appendSudo(): self
    {
        return $this->appendPackages(['sudo']);
    }

    /**
     * Append curl and wget to the packages list, preserving existing entries.
     *
     * Cloud-config option: packages. Repeated calls preserve duplicates.
     *
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install
     */
    public function appendCurlAndWget(): self
    {
        return $this->appendPackages(['curl', 'wget']);
    }

    /**
     * Append qemu-guest-agent to the packages list, preserving existing entries.
     *
     * Cloud-config option: packages. Repeated calls preserve duplicates.
     *
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#package-update-upgrade-install
     */
    public function appendQemuGuestAgent(): self
    {
        return $this->appendPackages(['qemu-guest-agent']);
    }

    /**
     * Append a snapshot of a user entry. Later changes to the entry are independent.
     *
     * Cloud-config option: users[].
     *
     * @param  User  $entry  User entry copied into the configuration.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public function appendUser(User $entry): self
    {
        $this->options['users'][] = $entry->toArray();

        return $this;
    }

    /**
     * Append a snapshot of a file entry. Later changes to the entry are independent.
     *
     * Cloud-config option: write_files[].
     *
     * @param  File  $entry  File entry copied into the configuration.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#write-files
     */
    public function appendFile(File $entry): self
    {
        $this->options['write_files'][] = $entry->toArray();

        return $this;
    }

    /**
     * Include the distribution's default user alongside custom users.
     *
     * Cloud-config option: users[] = default.
     *
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public function appendDefaultUser(): self
    {
        $this->options['users'][] = 'default';

        return $this;
    }

    /**
     * Append a command for final stage on first boot.
     * Strings are interpreted by a shell; lists represent executable and arguments.
     *
     * Cloud-config option: runcmd[].
     *
     * @param  string|non-empty-list<string>  $command  Shell command or executable/argument list; arguments may be empty strings.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\CommandInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#runcmd
     */
    public function appendRunCommand(string|array $command): self
    {
        $this->options['runcmd'][] = $this->validateCommand($command);

        return $this;
    }

    /**
     * Append a command for early boot, usually on every boot.
     * Strings are interpreted by a shell; lists represent executable and arguments.
     *
     * Cloud-config option: bootcmd[].
     *
     * @param  string|non-empty-list<string>  $command  Shell command or executable/argument list; arguments may be empty strings.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\CommandInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#bootcmd
     */
    public function appendBootCommand(string|array $command): self
    {
        $this->options['bootcmd'][] = $this->validateCommand($command);

        return $this;
    }

    /**
     * A snapshot using canonical cloud-init option names.
     *
     * @return array<string, mixed>
     *
     * @phpstan-return Configuration
     */
    public function toArray(): array
    {
        return $this->options;
    }

    /**
     * Identify cloud-config user-data for cloud-init.
     *
     * @return string
     *
     * @see https://docs.cloud-init.io/en/latest/explanation/format.html#cloud-config-data
     */
    protected function yamlHeader(): string
    {
        return "#cloud-config\n";
    }

    /**
     * Validate shell commands or argument lists, allowing empty individual arguments.
     *
     * @param  string|array<mixed>  $command
     * @return string|non-empty-list<string>
     *
     * @throws \CloudInit\Exceptions\CommandInvalidException
     */
    private function validateCommand(string|array $command): string|array
    {
        if (is_string($command)) {
            Validation::notBlank($command, 'Command', CommandInvalidException::class);

            return $command;
        }

        if (!array_is_list($command) || $command === []) {
            throw new CommandInvalidException('Command must be a non-empty argument list.');
        }

        $arguments = [];

        foreach ($command as $argument) {
            if (!is_string($argument)) {
                throw new CommandInvalidException('Command arguments must be strings.');
            }

            $arguments[] = $argument;
        }

        Validation::notBlank($arguments[0], 'Command executable', CommandInvalidException::class);

        return $arguments;
    }
}
