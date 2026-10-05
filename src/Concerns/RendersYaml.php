<?php

declare(strict_types=1);

namespace CloudInit\Concerns;

use CloudInit\Exceptions\DirectoryInvalidException;
use CloudInit\Exceptions\FilenameInvalidException;
use CloudInit\Exceptions\FileWriteException;
use CloudInit\Exceptions\YamlRenderException;
use CloudInit\Support\Validation;
use Symfony\Component\Yaml\Exception\DumpException;
use Symfony\Component\Yaml\Yaml;

/**
 * Share serialization and filesystem validation across cloud-init documents.
 */
trait RendersYaml
{
    /**
     * Render this document with its appropriate header and a trailing newline.
     * Command argument lists use inline YAML sequences to keep each command together.
     * Mapping entries start on the same line as their list marker.
     *
     * @return string
     *
     * @throws \CloudInit\Exceptions\ValidationException
     * @throws \CloudInit\Exceptions\YamlRenderException
     */
    public function renderString(): string
    {
        return $this->yamlHeader().$this->renderOptions($this->toArray());
    }

    /**
     * Serialize a document mapping independently of each builder's array shape.
     *
     * @param  array<string, mixed>  $options  Validated YAML options.
     * @return string
     *
     * @throws \CloudInit\Exceptions\YamlRenderException
     */
    private function renderOptions(array $options): string
    {
        try {
            $yaml = $options === [] ? "{}\n" : '';

            foreach ($options as $key => $value) {
                $inline = in_array($key, ['runcmd', 'bootcmd'], true) ? 2 : 10;
                $yaml .= Yaml::dump([$key => $value], $inline, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK | Yaml::DUMP_EXCEPTION_ON_INVALID_TYPE | Yaml::DUMP_COMPACT_NESTED_MAPPING);
            }
        } catch (DumpException $exception) {
            throw new YamlRenderException('Unable to render the YAML document.', previous: $exception);
        }

        return $yaml;
    }

    /**
     * Alias for renderString(), kept for existing consumers.
     *
     * @return string
     *
     * @throws \CloudInit\Exceptions\ValidationException
     * @throws \CloudInit\Exceptions\YamlRenderException
     *
     * @see renderString()
     */
    public function render(): string
    {
        return $this->renderString();
    }

    /**
     * Write the rendered YAML to a file in an existing local directory.
     * Existing file contents are replaced; the filename is used as provided.
     *
     * @param  string  $directory  Existing destination directory, absolute or relative.
     * @param  string  $filename  File basename, including the desired extension.
     * @return string The absolute path of the written file.
     *
     * @throws \CloudInit\Exceptions\DirectoryInvalidException
     * @throws \CloudInit\Exceptions\FilenameInvalidException
     * @throws \CloudInit\Exceptions\FileWriteException
     * @throws \CloudInit\Exceptions\ValidationException
     * @throws \CloudInit\Exceptions\YamlRenderException
     */
    public function renderYaml(string $directory, string $filename): string
    {
        Validation::notBlank($directory, 'Directory', DirectoryInvalidException::class);
        Validation::notBlank($filename, 'Filename', FilenameInvalidException::class);
        if (str_contains($directory, "\0") || str_contains($directory, '://')) {
            throw new DirectoryInvalidException('Directory must be a local path without null bytes.');
        }

        if ($filename === '.' || $filename === '..' || strpbrk($filename, "/\\\0") !== false) {
            throw new FilenameInvalidException('Filename must be a basename without path separators or null bytes.');
        }

        $resolved = realpath($directory);
        if ($resolved === false || !is_dir($resolved)) {
            throw new DirectoryInvalidException('Destination directory must exist.');
        }

        $path = rtrim($resolved, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
        $yaml = $this->renderString();
        if (@file_put_contents($path, $yaml, LOCK_EX) !== strlen($yaml)) {
            throw new FileWriteException("Unable to write the complete YAML document to {$path}.");
        }

        return $path;
    }

    /**
     * Render the builder when converted to a string.
     *
     * @return string
     *
     * @throws \CloudInit\Exceptions\ValidationException
     * @throws \CloudInit\Exceptions\YamlRenderException
     *
     * @see renderString()
     */
    public function __toString(): string
    {
        return $this->renderString();
    }

    /**
     * Return the prefix for this document format.
     *
     * @return string
     */
    protected function yamlHeader(): string
    {
        return '';
    }
}
