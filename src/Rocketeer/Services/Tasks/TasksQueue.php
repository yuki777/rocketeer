<?php
/*
 * This file is part of Rocketeer
 *
 * (c) Maxime Fabre <ehtnam6@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Rocketeer\Services\Tasks;

use Closure;
use Exception;
use KzykHys\Parallel\Parallel;
use LogicException;
use Rocketeer\Connection;
use Rocketeer\Traits\HasHistory;
use Rocketeer\Traits\HasLocator;

/**
 * Handles running an array of tasks sequentially
 * or in parallel
 *
 * @author Maxime Fabre <ehtnam6@gmail.com>
 */
class TasksQueue
{
	use HasLocator;
	use HasHistory;

	/**
	 * @type Parallel
	 */
	protected $parallel;

	/**
	 * A list of Tasks to execute
	 *
	 * @var array
	 */
	protected $tasks;

	/**
	 * The Remote connection
	 *
	 * @var Connection
	 */
	protected $remote;

	/**
	 * @param Parallel $parallel
	 */
	public function setParallel($parallel)
	{
		$this->parallel = $parallel;
	}

	////////////////////////////////////////////////////////////////////
	////////////////////////////// SHORTCUTS ///////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Execute Tasks on the default connection and
	 * return their output
	 *
	 * @param string|array|Closure $queue
	 * @param string|string[]|null $connections
	 *
	 * @return boolean
	 */
	public function execute($queue, $connections = null)
	{
		if ($connections) {
			$this->connections->setConnections($connections);
		}

		// Run tasks
		$this->run($queue);
		$history = $this->history->getFlattenedOutput();

		return end($history);
	}

	/**
	 * Execute Tasks on various connections
	 *
	 * @param string|string[]      $connections
	 * @param string|array|Closure $queue
	 *
	 * @return boolean
	 */
	public function on($connections, $queue)
	{
		return $this->execute($queue, $connections);
	}

	////////////////////////////////////////////////////////////////////
	//////////////////////////////// QUEUE /////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Run the queue
	 * Run an array of Tasks instances on the various
	 * connections and stages provided
	 *
	 * @param string|array $tasks An array of tasks
	 *
	 * @throws Exception
	 * @return Pipeline
	 */
	public function run($tasks)
	{
		$tasks    = (array) $tasks;
		$queue    = $this->builder->buildTasks($tasks);
		$pipeline = $this->buildPipeline($queue);

		// Wrap job in closure pipeline
		foreach ($pipeline as $key => $job) {
			$pipeline[$key] = function () use ($job) {
				return $this->executeJob($job);
			};
		}

		return $this->runPipeline($pipeline);
	}

	/**
	 * Build a pipeline of jobs for Parallel to execute
	 *
	 * @param array   $queue
	 * @param boolean $flat
	 *
	 * @return Pipeline
	 */
	public function buildPipeline(array $queue, $flat = false)
	{
		$pipelineBuilder = new PipelineBuilder($this->app);
		if ($flat) {
			return $pipelineBuilder->buildFlatPipeline($queue);
		}

		return $pipelineBuilder->buildMultiserverPipeline($queue);
	}

	/**
	 * Run the queue, taking into account the stage
	 *
	 * @param Job $job
	 *
	 * @return boolean
	 */
	protected function executeJob(Job $job)
	{
		// Set proper server
		$this->connections->setConnection($job->connection, $job->server);

		// Create pipeline and set parallelizable state
		$pipeline = $this->buildPipeline($job->queue, true);

		foreach ($pipeline as $key => $task) {
			$pipeline[$key] = function () use ($task, $job) {
				if ($task->usesStages()) {
					$stage = $task->usesStages() ? $job->stage : null;
					$this->connections->setStage($stage);
				}

				// Here we fire the task, save its
				// output and return its status
				$state = $task->fire();
				$this->toOutput($state);

				// If the task didn't finish, display what the error was
				if ($task->wasHalted() || $state === false) {
					$this->command->error('The tasks queue was canceled by task "'.$task->getName().'"');

					return false;
				}

				return true;
			};
		}

		return $this->runPipeline($pipeline);
	}

	//////////////////////////////////////////////////////////////////////
	////////////////////////////// RUNNERS ///////////////////////////////
	//////////////////////////////////////////////////////////////////////

	/**
	 * Run the pipeline in order.
	 * As long as the previous entry didn't fail, continue
	 *
	 * @param Pipeline $pipeline
	 *
	 * @return Pipeline
	 */
	protected function runSynchronously(Pipeline $pipeline)
	{
		$results = [];

		/** @type Closure $task */
		foreach ($pipeline as $key => $task) {
			$results[$key] = $task();
			if (!$results[$key]) {
				break;
			}
		}

		// Update Pipeline results
		$pipeline->setResults($results);

		return $pipeline;
	}

	/**
	 * Run the pipeline in parallel order
	 *
	 * @param Pipeline $pipeline
	 *
	 * @return Pipeline
	 * @throws \Exception
	 */
	protected function runAsynchronously(Pipeline $pipeline)
	{
		$this->parallel = $this->parallel ?: new Parallel();

		// Check if supported
		if (!$this->parallel->isSupported()) {
			throw new Exception('Parallel jobs require the PCNTL extension');
		}

		try {
			$results = $this->parallel->values($pipeline->all());
			$pipeline->setResults($results);
		} catch (LogicException $exception) {
			return $this->runSynchronously($pipeline);
		}

		return $pipeline;
	}

	/**
	 * @param Pipeline $pipeline
	 *
	 * @return Pipeline
	 * @throws Exception
	 */
	protected function runPipeline(Pipeline $pipeline)
	{
		// Cancel if empty pipeline
		if (!$pipeline->count()) {
			return $pipeline;
		}
		
		if ($this->getOption('parallel') && $pipeline->isParallelizable()) {
			return $this->runAsynchronously($pipeline);
		} else {
			return $this->runSynchronously($pipeline);
		}
	}
}
