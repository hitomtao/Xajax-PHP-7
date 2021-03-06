<?php
/**
 * PHP version php7
 *
 * @category
 * @package            jybrid-php-7
 * @author             ${JProof}
 * @copyright          ${copyright}
 * @license            ${license}
 * @link
 * @see                ${docu}
 * @since              27.10.2017
 */

declare(strict_types=1);

namespace Jybrid\Scripts;

use Jybrid\Helper\Directories;

/**
 * Class Scripts
 *
 * @see     https://github.com/JProof/Jybrid/blob/master/docs/scripts.md
 * @package Jybrid\Scripts
 */
class Scripts
{
	/**
	 * SearchDirectory for Scripts
	 *
	 * @var Queue
	 */
	protected $scriptDirs;
	/**
	 * The ScriptNames
	 *
	 * @var array
	 */
	protected $scripts;
	/**
	 * @var ScriptsOrdering
	 */
	protected $scriptsOrdering;
	/**
	 * @var array
	 */
	protected $lockedScripts = [];
	/**
	 * Internal Configuration
	 *
	 * @var \Jybrid\Configuration\Scripts
	 */
	protected $configuration;

	/**
	 * Scripts constructor.
	 */
	protected function __construct()
	{
		$this->configuration = \Jybrid\Configuration\Scripts::getInstance();

		$this->scriptDirs = new Queue();

		$this->scripts         = [];
		$this->scriptsOrdering = new ScriptsOrdering();

		// execution order call insert only on construction
		$this->getScriptsOrdering()->insert( 'jybrid', 50 );
		$this->getScriptsOrdering()->insert( 'jybrid.debug', 49 );

		$this->addScript( new Core( [ 'scriptName' => 'jybrid', 'fileName' => 'jybrid_core.js', 'useScriptLoadTimeout' => true ] ) );
		$this->addScript( new Core( [ 'scriptName' => 'jybrid.debug', 'fileName' => 'jybrid_debug.js', 'useScriptLoadTimeout' => true ] ) );

		// Adding jybrid default Script Directory
		$this->addScriptDir(\dirname(__DIR__) . '/assets/js');
	}

	/**
	 * Get all Script-Urls they was set before (sorting,ordering,safe detecting)
	 *
	 * @param bool|null $relative
	 *
	 * @return array
	 * @throws \UnexpectedValueException If scripts must be output but not found
	 */
	public function getScriptUrls(?bool $relative = null): array
	{
		$_solved         = [];
		$scriptUrls      = [];
		$scriptsIterator = $this->getScriptsOrdering()->getIterator();

		while ($scriptsIterator->valid())
		{

			$item = $scriptsIterator->current();
			$scriptsIterator->next();
			if (\in_array($item, $_solved, true))
			{
				continue;
			}
			$_solved[] = $item;

			if ($this->isLockScript($item))
			{
				continue;
			}
			// todo output relative or absolute
			$tmp = $this->getScriptUrl($item);
			if ($tmp)
			{
				$scriptUrls[$item] = $tmp;
			}
		}

		return $scriptUrls;
	}

	/**
	 * Get all Script-Items they was set before (sorting,ordering,safe detecting)
	 *
	 * @param bool|null $relative
	 *
	 * @return array
	 * @throws \UnexpectedValueException If scripts must be output but not found
	 */
	public function getScriptItems( ?bool $relative = null ): array {
		$_solved         = [];
		$scriptUrls      = [];
		$scriptsIterator = $this->getScriptsOrdering()->getIterator();

		while ( $scriptsIterator->valid() ) {

			$item = $scriptsIterator->current();
			$scriptsIterator->next();
			if ( \in_array( $item, $_solved, true ) ) {
				continue;
			}
			$_solved[] = $item;

			if ( $this->isLockScript( $item ) ) {
				continue;
			}
			// todo output relative or absolute
			$tmp = $this->getScriptItem( $item );
			if ( $tmp ) {
				$scriptUrls[ $item ] = $tmp;
			}
		}

		return $scriptUrls;
	}

	/**
	 * Adding an Override dir
	 *
	 * @param null|string $dir
	 * @param int|null    $priority
	 *
	 * @return bool has bin inserted or not
	 */
	public function addScriptDir(string $dir, ?int $priority = null): bool
	{
		if ($dir = Directories::getValidAbsoluteDirectory($dir))
		{
			$priority = $priority ?? $this->getScriptDirs()->getHighestPriority() + 1;

			$this->getScriptDirs()->insert($dir, (int) $priority);
			return true;
		}
		return false;
	}

	/**
	 * Try to get the first valid ScriptUrl
	 *
	 * @param string|null $name scriptName
	 *
	 * @return null|string relative url of the js File
	 * @throws \UnexpectedValueException
	 */
	public function getScriptUrl(?string $name = null): ?string
	{
		return null !== ( $scriptItem = $this->getScriptItem( $name ) ) ? $scriptItem->getRelativeDir() : null;
	}

	/**
	 * Getting the Script-Item which is the most wanted(ordering/priority)
	 *
	 * @param null|string $name
	 *
	 * @return \Jybrid\Scripts\Core|null
	 */
	public function getScriptItem( ?string $name = null ): ?Core {
		if ($this->isLockScript( $name)) {
			return null;
		}
		// check the Script-Type is in Concrete Directory
		if ( null !== ( $scriptItem = $this->getScriptByConcreteDirectory( $name ) ) ) {
			return $scriptItem;
		}

		if ( ($scriptQueue = $this->getScript( $name)) instanceof Queue && 0 < ($cnt = $scriptQueue->count())) {
			// iterate getScriptDirs and try to find the js File
			/** @var Queue $scriptQueue */
			foreach ($this->getScriptDirs() as $scriptDir) {
				if ( null === ($absDir = Directories::getValidAbsoluteDirectory( $scriptDir))) {
					// not valid Directory
					continue;
				}

				$sqIterator = $scriptQueue->getIterator();
				/** @var \Jybrid\Scripts\Base $scriptItem */
				while ($sqIterator->valid()) {

					/** @var \Jybrid\Scripts\Core $scriptItem */
					$scriptItem = $sqIterator->current();

					// do NOT try to render concrete js files
					if ( '' === (string) $scriptItem->getDir() && null !== ($relOutPath = $this->getSaveRelativeOutFile( $absDir, $scriptItem->getFileName()))) {
						$scriptItem->setRelativeDir( $relOutPath );

						return $scriptItem;
					}
					$sqIterator->next();
				}
			}
			throw new \UnexpectedValueException( $name . ' js-file was not found in any scriptDir');
		}
		throw new \UnexpectedValueException( $name . ' js-file was never set by an addScript Method');
	}

	/**
	 * Maybe Javascripts should output directly without searching in directory queue
	 *
	 * @param null|string $name
	 *
	 * @return null|\Jybrid\Scripts\Core
	 */
	protected function getScriptByConcreteDirectory( ?string $name = null ): ?Core
	{
		if ($this->isLockScript($name))
		{
			return null;
		}

		if (($scriptQueue = $this->getScript($name)) instanceof Queue && 0 < ($cnt = $scriptQueue->count()))
		{
			$sqIterator = $scriptQueue->getIterator();
			while ($sqIterator->valid())
			{
				/** @var \Jybrid\Scripts\Core $scriptItem */
				$scriptItem = $sqIterator->current();
				// First look up the Javascript has set an concrete Directory
				if ('' !== ($dir = (string) Directories::getValidAbsoluteDirectory($scriptItem->getDir())))
				{
					if (null !== ($relOutPath = $this->getSaveRelativeOutFile($dir, $this->getScriptFilename($scriptItem->getFileName()))))
					{
						// yes, we have an wanted custom specific directory for this javascript-file exists!
						$scriptItem->setRelativeDir( $relOutPath );

						return $scriptItem;
					}

					throw new \UnexpectedValueException( 'The directory(' . $dir . ') where the ' . $name . ' js file must be located does not exists' );
				}
				$sqIterator->next();
			}
		}
		return null;
	}

	/**
	 * Check existing of an JS file give back the relative valid url
	 *
	 * @param string $absDir
	 * @param string $scriptFileName
	 *
	 * @return null|string string relative Url for an existing JS File
	 */
	protected function getSaveRelativeOutFile(string $absDir, string $scriptFileName): ?string
	{
		if (file_exists($fPath = Directories::concatPaths($absDir, $scriptFileName)))
		{
			return Directories::concatPaths(Directories::getValidRelativeDirectory($absDir), $scriptFileName);
		}
		return null;
	}

	/**
	 * Different scripts have an identifier by "scriptName"
	 *
	 * @param string|null $name
	 *
	 * @return null|Queue
	 */
	public function getScript(string $name = null): ?Queue
	{
		$scripts = $this->getScripts();

		return $scripts[$name] ?? null;
	}

	/**
	 * Getting the minimized or regular js-filename
	 *
	 * @param $sFilename
	 *
	 * @return string
	 */
	private function getScriptFilename(?string $sFilename = null): string
	{
		if (\is_string($sFilename) && false === self::getInstance()->getConfiguration()->isUseUncompressedScripts())
		{
			return str_replace('.js', '.min.js', $sFilename);
		}

		return $sFilename;
	}

	/**
	 * Adding an Script
	 *
	 * @example new Jybrid\Scripts\Core(['scriptName' => 'jybrid', 'fileName' => 'jybrid_core2.js']);
	 *          replaces the script 'jybrid' with the jybrid_core2.js override file
	 *
	 * @param null|Iface $script   script object
	 * @param int|null   $priority Higher value will be tried to render first
	 */
	public function addScript(Iface $script = null, ?int $priority = null): void
	{
		if ($script instanceof Iface)
		{
			$scriptName = $script->getScriptName();
			$scripts    = $this->getScripts();

			if (!array_key_exists($scriptName, $scripts))
			{
				$scripts[$scriptName] = new Queue();
			}

			if (null === $priority)
			{
				$priority = $scripts[$scriptName]->count() + 1;
			}

			// auto add Script type like jquery without explizit use the addScriptsOrdering
			if (!$this->getScriptsOrdering()->scriptExists($scriptName))
			{
				$this->getScriptsOrdering()->appendScript($scriptName);
			}

			$scripts[$scriptName]->insert($script, $priority);
			$this->setScripts($scripts);
		}
	}

	/**
	 * Scripts and location will be as singleton
	 *
	 * @return \Jybrid\Scripts\Scripts
	 */
	public static function getInstance(): Scripts
	{
		static $self;
		if (!$self)
		{
			$self = new self();
		}

		return $self;
	}

	/**
	 * All searchDirectories where Javascript-Files can be located
	 *
	 * @return Queue
	 */
	public function getScriptDirs(): Queue
	{
		return $this->scriptDirs;
	}

	/**
	 * On Large PHP/WebApps there are many ways and points where somebody adds an script which you do not want to display/use.
	 *
	 * @example 'jybrid' 'jybrid.debug' 'jQuery'
	 *
	 * @param string|null $name
	 */
	public function setLockScript(string $name = null): void
	{
		if (!$this->isLockScript($name))
		{
			$this->lockedScripts[$name] = true;
		}
	}

	/**
	 * Remove the Lock of an Script
	 *
	 * @param string|null $name
	 */
	public function removeLockScript(string $name = null): void
	{
		if ($this->isLockScript($name))
		{
			unset($this->lockedScripts[$name]);
		}
	}

	/**
	 * @param string|null $name
	 *
	 * @return bool
	 */
	public function isLockScript(?string $name = null): bool
	{
		return null !== $name && array_key_exists($name, $this->lockedScripts);
	}

	/**
	 * ScriptsOrdering means, that scripts pushed into an separate queue. The queue handles which "scriptName" url must be rendered before others
	 *
	 * @return ScriptsOrdering
	 */
	protected function getScriptsOrdering(): ScriptsOrdering
	{
		return $this->scriptsOrdering;
	}

	/**
	 * @return \Jybrid\Configuration\Scripts
	 */
	public function getConfiguration(): \Jybrid\Configuration\Scripts
	{
		return $this->configuration;
	}

	/**
	 * @return array
	 */
	public function getScripts(): array
	{
		return $this->scripts;
	}

	/**
	 * @param array $scripts
	 */
	private function setScripts(array $scripts): void
	{
		$this->scripts = $scripts;
	}
}
