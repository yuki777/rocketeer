<?php
/*
 * This file is part of Rocketeer
 *
 * (c) Maxime Fabre <ehtnam6@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Rocketeer\Abstracts\Strategies;

abstract class AbstractPolyglotStrategy extends AbstractStrategy
{
	/**
	 * The various strategies to call
	 *
	 * @type array
	 */
	protected $strategies = [];

	/**
	 * The type of the sub-strategies
	 *
	 * @type string
	 */
	protected $type;

	/**
	 * Results of the last operation that was run
	 *
	 * @type array
	 */
	protected $results;

	/**
	 * Gather the missing X from a method
	 *
	 * @param string $method
	 *
	 * @return string[]
	 */
	protected function gatherMissingFromMethod($method)
	{
		$missing  = [];
		$gathered = $this->onStrategies($method);
		foreach ($gathered as $value) {
			$missing = array_merge($missing, $value);
		}

		return $missing;
	}

	/**
	 * @param callable|string $callback
	 *
	 * @return array
	 */
	protected function onStrategies($callback)
	{
		return $this->explainer->displayBelow(function () use ($callback) {
			$this->results = [];
			$queue         = [];

			foreach ($this->strategies as $strategy) {
				$instance = $this->getStrategy($this->type, $strategy, $this->options);
				if ($instance) {
					$instance->setRole($callback);
					$queue[$strategy] = $instance;
				}
			}

			return $this->queue->run($queue)->getResults();
		});
	}

	//////////////////////////////////////////////////////////////////////
	////////////////////////////// RESULTS ///////////////////////////////
	//////////////////////////////////////////////////////////////////////

	/**
	 * Whether the strategy passed or not
	 *
	 * @return boolean
	 */
	public function passed()
	{
		return $this->checkStrategiesResults($this->results);
	}

	/**
	 * Assert that the results of a command are all true
	 *
	 * @param boolean[] $results
	 *
	 * @return boolean
	 */
	protected function checkStrategiesResults($results)
	{
		$results = array_filter($results, function ($value) {
			return $value !== false;
		});

		return count($results) === count($this->strategies);
	}
}
