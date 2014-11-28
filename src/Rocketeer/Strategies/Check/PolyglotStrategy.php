<?php
namespace Rocketeer\Strategies\Check;

use Rocketeer\Abstracts\Strategies\AbstractPolyglotStrategy;
use Rocketeer\Interfaces\Strategies\CheckStrategyInterface;

class PolyglotStrategy extends AbstractPolyglotStrategy implements CheckStrategyInterface
{
	/**
	 * Check that the PM that'll install
	 * the app's dependencies is present
	 *
	 * @return boolean
	 */
	public function manager()
	{
		$this->onStrategies('manager');

		return $this->passed();
	}

	/**
	 * Check that the language used by the
	 * application is at the required version
	 *
	 * @return boolean
	 */
	public function language()
	{
		$this->onStrategies('language');

		return $this->passed();
	}

	/**
	 * Check for the required extensions
	 *
	 * @return array
	 */
	public function extensions()
	{
		$missing    = [];
		$extensions = $this->onStrategies('extensions');
		foreach ($extensions as $extension) {
			$missing = array_merge($missing, $extension);
		}

		return $missing;
	}

	/**
	 * Check for the required drivers
	 *
	 * @return array
	 */
	public function drivers()
	{
		$missing = [];
		$drivers = $this->onStrategies('drivers');
		foreach ($drivers as $driver) {
			$missing = array_merge($missing, $driver);
		}

		return $missing;
	}
}
