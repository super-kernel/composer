<p align="right">
  <strong>English</strong> | <a href="README.zh-CN.md">中文文档</a>
</p>

# SuperKernel Composer Metadata

`super-kernel/composer` reads the root `composer.json` and `composer.lock` metadata and provides package graphs, class maps, and class ownership on demand. Applications load Composer's generated `vendor/autoload.php`; package discovery begins only when a caller requests `PackageManagerProvider` or its DI service. Loading the component does not scan packages, register an SPL loader, or execute package file-autoload declarations.

## Project root and vendor directory

`SuperKernel\Contract\BasePathInterface` is the project root, a `Stringable` value. `Provider\BasePathProvider` supplies it and tries these detectors in order:

1. `NullDetector` recognizes execution inside an application Phar and returns the archive's internal `phar://...` root using [Phar::running()](https://www.php.net/manual/en/phar.running.php).
2. `ComposerScriptDetector` recognizes `COMPOSER_DEV_MODE=0` or `1` and checks Composer's working directory. This marker is supplied for [Composer scripts](https://getcomposer.org/doc/articles/scripts.md).
3. `ScriptDetector` recognizes an existing PHP entry script and checks the directory returned by `getcwd()`.
4. `WorkingDirectoryDetector` checks `getcwd()` without requiring an entry script.
5. `ComponentDetector` checks the current working directory for both `composer.json` and `composer.lock`, supporting standalone use when launched from the component root.

The first supported detector wins; later detectors are not evaluated. When none supports the environment, the provider throws `ProjectRootNotFoundException`. Filesystem detectors inspect only the directory returned by [getcwd()](https://www.php.net/manual/en/function.getcwd.php) and require `composer.json`; the component detector also requires `composer.lock`. They do not traverse parent directories or infer the root from the entry script or installation location, so launch PHP from the project root, including when running a script elsewhere by path. Detection checks file locations without parsing their contents.

The provider accepts an optional ordered iterable of `DetectorInterface` implementations for custom environments; an explicit empty iterable also fails with `ProjectRootNotFoundException`. A detector exposes `support(): bool` and `projectDirectory(): string`, which returns an empty string when unsupported, and must return a valid canonical directory when supported. Wrap an explicit directory string in `Path\BasePath`.

`SuperKernel\Contract\VendorDirInterface` is the separate `Stringable` dependency directory. `Provider\VendorDirProvider::__invoke()` takes `ComposerMetadataInterface` and `BasePathInterface` from the same project. It reads `config.vendor-dir` from the root package, defaults to `vendor`, resolves relative paths against the base path, normalizes absolute and Phar paths, and canonicalizes existing links. It does not create the directory; an invalid configured path raises `ComposerFileException`.

```php
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Provider\BasePathProvider;
use SuperKernel\Composer\Provider\VendorDirProvider;

$root = new BasePathProvider()();
$projectDirectory = (string)$root;
$metadata = new ComposerMetadata($root);
$vendorDir = new VendorDirProvider()($metadata, $root);
$vendorDirectory = (string)$vendorDir;
```

Consumers that need the dependency directory inject `VendorDirInterface` and cast it to a string. If the working directory changes after detection, pass the previously resolved base path explicitly. Inside a Phar, root detection and manifest/lock reading work from the archive, and package caches must already be prepared beneath the vendor directory; the archive provides no writable cache storage.

## Composer metadata

`Metadata\ComposerMetadata` supplies `ComposerMetadataInterface`. It reads fresh metadata from the project's `composer.json` and `composer.lock` through its required `BasePathInterface $basePath` argument, needs both documents but no installed vendor directory, and never consults installation state, dependency manifests, plugins, scripts, or the network. Its single-file reader methods return `ComposerJsonInterface` and `ComposerLockInterface` views.

```php
use SuperKernel\Composer\Constants\PackageType;
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Path\BasePath;

$metadata = new ComposerMetadata(new BasePath('/srv/application'));
$root = $metadata->root();
$dependencies = $metadata->all();
$libraries = $metadata->ofType(PackageType::Library);
$groups = $metadata->groupedByType();

if ($metadata->has('vendor/package')) {
    $package = $metadata->get('vendor/package');
    $extensions = $package->extensions();
    $productionExtensions = $package->extensions(false);
    $classRules = $package->autoload()->classRules();
    $functionFiles = $package->autoload()->files();
    $originalRecord = $package->metadata();
}
```

Root identity, requirements, and autoload declarations come only from the root `composer.json`. Locked names, versions, references, types, runtime requirements, development classification, and autoload declarations come only from `composer.lock` `packages` and `packages-dev`, including `super-kernel/composer` itself.

The root always has the synthetic `PackageType::Root` type and occurs once in the `Root` group; its declared type remains in `metadata()`. An unnamed root keeps a null name and is available through `root()`. `all()` contains only production and development lock packages, sorted by name. `has()` and `get()` include named roots and use case-insensitive names. Check `has()` before `get()`; a missing name raises `PackageNotFoundException`.

Known types use `PackageType`; custom types keep their exact strings and their own group keys. The custom type `root` remains distinct from synthetic `Root`; numeric type keys follow PHP's array-key conversion. Empty type selections return `[]`, and conflicting package names are rejected.

Each `ComposerPackageInterface` exposes native package fields and preserves the original record, including custom fields, as recursively normalized arrays. Optional scalar values return null; collection accessors default to `[]`. Absent `source` and `dist` return null. License, binary, and comment strings become lists. `version()` preserves the declared string without trimming, normalization, inference, or format validation; an omitted root version returns null. JSON structure and field types are checked, including string versions and required lock-package versions; version formats are not. `reference()` selects `dist.reference`, then `source.reference`, and otherwise returns null. Dedicated getters cover requirements, source/dist, authors, support, funding, extra, scripts, and lock fields such as `version_normalized`, `notification-url`, `installation-source`, and `transport-options`. Field names follow the [Composer package schema](https://getcomposer.org/schema.json) and [Composer's package dumper](https://github.com/composer/composer/blob/main/src/Composer/Package/Dumper/ArrayDumper.php).

`isDev()` reflects `packages-dev` membership and is always false for the root. `extensions(true)` returns unique sorted lowercase `ext-*` names from this package's `require` and `require-dev`; false selects only `require`. It does not compute transitive application requirements or include suggestions, PHP versions, `lib-*`, or Composer API requirements.

`ComposerAutoloadInterface::classRules()` separates `psr-0`, `psr-4`, `classmap`, and `exclude-from-classmap` from `files()`. PSR path strings become lists; duplicates are removed while preserving declaration order and path spelling. `autoloadDev()` exposes the separate development declaration. These methods neither check path existence nor execute files.

Manifest and lock structural errors raise `ComposerFileException`; scan-rule validation raises `AutoloadRuleException` or `LockFileException`.

## Package manager

Load the application's generated Composer entry, then resolve the shared package manager through the DI container:

```php
require '/srv/application/vendor/autoload.php';

$packages = $container->get(SuperKernel\Contract\PackageManagerInterface::class);
```

Composer keeps its own loader and executes its declared file-autoload entries. `Provider\PackageManagerProvider` builds the manager from a `Contract\PackageReconcilerInterface`, a `ComposerMetadataInterface` snapshot, and the matching `VendorDirInterface`, all describing the same project. Direct callers obtain the reconciler from the container or supply their own implementation:

```php
$packages = new PackageManagerProvider()($reconciler, $metadata, $vendorDir);
```

Each invocation builds a fresh manager from the supplied snapshot without path detection or repeated manifest and lock reads; supply a new snapshot to observe changed lock entries. Building it changes neither SPL registration nor file-autoload execution. The DI container retains the shared instance, and previously returned managers keep their prepared graphs.

The provider reads the vendor directory's `composer/installed.php` once per invocation and uses only `root.dev` and membership in `versions`. In a `dev=false` installation, development lock entries that are not installed are excluded before location, scanning, or cache restoration; installed development packages stay included, and development installations keep the complete lock graph. Production entries are never excluded. A missing directory for a required production or development package raises `PackageLocationException`, and missing, unreadable, or malformed installation state raises `ComposerFileException`. `installed.json` and `Composer\InstalledVersions` are not read, so separate projects cannot share installation membership.

Every `PackageInterface` publishes two independent requirement sets:

- `dependencies()` and `hasDependencies()` contain only installable `vendor/package` requirements;
- `environmentRequirements()` and `hasEnvironmentRequirements()` contain PHP, PHP subtypes, HHVM, `ext-*`, `lib-*`, and Composer API requirements supplied by the runtime environment.

It also publishes `reference()`, version, Composer type, lock development classification, located path, and validated scan rules. `type()` identifies Root, `composerType()` keeps the declared Composer type, and `locatedPath()` provides the package directory. A root reference is `null`; a lock reference uses distribution first, then source, and remains `null` when neither exists. Root `require` and `require-dev` are represented with their development flags. Lock packages contribute only runtime `require`; a dependency's `require-dev` is not transitive. The scanned root requires a package name.

### Class graphs

```php
$composerClasses = $packages->classes('super-kernel/composer');
$allClasses = $packages->allClasses();
$owner = $packages->packageForClass(App\Service\Clock::class);
```

`classes()` returns one package's sorted class-to-absolute-path map, `[]` for a known package without classes, and `null` for an unknown package. `allClasses()` returns the aggregate loader map. `packageForClass()` returns the package selected for an exact symbol or `null`. Root declarations win aggregate collisions, followed by normalized package order; each per-package map still keeps its own declaration.

The root is always scanned with `autoload` plus `autoload-dev`, including in production; lock packages use only `autoload`. Candidate files may have any suffix. Named classes, interfaces, traits, and enums are included; anonymous classes and unparseable or unreadable files are ignored. Explicit classmap rules outrank PSR directories, and `exclude-from-classmap` stays effective. `ClassMap\ClassMapBuilder::build()` accepts a package list such as `$packages->all()` and uses the injected `ClassNameParserInterface`.

Scan rules admit existing package-contained PSR-4, PSR-0, and classmap directories, files, and supported Composer `*` globs. Absolute paths, null bytes, parent traversal, unsupported wildcard forms, and symlink escapes are rejected. `autoload.files` is validated and resolved separately: the manager exposes existing package-contained files in dependency order without executing them or treating them as scan inclusions. Legacy `target-dir` and `include-path` are not supported.

### Package location

Packages are located through `Contract\PackageLocatorInterface`. The default `Locator\PackageLocator` returns the standard `<vendor>/<package>` candidate; a custom implementation can support installer-specific layouts. The locator is authoritative and may return an absolute or vendor-relative path. A null candidate, invalid path, missing directory, or file in place of a directory raises `PackageLocationException` without a standard-layout fallback, and stops runtime initialization. Root and metapackage records do not use the locator, and path-repository links are canonicalized. Location is checked before every cache access, including cache hits and restore-only execution; a restored record's directory must also still exist.

## Package caches

Each package has its own serialized file below the vendor directory, for example:

```text
<vendor>/.cache/classes/acme/dependency.ser
```

A record holds the package, its class map, ordered file autoloads, the vendor-directory binding, and a SHA-256 checksum. On each reconciliation, a dependency whose cached `reference()` equals the current lock reference, including when both are null, reuses its cache and derived data; missing, invalid, or different-reference records are parsed, scanned, and rewritten. The root is always rescanned. Dependency metadata or source edits under an unchanged reference therefore reuse the cache: refresh the reference or remove that package's cache file to force a rebuild. Only packages in the current metadata are included, even when older cache files remain.

Restoration verifies the package identity, root or dependency role, project, record structure, object layout, and checksum. Missing, unreadable, incompatible, or corrupted data raises `PackageCacheException`. Rebuild package caches through a scanning processor after upgrading the component; normal scanning replaces incompatible records, while Phar and other restore-only deployments must receive rebuilt snapshots. With `NullScanProcessor`, which is supported only inside a Phar, existing package files are restored without scanning.

The checksum detects inconsistent data and does not authenticate local filesystem writers. Each package file is published atomically through a temporary file in the same directory, and a later package failure preserves earlier successful writes; concurrent writers are not coordinated across packages. Other files in the cache directory are left untouched.
