# SuperKernel Composer Metadata

`super-kernel/composer` reads root `composer.json` and `composer.lock` metadata and provides package graphs, class maps,
and class ownership on demand. Applications load Composer's generated `vendor/autoload.php`; package discovery begins
when a caller requests `PackageManagerProvider` or its DI service. Loading the component does not scan packages,
register an SPL loader, or execute package file-autoload declarations.

`super-kernel/composer` requires `super-kernel/attribute`, `super-kernel/contract`, `super-kernel/tokenizer`, and
`super-kernel/scan-isolation`. The reconciler writes its cache directly below the injected `VendorDirInterface` directory.

## Loading and package metadata

Load the application's generated Composer entry, then resolve services through its DI container (`$container`):

```php
require '/srv/application/vendor/autoload.php';

$packages = $container->get(SuperKernel\Contract\PackageManagerInterface::class);
```

Composer retains its loader and executes its declared file-autoload entries.

`Model\Package` represents both the root and every locked package, including `super-kernel/composer` itself.
The self package uses its lock entry for type, version, reference, dependencies, environment requirements, development
membership, and autoload declarations. It follows the same location and scan validation as other packages.

## Package reconciliation

`ComposerMetadata` reads native package data from the project's manifest and lock file.
`PackageManagerProvider::__invoke()` requires a `PackageReconcilerInterface`, a `ComposerMetadataInterface` snapshot, and the matching `VendorDirInterface`.
It selects runtime entries from `metadata->all()` and the additional `metadata->root()`, reconciles them, and constructs
a fresh `PackageManager` from the returned instances, their class maps, and dependency-ordered file autoloads. Root
retains first precedence in the manager. The metadata and reconciler must describe the same workspace.
The component-local `Contract\PackageReconcilerInterface` exposes `reconcile()`, `classes()`, and `autoloadFiles()`;
`Cache\PackageReconciler` implements it and carries `Provider(PackageReconcilerInterface::class)` directly.

`PackageReconciler` owns the scan processor. For every given `ComposerPackageInterface`, it checks processor support
and invokes one callback. Inside that callback it reads and deserializes an existing package cache and compares
`reference()` with the current native record. Equal references reuse the instance and its derived data, including
when both references are null. Missing, invalid, or different-reference records are parsed, scanned through Tokenizer,
and written immediately. Root is always reparsed and rescanned with production plus development rules.

After the processor returns, the reconciler reads the package file again and returns the newly deserialized instance.
That final read is authoritative, including a valid update published before it runs. Missing, unreadable, incompatible,
or corrupted data raises `PackageCacheException`; the callback's in-memory result cannot replace this read.
Each successful package publication is independent. A later package failure preserves earlier successful writes.
Only packages in the current metadata are included in the next manager, even when older cache files remain.

Manager construction changes neither SPL registration nor file-autoload execution. The DI container owns shared
instance retention; previously returned managers retain their prepared graphs.

Factory providers retain their DI bindings and require their service dependencies explicitly. The temporary container
used before DI is ready resolves those dependencies; providers never instantiate fallback providers. Direct callers
must supply the same services. `PackageManagerProvider` does not detect a workspace, and
`PackageReconciler` requires an injected parser instead of constructing one from a base path or locator.

```php
use SuperKernel\Composer\Cache\PackageReconciler;
use SuperKernel\Composer\ClassMap\ClassMapBuilder;
use SuperKernel\Composer\Locator\PackageLocator;
use SuperKernel\Composer\Metadata\PackageParser;
use SuperKernel\Composer\Provider\PackageManagerProvider;

// Direct composition from already-resolved services for one workspace.
$parser = new PackageParser($basePath, $vendorDir, new PackageLocator());
$reconciler = new PackageReconciler(
    $parser,
    new ClassMapBuilder($classNameParser),
    $vendorDir,
    $scanProcessor,
);
$packages = new PackageManagerProvider()($reconciler, $metadata, $vendorDir);
```

`PackageReconciler` is marked with `Provider(PackageReconcilerInterface::class)`. Its four required constructor
parameters are `PackageParserInterface`, `ClassMapBuilder`, `VendorDirInterface`, and `ScanProcessorInterface`, in that
order.
`ClassMapBuilder` receives `ClassNameParserInterface` through constructor injection. Tokenizer binds `ClassNameParser`
with `Provider(ClassNameParserInterface::class)`. Directly constructed builders still accept any implementation of
that interface.
Storage and parsing must use the supplied vendor directory; storage must be shared with the scan
child. Supply a custom `PackageLocatorInterface` through `PackageParser`'s required third constructor parameter.
The reconciler constructor accepts `PackageParserInterface` and requires an explicit processor. Construction
does not probe the processor, locate packages, read metadata, or allocate storage.
Inside a Phar archive, `NullScanProcessor` restores existing package files without executing scan callbacks.
Its `support()` returns false outside Phar, where reconciliation rejects an explicitly injected Null processor.
The manager provider always uses its supplied metadata snapshot without path detection or repeated manifest/lock reads.
To observe changed lock entries, supply a new snapshot. Missing or invalid package caches fail. An enclosing isolated
bootstrap can supply a `PhaseScanProcessor` to execute nested callbacks in its false phase and restore their caches
in its true phase.
Package location is checked before cache access, including on cache hits and with a `NullScanProcessor`; an injected
locator is also consulted on each reconciliation. A missing directory raises `PackageLocationException` and aborts
runtime initialization. If a restored record points to a different directory, that directory must also still exist.
Dependency metadata or source edits under an unchanged reference intentionally reuse the cached package. Refresh the
reference or remove that package's cache to force a rebuild. Root changes are picked up by its unconditional scan.

## Serialized package caches

Each package has its own serialized file, for example:

```text
<vendor>/.cache/classes/acme/dependency.ser
```

The actual vendor directory comes from `VendorDirInterface`. The serialized array contains the runtime package
instance, class map, ordered file autoloads, vendor-directory binding, and SHA-256 checksum.
Restoration uses an explicit class allow-list and verifies the package identity, Root or dependency role, workspace,
record structure, initialized Package property layout, and checksum. Removed or missing properties are rejected.
Rebuild package caches after object-layout changes, including removal of the fingerprint field. Normal scanning
replaces incompatible records; Phar and other restore-only deployments must receive rebuilt snapshots. Cache records have no schema version. Earlier records containing a `schema` field
are rebuilt during a successful processing pass; restore-only mode requires compatible caches.
The checksum detects inconsistent data and does not authenticate local filesystem writers. The reconciler publishes
each package file atomically through a temporary file in the same directory; concurrent writers are not coordinated
across packages.

`PackageReconciler` owns serialization and restoration directly. It reads only the per-package layout above;
unrecognized cache artifacts remain untouched. Rebuild caches through a scanning processor before packaging Phar.

## Project root detection

`SuperKernel\Contract\BasePathInterface` belongs to `super-kernel/contract`, extends PHP's `Stringable`, and declares
`__toString(): string`.
The final readonly `Path\BasePath` value stores the directory supplied by the factory unchanged. Directory validity
and normalization belong to the factory's detector chain. `Provider\BasePathProvider` is marked for this
interface and tries these detectors in order:

1. `NullDetector`, implementing `DetectorInterface`, recognizes execution inside an application Phar and returns
   the archive's internal `phar://...` root using [Phar::running()](https://www.php.net/manual/en/phar.running.php).
2. `ComposerScriptDetector` recognizes `COMPOSER_DEV_MODE=0` or `1` and checks Composer's working directory.
   This marker is supplied for [Composer scripts](https://getcomposer.org/doc/articles/scripts.md).
3. `ScriptDetector` recognizes an existing PHP entry script and checks the directory returned by `getcwd()`.
4. `WorkingDirectoryDetector` checks `getcwd()` without requiring an entry script.
5. `ComponentDetector` checks the current working directory for both `composer.json` and `composer.lock`,
   supporting standalone use when launched from the component root.

Each `DetectorInterface` implementation captures its environment, exposes `support(): bool`, and returns its detected
path through `projectDirectory(): string`, returning an empty string when unsupported. Call `support()` before using
the directory. The factory constructs a fresh root value from the first supported detector; later detectors are not
evaluated. Only the factory throws `ProjectRootNotFoundException` when no detector supports the environment. The selected
detector supplies a valid canonical directory; `BasePath` trusts this internal contract without further checks.

Filesystem detectors inspect only the directory returned by [getcwd()](https://www.php.net/manual/en/function.getcwd.php).
They require `composer.json`; the component detector also requires `composer.lock`. They do not traverse parent
directories or infer the application root from the entry script or component installation location.
Launch PHP from the project root, including when using a relative or absolute path to a script elsewhere.
Detection checks file locations without parsing their contents or inspecting `config.vendor-dir` or an installation.
`ComposerMetadata` reads and validates the located documents, preserving `config.vendor-dir` in root package
data for `VendorDirProvider` to resolve. Neither detection nor native metadata reading requires installed dependencies.
If the working directory changes, pass the previously resolved base path explicitly to `ComposerMetadata`.
Pass metadata and a reconciler for the same project to the package-manager provider.

`SuperKernel\Contract\VendorDirInterface` provides a separate Stringable vendor path value, implemented by the final
readonly `Path\VendorDir`. `Provider\VendorDirProvider::__invoke()` requires `ComposerMetadataInterface`
and `BasePathInterface` arguments from the same workspace. It uses the supplied metadata without rereading Composer
files or detecting a base path.
The provider reads `config.vendor-dir` from the metadata's root package, defaults to `vendor` when absent, and resolves
relative paths against the selected base path. It normalizes absolute and Phar paths and canonicalizes existing links.
It creates a fresh value without creating a directory; invalid configured paths raise `ComposerFileException`.

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

Consumers that need the dependency directory inject `SuperKernel\Contract\VendorDirInterface` and cast it to a string.
The temporary container resolves this service before the final DI container is ready. Direct callers explicitly pass
both metadata and base path to `VendorDirProvider`.
The package-manager constructor starts with the root package; parsing and cache reconciliation receive
the vendor value independently. Incompatible snapshots require rebuilding through a scanning processor.

The root provider accepts an optional ordered iterable of `DetectorInterface` implementations for custom environments;
an explicit empty iterable also fails with `ProjectRootNotFoundException`. DI retains its resolved root value and injects
it into `ComposerMetadata`. Its constructor requires `BasePathInterface $basePath` and does not detect or retain a
default project path. Wrap explicit directory strings in `Path\BasePath`. Phar path support here covers
root detection and native manifest/lock reading. Phar execution with `NullScanProcessor` requires matching prebuilt
cache files beneath the supplied vendor directory; it does not provide writable cache storage inside an archive.

## Structured Composer package data

`Metadata\ComposerMetadata` is marked with `Provider(ComposerMetadataInterface::class)` and
reads fresh metadata from the project's `composer.json` and `composer.lock` using its required
`BasePathInterface $basePath` constructor argument. DI resolves and retains the injected base path and metadata instances.
Direct callers construct `ComposerMetadata` with a base-path value. An explicit root value requires both documents but no installed vendor directory. The single-file
reader methods continue to return `ComposerJsonInterface` and `ComposerLockInterface` views.

`ComposerMetadataInterface` exposes package data only. Project and vendor directory access belongs to
`BasePathInterface` and `VendorDirInterface`. Metadata construction accepts a project root, and no longer accepts
`vendorDirectory` for reverse project discovery or validates that configuration as a filesystem path.

```php
use SuperKernel\Composer\Enum\PackageType;
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

The root always has the synthetic `PackageType::Root` type and occurs once in the `Root` group. Its original
declared type remains in `metadata()`. An unnamed root keeps a null name and is available through `root()`; no synthetic
package name is invented. `all()` contains only production and development lock packages, sorted by name. `has()` and
`get()` include named roots and use case-insensitive names. Calling `get()` requires checking `has()` first; a missing
name raises `PackageNotFoundException`, deliberately without an interface-level `@throws` annotation.

Known types use `PackageType`; custom types retain their exact strings and their own group keys. There is no
shared custom-type bucket. The custom type `root` remains distinct from synthetic `Root`; numeric type keys follow PHP's
array-key conversion. Empty type selections return `[]`, and conflicting package names are rejected.

Each `ComposerPackageInterface` exposes native package fields and preserves the original record, including custom
fields, as recursively normalized arrays. Optional scalar values return null; collection accessors default to `[]`.
Absent `source` and `dist` return null. License, binary, and comment strings become lists. `version()` preserves the
declared string without trimming, normalization, inference, or format validation; an omitted root version returns
null. The framework checks JSON structure and field types, including string versions and required lock-package
versions. Version-format validation belongs to the Composer tool. `reference()` selects `dist.reference`, then
`source.reference`, and otherwise returns null.
Dedicated getters cover requirements, source/dist, authors, support, funding, extra, scripts, and lock fields such as
`version_normalized`, `notification-url`, `installation-source`, and `transport-options`. Field names follow the
[Composer package schema](https://getcomposer.org/schema.json) and
[Composer's package dumper](https://github.com/composer/composer/blob/main/src/Composer/Package/Dumper/ArrayDumper.php).

`isDev()` reflects `packages-dev` membership and is always false for the root. `extensions(true)` returns unique sorted
lowercase `ext-*` names from this package's `require` and `require-dev`; false selects only `require`. It does not compute
transitive application requirements or include suggestions, PHP versions, `lib-*`, or Composer API requirements.

`ComposerAutoloadInterface::classRules()` separates `psr-0`, `psr-4`, `classmap`, and `exclude-from-classmap` from
`files()`. PSR path strings become lists; duplicates are removed while preserving declaration order and path spelling.
`autoloadDev()` exposes a separate development declaration. These methods neither check path existence nor execute
files. The existing runtime scan interfaces retain responsibility for filesystem containment and class discovery.

## Package metadata API

Native metadata comes from the root manifest and lock file through `ComposerMetadata`:

```php
use SuperKernel\Composer\Metadata\ComposerMetadata;
use SuperKernel\Composer\Path\BasePath;

$metadata = new ComposerMetadata(new BasePath('/srv/application'));
$root = $metadata->root();
$lockedPackages = $metadata->all(); // Excludes Root.
```

Use `PackageManagerProvider` for the scanned shared package-manager interface. The runtime package manager preserves
normalized dependencies, environment requirements, scan rules, and exact class ownership from those native fields.
The single `SuperKernel\Composer\Manager\PackageManager` implementation uses the shared
`SuperKernel\Contract\PackageManagerInterface`. Its constructor accepts a prepared package graph, class maps,
and ordered autoload-file paths, without discovery, scanning, storage access, or loader registration.
Shared `PackageMetadataInterface` and `PackageManagerInterface` ports belong to Contract; Composer's richer
`PackageInterface` exposes scan rules. `ClassMapBuilder::build()` accepts a package list such as `$manager->all()`.

Manifest/lock structural errors are `ComposerFileException`; scan-rule validation uses autoload/lock exceptions.
The scanned root requires a package name. Root and dependencies share `Model\Package`: `type()` identifies Root,
`composerType()` retains the declared Composer type, and `locatedPath()` provides its directory.
`scanRules()` supplies production rules; `scanRulesFor(AutoloadScope::Development)` adds root development rules.
When constructing Root, supply `PackageType::Root`, its path, and no lock-derived state. Optional `composerType`
and `developmentScanRules` describe its declared type and merged discovery rules.

Every `PackageInterface` publishes two independent requirement sets:

- `dependencies()` and `hasDependencies()` contain only installable `vendor/package` requirements;
- `environmentRequirements()` and `hasEnvironmentRequirements()` contain PHP, PHP subtypes, HHVM, `ext-*`, `lib-*`,
  and Composer API requirements supplied by the runtime environment.

It also publishes `reference()`, version, Composer type, lock development classification, located path, and validated
scan rules. A root reference is `null`; a lock reference uses distribution first, then source, and remains `null` when
neither exists. No version or installed metadata is used to fabricate one.

Root `require` and `require-dev` are represented with their development flags. Lock packages contribute only runtime
`require`; dependency-package `require-dev` is root-only Composer metadata and is not transitive.

## Package class graphs

Three public lookups expose derived class ownership:

```php
$composerClasses = $packages->classes('super-kernel/composer');
$allClasses = $packages->allClasses();
$owner = $packages->packageForClass(SuperKernel\Composer\Manager\PackageManager::class);
```

`classes()` returns one package's sorted class-to-absolute-path map, `[]` for a known package without classes, and `null`
for an unknown package. `allClasses()` returns the aggregate loader map. `packageForClass()` returns the package selected
for an exact symbol or `null`. Root declarations win aggregate collisions, followed by normalized package order; each
per-package map still retains its own declaration.

The root is scanned with `autoload` plus `autoload-dev`; lock packages use only `autoload`. Candidate files may have any
suffix. Named classes, interfaces, traits, and enums are included. Anonymous classes and unparseable files are ignored.
Explicit classmap rules outrank PSR directories, and `exclude-from-classmap` remains effective.

## Metadata and location boundaries

Root identity, requirements, and autoload declarations come only from the explicit root `composer.json`. Locked names,
versions, references, types, runtime requirements, development classification, and autoload declarations come only from
`composer.lock` `packages` and `packages-dev`, including the Composer component itself.

`PackageManagerProvider` is the sole installation-state reader. It requires
`VendorDirInterface $vendorDir` and reads that directory's `composer/installed.php` once per
invocation. Only `root.dev` and membership in `versions` are used: in a `dev=false` installation, absent development
lock entries are excluded before location, scanning, or cache restoration. Recorded development packages remain
included. Development installations retain the complete lock graph. Production entries are never excluded.
A missing directory for a required production or development package still raises `PackageLocationException`.
Missing, unreadable, or malformed installation state raises `ComposerFileException` instead of guessing from paths.

Composer's generated state determines installation membership, while
manifest and lock remain the sole authority for package data. The provider uses the supplied vendor directory rather
than global `InstalledVersions` state, so separate workspaces cannot accidentally share installation membership.
`ComposerMetadata::all()`, `get()`, and `packages-dev` membership are unchanged and remain readable without vendor.
Metadata readers and `PackageParser` never consult installation state, dependency manifests, plugins, scripts, or
the network. `installed.json` and `Composer\InstalledVersions` are not read.

DI injects the registered vendor directory. Direct provider callers supply the same installation state:

```php
$packages = new PackageManagerProvider()($reconciler, $metadata, $vendorDir);
```

For an already-prepared graph without installation-state discovery, construct `Manager\PackageManager` directly.

Root `autoload` and `autoload-dev` are always scanned, including in production. Installed dependencies use only
production `autoload`; selecting installation membership does not narrow these discovery rules. `PackageParser`
continues to locate packages through its injected `PackageLocatorInterface`, canonicalize path-repository links,
and leave metapackages unlocated.

`Metadata\PackageParser` implements the component-local `Contract\PackageParserInterface`, which exposes `locate()`,
`parse()`, and `autoloadFiles()`. The class is marked with `Provider(PackageParserInterface::class)` and requires
`BasePathInterface $basePath`, `VendorDirInterface $vendorDir`, and `PackageLocatorInterface $locator` in its constructor.
Construction reads no metadata and locates no packages; direct callers must supply all three services and use `basePath` for the first named argument.
`PackageReconciler` depends on this interface, allowing a custom parser to be injected through
`PackageReconciler` with `parser: $parser`.

`Locator\PackageLocator` is marked with `Provider(PackageLocatorInterface::class)` and supplies the standard
`<vendor>/<package>` candidate. DI injects this implementation by default; direct callers can pass it or a custom
locator for installer-specific layouts. The supplied locator is authoritative and may return an absolute or
vendor-relative candidate. A null candidate, invalid path,
missing directory, or file in place of a directory raises `PackageLocationException` without a standard-layout fallback.
Root and metapackage records do not invoke the locator.

Scan rules admit existing package-contained PSR-4, PSR-0, and classmap directories, files, and supported Composer `*`
globs. Absolute paths, null bytes, parent traversal, unsupported wildcard forms, and symlink escapes are rejected.
`autoload.files` is validated and resolved separately from class scanning. The manager exposes existing package-contained
files in dependency order for callers; it does not execute them or convert them into scan inclusions. Legacy
`target-dir` and `include-path` are not supported.

## Development

Within the SuperKernel repository, use the root dependency installation and run the component suite from the
repository root:

```bash
composer84 install
test_bootstrap=$(mktemp)
cat > "$test_bootstrap" <<'PHP'
<?php
require getcwd() . '/vendor/autoload.php';
$loader = new Composer\Autoload\ClassLoader();
$loader->addPsr4('SuperKernel\\Composer\\Tests\\', getcwd() . '/.vendor/composer/tests');
$loader->register(true);
PHP
php84 vendor/bin/phpunit --configuration .vendor/composer/phpunit.xml.dist --bootstrap "$test_bootstrap"
rm "$test_bootstrap"
php84 vendor/bin/php-cs-fixer check --diff --config=.vendor/composer/.php-cs-fixer.dist.php
```

The suite uses this component's PHPUnit configuration with root PHPUnit and the root `vendor/autoload.php`. The
disposable bootstrap registers the `SuperKernel\Composer\Tests\` development namespace because the root manifest maps
no component tests; no component `vendor/` directory is needed.

For a standalone checkout of this component, use the PHP 8.4.27 or later 8.4.x Composer executable in that checkout:

```bash
composer84 install
composer84 validate --strict
composer84 test
composer84 format:check
composer84 dump-autoload --optimize --strict-psr --dry-run
```
