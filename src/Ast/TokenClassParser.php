<?php
declare(strict_types=1);

namespace SuperKernel\Composer\Ast;

use function defined;
use function is_array;
use function token_get_all;
use const T_CLASS;
use const T_ENUM;
use const T_INTERFACE;
use const T_NAMESPACE;
use const T_STRING;
use const T_TRAIT;

final class TokenClassParser
{
	public function getFullyQualifiedClassName(string $code): ?string
	{
		$tokens           = token_get_all($code);
		$namespace        = '';
		$class            = '';
		$gettingNamespace = false;
		$gettingClass     = false;

		foreach ($tokens as $token) {
			if (is_array($token)) {
				if ($token[0] === T_NAMESPACE) {
					$gettingNamespace = true;
				}

				if ($token[0] === T_CLASS ||
				    $token[0] === T_INTERFACE ||
				    $token[0] === T_TRAIT ||
				    (defined('T_ENUM') && $token[0] === T_ENUM)
				) {
					$gettingClass = true;
				}

				if ($gettingNamespace) {
					if ($token[0] === T_NAME_QUALIFIED || $token[0] === T_STRING) {
						$namespace .= $token[1];
					}
				}

				if ($gettingClass) {
					if ($token[0] === T_STRING) {
						$class = $token[1];
						break;
					}
				}
			} else {
				if ($token === ';') $gettingNamespace = false;
				if ($token === '{') $gettingClass = false;
			}
		}

		return $class ? ($namespace ? $namespace . '\\' . $class : $class) : null;
	}
}