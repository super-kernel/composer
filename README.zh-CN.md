<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Composer Metadata

`super-kernel/composer` 读取根项目的 `composer.json` 和 `composer.lock` 元数据，并按需提供包依赖图、类图和类的归属。应用加载 Composer 生成的 `vendor/autoload.php`；只有当调用方请求 `PackageManagerProvider` 或其 DI 服务时，才会开始包的发现。加载本组件不会扫描包、不会注册 SPL 加载器，也不会执行包声明的文件自动加载。

## 项目根目录与 vendor 目录

`SuperKernel\Contract\BasePathInterface` 表示项目根目录，是一个 `Stringable` 值。`Provider\BasePathProvider` 提供它，并按以下顺序尝试各个检测器：

1. `NullDetector` 识别在应用 Phar 内的执行，并通过 [Phar::running()](https://www.php.net/manual/en/phar.running.php) 返回归档内部的 `phar://...` 根。
2. `ComposerScriptDetector` 识别 `COMPOSER_DEV_MODE=0` 或 `1`，并检查 Composer 的工作目录。这个标记由 [Composer 脚本](https://getcomposer.org/doc/articles/scripts.md) 提供。
3. `ScriptDetector` 识别存在的 PHP 入口脚本，并检查 `getcwd()` 返回的目录。
4. `WorkingDirectoryDetector` 检查 `getcwd()`，不要求有入口脚本。
5. `ComponentDetector` 检查当前工作目录中是否同时存在 `composer.json` 和 `composer.lock`，以支持从组件根目录启动时的独立使用。

第一个受支持的检测器生效，之后的检测器不再评估。没有任何检测器支持当前环境时，提供者抛出 `ProjectRootNotFoundException`。基于文件系统的检测器只检查 [getcwd()](https://www.php.net/manual/en/function.getcwd.php) 返回的目录，并要求存在 `composer.json`；组件检测器还要求存在 `composer.lock`。它们不会向上遍历父目录，也不会根据入口脚本或安装位置推断根目录，因此请从项目根目录启动 PHP，即使是按路径运行其他位置的脚本也是如此。检测只检查文件位置，不解析文件内容。

为适应自定义环境，提供者可以接收一个有序的 `DetectorInterface` 实现可迭代对象；显式传入空的可迭代对象同样会以 `ProjectRootNotFoundException` 失败。检测器提供 `support(): bool` 和 `projectDirectory(): string`，后者在不受支持时返回空字符串，受支持时必须返回有效的规范目录。需要显式指定目录字符串时，请用 `Path\BasePath` 包装。

`SuperKernel\Contract\VendorDirInterface` 是另一个独立的 `Stringable` 值，表示依赖目录。`Provider\VendorDirProvider::__invoke()` 接收来自同一个项目的 `ComposerMetadataInterface` 和 `BasePathInterface`。它从根包读取 `config.vendor-dir`，缺省为 `vendor`，相对路径基于根目录解析，对绝对路径和 Phar 路径进行规范化，并解析已存在的链接。它不会创建该目录；配置的路径无效时抛出 `ComposerFileException`。

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

需要依赖目录的使用方注入 `VendorDirInterface`，并将其转换为字符串。如果检测之后工作目录发生了变化，请显式传入之前解析得到的根目录。在 Phar 内，根目录检测和 manifest/lock 读取都基于归档进行，包缓存必须已经在 vendor 目录下准备好；归档不提供可写的缓存存储。

## Composer 元数据

`Metadata\ComposerMetadata` 提供 `ComposerMetadataInterface`。它通过必需的 `BasePathInterface $basePath` 参数，从项目的 `composer.json` 和 `composer.lock` 读取最新的元数据；两个文件都必须存在，但不需要已安装的 vendor 目录，也从不读取安装状态、依赖包的 manifest、插件、脚本或网络。其单文件读取方法返回 `ComposerJsonInterface` 和 `ComposerLockInterface` 视图。

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

根包的身份、依赖要求和自动加载声明只来自根 `composer.json`。锁定包的名称、版本、reference、类型、运行时依赖要求、开发分类和自动加载声明只来自 `composer.lock` 的 `packages` 和 `packages-dev`，`super-kernel/composer` 本身也不例外。

根包始终具有合成的 `PackageType::Root` 类型，并在 `Root` 分组中只出现一次；其声明的原始类型保留在 `metadata()` 中。未命名的根包保持名称为 null，可以通过 `root()` 取得。`all()` 只包含生产和开发的锁定包，按名称排序。`has()` 和 `get()` 包括具名的根包，并且名称不区分大小写。请先用 `has()` 检查再调用 `get()`；名称不存在时抛出 `PackageNotFoundException`。

已知类型使用 `PackageType`；自定义类型保留其原始字符串和各自的分组键。自定义类型 `root` 与合成的 `Root` 不同；数字形式的类型键遵循 PHP 的数组键转换规则。空的类型选择返回 `[]`，名称冲突的包会被拒绝。

每个 `ComposerPackageInterface` 暴露原生的包字段，并以递归规范化的数组保留原始记录（包括自定义字段）。可选的标量值返回 null；集合访问器默认返回 `[]`。缺少 `source` 和 `dist` 时返回 null。许可证、二进制文件和注释字符串会变成列表。`version()` 保留声明的字符串，不做裁剪、规范化、推断或格式校验；根包省略版本时返回 null。会检查 JSON 结构和字段类型（包括版本必须是字符串、锁定包必须有版本），但不检查版本格式。`reference()` 优先选择 `dist.reference`，其次是 `source.reference`，否则返回 null。专门的 getter 覆盖依赖要求、source/dist、作者、支持、资助、extra、脚本，以及 `version_normalized`、`notification-url`、`installation-source` 和 `transport-options` 等锁文件字段。字段名遵循 [Composer 包 schema](https://getcomposer.org/schema.json) 和 [Composer 的包导出器](https://github.com/composer/composer/blob/main/src/Composer/Package/Dumper/ArrayDumper.php)。

`isDev()` 反映 `packages-dev` 成员关系，对根包始终为 false。`extensions(true)` 返回该包 `require` 和 `require-dev` 中去重、排序、小写的 `ext-*` 名称；传 false 时只选取 `require`。它不计算应用的传递依赖要求，也不包括建议项、PHP 版本、`lib-*` 或 Composer API 要求。

`ComposerAutoloadInterface::classRules()` 把 `psr-0`、`psr-4`、`classmap` 和 `exclude-from-classmap` 与 `files()` 分开。PSR 的路径字符串会变成列表；去除重复项，同时保留声明顺序和路径的原始写法。`autoloadDev()` 暴露独立的开发环境声明。这些方法既不检查路径是否存在，也不执行文件。

manifest 和 lock 的结构错误抛出 `ComposerFileException`；扫描规则校验抛出 `AutoloadRuleException` 或 `LockFileException`。

## 包管理器

加载应用生成的 Composer 入口，然后通过 DI 容器解析共享的包管理器：

```php
require '/srv/application/vendor/autoload.php';

$packages = $container->get(SuperKernel\Contract\PackageManagerInterface::class);
```

Composer 保留自己的加载器，并执行其声明的文件自动加载条目。`Provider\PackageManagerProvider` 用一个 `Contract\PackageReconcilerInterface`、一份 `ComposerMetadataInterface` 快照以及对应的 `VendorDirInterface` 构建管理器，三者都必须描述同一个项目。直接调用时，请从容器取得 reconciler，或提供自己的实现：

```php
$packages = new PackageManagerProvider()($reconciler, $metadata, $vendorDir);
```

每次调用都会根据传入的快照构建一个新的管理器，不做路径检测，也不重复读取 manifest 和 lock；要看到锁定条目的变化，请传入新的快照。构建管理器既不改变 SPL 注册，也不执行文件自动加载。共享实例由 DI 容器保留，之前返回的管理器保持其已准备好的依赖图。

提供者每次调用时读取一次 vendor 目录中的 `composer/installed.php`，只使用 `root.dev` 和 `versions` 中的成员关系。在 `dev=false` 的安装中，未安装的开发锁定条目会在定位、扫描或缓存恢复之前被排除；已安装的开发包仍会保留，开发环境的安装保留完整的锁定依赖图。生产条目从不被排除。必需的生产或开发包缺少目录时抛出 `PackageLocationException`；安装状态缺失、不可读或格式错误时抛出 `ComposerFileException`。不读取 `installed.json` 和 `Composer\InstalledVersions`，因此不同的项目不会共享安装成员关系。

每个 `PackageInterface` 发布两组相互独立的依赖要求：

- `dependencies()` 和 `hasDependencies()` 只包含可安装的 `vendor/package` 依赖；
- `environmentRequirements()` 和 `hasEnvironmentRequirements()` 包含由运行环境提供的 PHP、PHP 子类型、HHVM、`ext-*`、`lib-*` 以及 Composer API 要求。

它还发布 `reference()`、版本、Composer 类型、锁文件中的开发分类、定位到的路径以及校验过的扫描规则。`type()` 标识 Root，`composerType()` 保留声明的 Composer 类型，`locatedPath()` 提供包目录；`Model\Package` 实现的 `withLocatedPath()` 返回一个位于另一目录、经过同样校验的副本。根包的 reference 为 `null`；锁定包的 reference 优先使用 dist，其次是 source，两者都没有时为 `null`。根包的 `require` 和 `require-dev` 会带着各自的开发标志表示。锁定包只贡献运行时的 `require`；依赖包的 `require-dev` 不会传递。被扫描的根包必须有包名。

### 类图

```php
$composerClasses = $packages->classes('super-kernel/composer');
$allClasses = $packages->allClasses();
$owner = $packages->packageForClass(App\Service\Clock::class);
```

`classes()` 返回单个包排序后的类名到绝对路径的映射；对已知但没有类的包返回 `[]`，对未知的包返回 `null`。`allClasses()` 返回供加载器使用的汇总映射。`packageForClass()` 返回为某个确切符号选定的包，或 `null`。汇总映射出现冲突时，根包的声明优先，其次按规范化后的包顺序；每个包自己的映射仍保留其自身的声明。

根包始终同时按 `autoload` 和 `autoload-dev` 扫描，生产环境中也是如此；锁定包只使用 `autoload`。候选文件可以是任意后缀。具名的类、接口、trait 和枚举都会被包含；匿名类以及无法解析或无法读取的文件会被忽略。显式的 classmap 规则优先于 PSR 目录，`exclude-from-classmap` 仍然有效。`ClassMap\ClassMapBuilder::build()` 接受一个包列表（例如 `$packages->all()`），并使用注入的 `ClassNameParserInterface`。

扫描规则接受包内已存在的 PSR-4、PSR-0 和 classmap 目录、文件，以及受支持的 Composer `*` 通配符。绝对路径、空字节、向上遍历、不受支持的通配形式以及逃出包目录的符号链接都会被拒绝。`autoload.files` 单独校验和解析：管理器按依赖顺序暴露包内已存在的这些文件，但不会执行它们，也不会把它们当作扫描对象。不支持旧的 `target-dir` 和 `include-path`。

### 包的定位

包通过 `Contract\PackageLocatorInterface` 定位。默认的 `Locator\PackageLocator` 返回标准的 `<vendor>/<package>` 候选路径；自定义实现可以支持特定安装器的目录布局。定位器的结果具有决定性，可以返回绝对路径或相对于 vendor 的路径。候选为 null、路径无效、目录不存在，或该位置是文件而不是目录时，会抛出 `PackageLocationException`，不会回退到标准布局，并中止运行时初始化。根包和元包记录不使用定位器，路径仓库的链接会被规范化。每次访问缓存之前都会检查位置，包括缓存命中和仅恢复的执行；恢复出的记录所指向的目录也必须仍然存在。

## 包缓存

每个包在 vendor 目录下有自己的序列化文件，例如：

```text
<vendor>/.cache/classes/acme/dependency.ser
```

一条记录包含包本身、其类图、有序的文件自动加载列表以及 SHA-256 校验和，并且可以重定位：包的位置相对其所在环境存储，根包存为 `.`，依赖包存为 vendor 目录下的路径；类图和文件自动加载的路径则相对包目录存储。因此移动项目，或把项目打包进 Phar，记录依然有效。位于 vendor 目录之外的依赖包，或位于包目录之外的文件，会保留绝对路径，相应的记录只在该路径下有效。每次协调时，如果某个依赖包缓存的 `reference()` 与当前锁文件中的 reference 相同（两者都为 null 也算相同），就复用其缓存和派生数据；缺失、无效或 reference 不同的记录会被重新解析、扫描并重写。根包总是会被重新扫描。因此，在 reference 不变的情况下修改依赖包的元数据或源码，仍会复用缓存：请更新 reference，或删除该包的缓存文件来强制重建。只有当前元数据中的包会被包含，即使还有更早的缓存文件残留。

恢复时会校验包的身份、根包或依赖包的角色、记录结构、对象布局和校验和，并检查存储的位置是否与包当前的位置一致，然后基于当前位置展开存储的路径。数据缺失、不可读、不兼容或损坏时抛出 `PackageCacheException`。升级本组件之后，请通过一个执行扫描的处理器重建包缓存；正常扫描会替换不兼容的记录，而 Phar 和其他仅恢复的部署必须拿到重建后的快照。使用只在 Phar 内受支持的 `NullScanProcessor` 时，会直接恢复已有的包文件而不进行扫描。

校验和用于发现不一致的数据，并不能验证本地文件系统的写入方。每个包文件都通过同目录下的临时文件原子发布，之后某个包失败也不会影响之前已成功写入的文件；跨包的并发写入方之间没有协调。缓存目录中的其他文件保持不变。
