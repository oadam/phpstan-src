<?php declare(strict_types = 1);

namespace PHPStan\File;

use Nette\Utils\Strings;
use function array_pop;
use function explode;
use function implode;
use function ltrim;
use function preg_match;
use function rtrim;
use function str_replace;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use const DIRECTORY_SEPARATOR;

class FileHelper
{

	private string $workingDirectory;

	public function __construct(string $workingDirectory)
	{
		$this->workingDirectory = $this->normalizePath($workingDirectory);
	}

	public function getWorkingDirectory(): string
	{
		return $this->workingDirectory;
	}

	/** @api */
	public function absolutizePath(string $path): string
	{
		if (DIRECTORY_SEPARATOR === '/') {
			if (substr($path, 0, 1) === '/') {
				return $path;
			}
		} else {
			if (substr($path, 1, 1) === ':') {
				return $path;
			}
		}
		if (preg_match('~^[a-z0-9+\-.]+://~i', $path) === 1) {
			return $path;
		}

		return rtrim($this->getWorkingDirectory(), '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
	}

	/** @api */
	public function normalizePath(string $originalPath, string $directorySeparator = DIRECTORY_SEPARATOR): string
	{
		$isLocalPath = false;
		if ($originalPath !== '') {
			if ($originalPath[0] === '/') {
				$isLocalPath = true;
			} elseif (strlen($originalPath) >= 3 && $originalPath[1] === ':' && $originalPath[2] === '\\') { // e.g. C:\
				$isLocalPath = true;
			}
		}

		$matches = null;
		if (!$isLocalPath) {
			$matches = Strings::match($originalPath, '~^([a-z0-9+\-.]+)://(.+)$~is');
		}

		if ($matches !== null) {
			[, $scheme, $path] = $matches;
			$scheme = strtolower($scheme);
		} else {
			$scheme = null;
			$path = $originalPath;
		}

		$path = str_replace(['\\', '//', '///', '////'], '/', $path);

		$pathRoot = strpos($path, '/') === 0 ? $directorySeparator : '';
		$pathParts = explode('/', trim($path, '/'));

		$normalizedPathParts = [];
		foreach ($pathParts as $pathPart) {
			if ($pathPart === '.') {
				continue;
			}
			if ($pathPart === '..') {
				/** @var string $removedPart */
				$removedPart = array_pop($normalizedPathParts);
				if ($scheme === 'phar' && substr($removedPart, -5) === '.phar') {
					$scheme = null;
				}

			} else {
				$normalizedPathParts[] = $pathPart;
			}
		}

		return ($scheme !== null ? $scheme . '://' : '') . $pathRoot . implode($directorySeparator, $normalizedPathParts);
	}

}
