# Agent guide

These instructions apply throughout this repository. Read the relevant source and README before changing behavior.

## Project and layout

- Package: `vinhais/cloud-init.php`; namespace: `CloudInit\`; PHP: `^8.5`; autoloading: PSR-4 from `src/`.
- This is a standalone Composer library with Laravel-style fluent conventions, not a Laravel application. Symfony YAML handles serialization.
- `src/UserData.php` implements cloud-config. `src/CloudInit.php` is the compatible entry point extending `UserData`.
- `src/MetaData.php` and `src/NetworkConfig.php` build separate NoCloud documents. `src/Network/` contains Ethernet and route builders.
- `src/User.php` and `src/File.php` describe entries inside user-data. Traits belong in `src/Concerns/`; shared validators in `src/Support/`; domain exceptions in `src/Exceptions/`.
- `src/Datasources/` implements LXD config fragments, MAAS base64 deploy parameters, and WSL per-instance files. NoCloud remains supported; all other adapters are WIP as listed in `docs/datasources.md`. Do not expose WIP placeholders as implemented functionality.
- `tests/` contains PHPUnit tests. `examples/` contains runnable examples. `docs/reference.md` documents supported options and exceptions; README contains introductory examples.

## Using the library

After `composer install`, load `vendor/autoload.php` in standalone scripts. For example:

```php
use CloudInit\MetaData;
use CloudInit\Network\Ethernet;
use CloudInit\NetworkConfig;
use CloudInit\UserData;

$user = UserData::make()->setHostname('web-01')->appendPackages(['nginx']);
$meta = MetaData::make()->setInstanceId('iid-web-01')->setLocalHostname('web-01');
$network = NetworkConfig::make()->setEthernet(Ethernet::make('eth0')->setDhcp4());
$yaml = $user->renderString();
// $directory must already exist; these calls overwrite files with the same names.
$user->renderYaml($directory, 'user-data');
$meta->renderYaml($directory, 'meta-data');
$network->renderYaml($directory, 'network-config');
```

- `UserData::chunk($metaData, $networkConfig)` returns a `Chunk` containing rendered snapshots under `user-data`, `meta-data`, and `network-config`. Its single YAML file is an application transport format, not native cloud-init input. Never flatten metadata/networking into user-data or add an outer cloud-config header.
- Only user-data receives `#cloud-config`. NoCloud metadata uses `instance-id` and `local-hostname`. Standalone network-config has top-level `version: 2` and `ethernets`, with no `network` wrapper.
- `renderString()` only returns content; `renderYaml($directory, $filename)` writes it and returns the absolute path. `render()` is a compatibility alias. Reuse `Concerns\RendersYaml` for document serialization.
- Builders mutate and return themselves. `set*` replaces; `append*` accumulates in order. Preserve explicit false values, empty lists, and snapshot semantics for nested builders. Conditional callbacks receive the builder and their returns are ignored.
- LXD wraps document strings in a `config` map; never invent `cloud-init.meta-data`. MAAS `toDeployParameters()` encodes user-data exactly once; metadata/networking stay managed by MAAS. WSL `writeFiles()` uses the instance name for filenames, optionally writes metadata, and does not support vendor-data/network-config.
- Metadata requires an instance ID before export. Network output requires a configured Ethernet interface. Read `docs/reference.md` for the supported subset; do not claim support for all cloud-init/Netplan features.

## Editing conventions

- Use `declare(strict_types=1)`, native parameter/return types, typed properties, and precise PHPDoc list/array shapes. Validate all list entries before mutating state.
- Follow Laravel-style PHPDoc: a summary, blank line, `@param` for every parameter, `@return` (including `void` and `$this`), applicable `@throws`, and official `@see` links for cloud-init options. Describe the emitted YAML key and accepted values. Keep docs consistent with native signatures.
- Use four-space indentation and blank lines between sequential `if`/`foreach` blocks and subsequent statements. Do not add whitespace to blank lines.
- Keep documentation, comments, exception messages, and examples in English. Avoid adding emojis; preserve the explicitly chosen package description.
- Add specific validation exceptions extending `ValidationException`; reuse existing domain exceptions where appropriate. Operational failures use runtime exceptions such as `FileWriteException` and `YamlRenderException`. Preserve underlying causes when wrapping errors.
- Preserve existing public APIs, especially `CloudInit::make()`. Use Symfony YAML rather than hand-built YAML. Prefer existing helpers and native PHP over new dependencies or speculative abstractions.
- Check [cloud-init modules](https://docs.cloud-init.io/en/latest/reference/modules.html), [NoCloud](https://docs.cloud-init.io/en/latest/reference/datasources/nocloud.html), and [network Version 2](https://docs.cloud-init.io/en/latest/reference/network-config-format-v2.html) before implementing new options. Update the option index in `docs/reference.md`, PHPDoc, examples, and relevant tests together.
- Do not edit `vendor/` or generated caches. Do not commit, push, or publish unless explicitly requested.

## Verification

- Run `composer validate --strict` for package metadata changes. Run `composer test` and `composer analyse` (or `composer check`) after behavior/type changes. PHPStan uses level `max`; fix errors rather than adding baselines or suppressions.
- If the sandbox prevents PHPStan from opening a local worker socket, use `php vendor/bin/phpstan analyse --debug` for sequential analysis.
- For example changes, run `php examples/web-server.php` and `php examples/nocloud.php <existing-temporary-directory>`. Also run the relevant `examples/lxd.php`, `examples/maas.php`, or `examples/wsl.php <existing-temporary-directory>` for adapter changes. Verify argument-free previews too; use `examples/maas.php --base64` to check MAAS transport encoding. Check generated documents and decode the MAAS value without provisioning infrastructure.
- Cover canonical YAML structure, validation exceptions, failed-batch state preservation, snapshots, and string/file rendering when those behaviors change. Documentation-only edits need relevant consistency checks, not new behavior tests.
- Report what was verified and any unavailable checks. Unit tests and YAML parsing do not establish successful provisioning on a real image.

- `tests/ExamplesTest.php` launches real CLI processes from `examples/`. Keep no-argument previews usable and invalid CLI input free of uncaught traces. Use `examples/diagnose.php` to investigate differences in loaded code or YAML formatting before blaming caches.
