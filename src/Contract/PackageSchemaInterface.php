<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Contract;

/**
 * Represents the first-level structure of a Composer package definition.
 *
 * Only top-level fields are included, with simplified return types.
 */
interface PackageSchemaInterface
{
	/**
	 * Package name including vendor prefix.
	 *
	 * @return string
	 * @example "monolog/monolog"
	 *
	 */
	public function getName(): string;

	/**
	 * Package version.
	 *
	 * @return string|null
	 * @example "1.2.3", "dev-main"
	 *
	 */
	public function getVersion(): ?string;

	/**
	 * Normalized version string.
	 *
	 * @return string|null
	 * @example "1.2.3.0"
	 *
	 */
	public function getVersionNormalized(): ?string;

	/**
	 * Package type.
	 *
	 * @return string|null
	 *
	 * @example "project"
	 * @example "composer-plugin"
	 * @example "metapackage"
	 * @example "library"
	 */
	public function getType(): ?string;

	/**
	 * Short description of the package.
	 *
	 * @return string|null
	 * @example "Logging library for PHP"
	 *
	 */
	public function getDescription(): ?string;

	/**
	 * License(s) of the package.
	 *
	 * @return string|array|null
	 * @example ["MIT", "Apache-2.0"]
	 *
	 * @example "MIT"
	 */
	public function getLicense(): string|array|null;

	/**
	 * Project homepage URL.
	 *
	 * @return string|null
	 * @example "https://github.com/Seldaek/monolog"
	 *
	 */
	public function getHomepage(): ?string;

	/**
	 * Keywords associated with the package.
	 *
	 * @return array|null
	 * @example ["logging", "psr-4", "monolog"]
	 *
	 */
	public function getKeywords(): ?array;

	/**
	 * Path to the README file.
	 *
	 * @return string|null
	 * @example "README.md"
	 *
	 */
	public function getReadme(): ?string;

	/**
	 * Release date/time.
	 *
	 * @return string|null
	 * @example "2023-01-01 12:00:00"
	 * @example "2023-01-01T12:00:00Z"
	 *
	 * @example "2023-01-01"
	 */
	public function getTime(): ?string;

	/**
	 * Authors or maintainers.
	 *
	 * @return array|null
	 * @example [["name"=>"super-kernel", "email"=>"super-kernel@example.com"]]
	 *
	 */
	public function getAuthors(): ?array;

	/**
	 * Support URLs and contact info.
	 *
	 * @return array|null
	 * @example ["email"=>"support@example.com", "issues"=>"https://github.com/issues"]
	 *
	 */
	public function getSupport(): ?array;

	/**
	 * Funding options.
	 *
	 * @return array|null
	 * @example [["type"=>"patreon","url"=>"https://patreon.com/package"]]
	 *
	 */
	public function getFunding(): ?array;

	/**
	 * Required packages.
	 *
	 * @return array|null
	 * @example ["php"=>"^8.1","psr/log"=>"^3.0"]
	 *
	 */
	public function getRequire(): ?array;

	/**
	 * Development-only required packages.
	 *
	 * @return array|null
	 * @example ["phpunit/phpunit"=>"^10.0"]
	 *
	 */
	public function getRequireDev(): ?array;

	/**
	 * Conflicts with other packages.
	 *
	 * @return array|null
	 * @example ["some/package"=>"<1.0"]
	 *
	 */
	public function getConflict(): ?array;

	/**
	 * Packages replaced by this package.
	 *
	 * @return array|null
	 * @example ["old/package"=>"1.0"]
	 *
	 */
	public function getReplace(): ?array;

	/**
	 * Packages provided by this package.
	 *
	 * @return array|null
	 * @example ["psr/log-implementation"=>"1.0"]
	 *
	 */
	public function getProvide(): ?array;

	/**
	 * Suggested packages.
	 *
	 * @return array|null
	 * @example ["ext-json"=>"Required for JSON support"]
	 *
	 */
	public function getSuggest(): ?array;

	/**
	 * Extra arbitrary metadata.
	 *
	 * @return array|null
	 * @example ["branch-alias"=>["dev-main"=>"1.0-dev"]]
	 *
	 */
	public function getExtra(): ?array;

	/**
	 * Binary executables included in the package.
	 *
	 * @return array|null
	 * @example ["bin/monolog"]
	 *
	 */
	public function getBin(): ?array;

	/**
	 * Archive settings.
	 *
	 * @return array|null
	 * @example ["exclude"=>["tests/","docs/"]]
	 *
	 */
	public function getArchive(): ?array;

	/**
	 * Source repository information.
	 *
	 * @return array|null
	 * @example ["type"=>"git","url"=>"https://github.com/monolog/monolog.git","reference"=>"master"]
	 *
	 */
	public function getSource(): ?array;

	/**
	 * Distribution information.
	 *
	 * @return array|null
	 * @example ["type"=>"zip","url"=>"https://github.com/monolog/monolog/archive/1.2.3.zip","reference"=>"1.2.3"]
	 *
	 */
	public function getDist(): ?array;

	/**
	 * Autoload definitions.
	 *
	 * @return array|null
	 * @example ["psr-4"=>["Monolog\\"=>"src/"]]
	 *
	 */
	public function getAutoload(): ?array;

	/**
	 * Development-only autoload definitions.
	 *
	 * @return array|null
	 * @example ["psr-4"=>["Monolog\\Test\\"=>"tests/"]]
	 *
	 */
	public function getAutoloadDev(): ?array;

	/**
	 * Whether the package is abandoned.
	 *
	 * @return bool|string|null
	 * @example "replacement/package"
	 *
	 * @example false
	 */
	public function getAbandoned(): bool|string|null;

	public function __get(string $name): mixed;
}