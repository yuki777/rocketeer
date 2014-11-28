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

use Closure;

abstract class AbstractPolyglotStrategy extends AbstractStrategy
{
	/**
	 * The various strategies to call
	 *
	 * @type array
	 */
	protected $strategies = [];

	/**
	 * Results of the last operation that was run
	 *
	 * @type array
	 */
	protected $results;

	/**
	 * @param Closure|string $callback
	 *
	 * @return array
	 */
	protected function onStrategies($callback)
	{
		return $this->explainer->displayBelow(function () use ($callback) {
			$this->results = [];
			$queue         = [];

			foreach ($this->strategies as $strategy) {
				$instance = $this->getStrategy('Dependencies', $strategy);
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

		return count($results) == count($this->strategies);
	}
}
