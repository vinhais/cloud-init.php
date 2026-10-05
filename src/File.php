<?php

declare(strict_types=1);

namespace CloudInit;

use CloudInit\Concerns\Conditionable;
use CloudInit\Exceptions\FileOwnerInvalidException;
use CloudInit\Exceptions\FilePathInvalidException;
use CloudInit\Exceptions\FilePermissionsInvalidException;

/**
 * A plain-text write_files entry; content is never executed by this library.
 */
final class File
{
    use Conditionable;

    /**
     * Explicitly configured file options.
     *
     * @var array<string, string|bool>
     */
    private array $options;

    /**
     * Create a file entry using an absolute destination path.
     *
     * Cloud-config option: write_files[].path, write_files[].content.
     *
     * @param  string  $path  Absolute destination path inside the provisioned machine.
     * @param  string  $content  Plain-text content; an empty string creates an empty file.
     * @return void
     *
     * @throws \CloudInit\Exceptions\FilePathInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#write-files
     */
    private function __construct(string $path, string $content)
    {
        if (!str_starts_with($path, '/') || str_contains($path, "\0") || $path === '/') {
            throw new FilePathInvalidException('File path must be an absolute file path without null bytes.');
        }

        $this->options = ['path' => $path, 'content' => $content];
    }

    /**
     * Start a file entry; an empty content string creates an empty file.
     *
     * Cloud-config option: write_files[].path, write_files[].content.
     *
     * @param  string  $path  Absolute destination path inside the provisioned machine.
     * @param  string  $content  Plain-text content; an empty string creates an empty file.
     * @return static
     *
     * @throws \CloudInit\Exceptions\FilePathInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#write-files
     */
    public static function make(string $path, string $content = ''): self
    {
        return new self($path, $content);
    }

    /**
     * Replace the file content.
     *
     * Cloud-config option: write_files[].content.
     *
     * @param  string  $content  Plain-text content; an empty string is allowed.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#write-files
     */
    public function setContent(string $content): self
    {
        $this->options['content'] = $content;

        return $this;
    }

    /**
     * Set the owner as user:group.
     *
     * Cloud-config option: write_files[].owner.
     *
     * @param  string  $owner  Owner in user:group notation, for example root:root.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\FileOwnerInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#write-files
     */
    public function setOwner(string $owner): self
    {
        if (preg_match('/^[^\s:]+:[^\s:]+$/D', $owner) !== 1) {
            throw new FileOwnerInvalidException('Owner must use user:group notation.');
        }

        $this->options['owner'] = $owner;

        return $this;
    }

    /**
     * Set permissions as an octal string, preserving leading zeros.
     *
     * Cloud-config option: write_files[].permissions.
     *
     * @param  string  $permissions  Three or four octal digits as a string, for example 0644.
     * @return $this
     *
     * @throws \CloudInit\Exceptions\FilePermissionsInvalidException
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#write-files
     */
    public function setPermissions(string $permissions): self
    {
        if (preg_match('/^[0-7]{3,4}$/D', $permissions) !== 1) {
            throw new FilePermissionsInvalidException('Permissions must contain three or four octal digits.');
        }

        $this->options['permissions'] = $permissions;

        return $this;
    }

    /**
     * Append to an existing file instead of replacing its contents.
     *
     * Cloud-config option: write_files[].append.
     *
     * @param  bool  $append  True to append content; false to replace existing content.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#write-files
     */
    public function setAppend(bool $append = true): self
    {
        $this->options['append'] = $append;

        return $this;
    }

    /**
     * Write during the final stage, after packages and users are configured.
     *
     * Cloud-config option: write_files[].defer.
     *
     * @param  bool  $defer  True to defer writing until the final stage; false for normal timing.
     * @return $this
     *
     * @see https://docs.cloud-init.io/en/latest/reference/modules.html#write-files
     */
    public function setDefer(bool $defer = true): self
    {
        $this->options['defer'] = $defer;

        return $this;
    }

    /**
     * A snapshot of this file entry.
     *
     * @return array<string, string|bool>
     */
    public function toArray(): array
    {
        return $this->options;
    }
}
