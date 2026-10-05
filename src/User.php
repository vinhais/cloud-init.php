<?php

declare(strict_types=1);

namespace CloudInit;

use CloudInit\Concerns\Conditionable;
use CloudInit\Exceptions\GecosInvalidException;
use CloudInit\Exceptions\GroupInvalidException;
use CloudInit\Exceptions\HomeDirectoryInvalidException;
use CloudInit\Exceptions\ShellInvalidException;
use CloudInit\Exceptions\SshKeyInvalidException;
use CloudInit\Exceptions\SudoRuleInvalidException;
use CloudInit\Exceptions\UserNameInvalidException;
use CloudInit\Support\Validation;

/**
 * A typed users entry with opt-in configuration and no implicit privilege grants.
 */
final class User
{
    use Conditionable;

    /**
     * Explicitly configured user options.
     *
     * @var array<string, string|bool|list<string>>
     */
    private array $options;

    /**
     * Initialize a user with a non-blank name.
     *
     * Cloud-config option: users[].name.
     *
     * @param  string  $name  Non-blank account name.
     * @return void
     *
     * @throws \CloudInit\Exceptions\UserNameInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    private function __construct(string $name)
    {
        Validation::notBlank($name, 'User name', UserNameInvalidException::class);
        $this->options = ['name' => $name];
    }

    /**
     * Start a user entry.
     *
     * Cloud-config option: users[].name.
     *
     * @param  string  $name  Non-blank account name.
     * @return static
     *
     * @throws \CloudInit\Exceptions\UserNameInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * Set the login shell.
     *
     * Cloud-config option: users[].shell.
     *
     * @param  string  $shell  Non-blank login shell path, for example /bin/bash.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\ShellInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public function setShell(string $shell): self
    {
        Validation::notBlank($shell, 'shell', ShellInvalidException::class);
        $this->options['shell'] = $shell;

        return $this;
    }

    /**
     * Set the home directory.
     *
     * Cloud-config option: users[].homedir.
     *
     * @param  string  $homedir  Non-blank home directory, for example /home/deploy.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\HomeDirectoryInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public function setHomeDirectory(string $homedir): self
    {
        Validation::notBlank($homedir, 'homedir', HomeDirectoryInvalidException::class);
        $this->options['homedir'] = $homedir;

        return $this;
    }

    /**
     * Set the descriptive user information.
     *
     * Cloud-config option: users[].gecos.
     *
     * @param  string  $gecos  Non-blank descriptive account information.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\GecosInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public function setGecos(string $gecos): self
    {
        Validation::notBlank($gecos, 'gecos', GecosInvalidException::class);
        $this->options['gecos'] = $gecos;

        return $this;
    }

    /**
     * Set the primary group.
     *
     * Cloud-config option: users[].primary_group.
     *
     * @param  string  $primary_group  Non-blank primary group name.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\GroupInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public function setPrimaryGroup(string $primary_group): self
    {
        Validation::notBlank($primary_group, 'primary_group', GroupInvalidException::class);
        $this->options['primary_group'] = $primary_group;

        return $this;
    }

    /**
     * Control password locking.
     *
     * Cloud-config option: users[].lock_passwd.
     *
     * @param  bool  $lock_passwd  Whether to lock password-based login for this account.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public function setLockPassword(bool $lock_passwd): self
    {
        $this->options['lock_passwd'] = $lock_passwd;

        return $this;
    }

    /**
     * Set an explicit sudo rule; no sudo access is granted by default.
     *
     * Cloud-config option: users[].sudo.
     *
     * @param  string  $sudo  A non-blank sudo rule; this API accepts a single string.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\SudoRuleInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public function setSudo(string $sudo): self
    {
        Validation::notBlank($sudo, 'sudo', SudoRuleInvalidException::class);
        $this->options['sudo'] = $sudo;

        return $this;
    }

    /**
     * Replace the complete groups list.
     *
     * Cloud-config option: users[].groups.
     *
     * @param  list<string>  $values  Supplementary group names; this API accepts a list of strings.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\GroupInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public function setGroups(array $values): self
    {
        $this->options['groups'] = Validation::strings($values, 'groups', GroupInvalidException::class);

        return $this;
    }

    /**
     * Replace the complete ssh_authorized_keys list.
     *
     * Cloud-config option: users[].ssh_authorized_keys.
     *
     * @param  list<string>  $values  Public key strings assigned to this account.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\SshKeyInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#users-and-groups
     */
    public function setSshKeys(array $values): self
    {
        $this->options['ssh_authorized_keys'] = Validation::strings($values, 'ssh_authorized_keys', SshKeyInvalidException::class);

        return $this;
    }

    /**
     * A snapshot of this user entry.
     *
     * @return array<string, string|bool|list<string>>
     */
    public function toArray(): array
    {
        return $this->options;
    }
}
