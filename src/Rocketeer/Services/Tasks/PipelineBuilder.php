<?php
namespace Rocketeer\Services\Tasks;

use Rocketeer\Abstracts\AbstractTask;
use Rocketeer\Exceptions\TaskCompositionException;
use Rocketeer\Traits\HasLocator;

class PipelineBuilder
{
	use HasLocator;

	/**
	 * Build a pipeline of jobs
	 *
	 * @param array $queue
	 *
	 * @return Pipeline
	 */
	public function buildPipeline(array $queue)
	{
		// First we'll build the queue
		$pipeline = new Pipeline();

		$queue = $this->decomposeDependenciesTree($queue);

		// Get the connections to execute the tasks on
		$connections = (array) $this->connections->getConnections();
		foreach ($connections as $connection) {
			$servers = $this->connections->getConnectionCredentials($connection);
			$stages  = $this->getStages($connection);

			// Add job to pipeline
			foreach ($servers as $server => $credentials) {
				foreach ($stages as $stage) {
					foreach ($queue as $jobs) {
						$pipeline[] = $this->createJob($connection, $server, $stage, $jobs);
					}
				}
			}
		}

		return $pipeline;
	}

	////////////////////////////////////////////////////////////////////
	//////////////////////////////// STAGES ////////////////////////////
	////////////////////////////////////////////////////////////////////

	/**
	 * Get the stages of a connection
	 *
	 * @param string $connection
	 *
	 * @return array
	 */
	public function getStages($connection)
	{
		$this->connections->setConnection($connection);

		$stage = $this->rocketeer->getOption('stages.default');
		if ($this->hasCommand()) {
			$stage = $this->getOption('stage') ?: $stage;
		}

		// Return all stages if "all"
		if ($stage == 'all' || !$stage) {
			$stage = $this->connections->getStages();
		}

		// Sanitize and filter
		$stages = (array) $stage;
		$stages = array_filter($stages, [$this, 'isValidStage']);

		return $stages ?: [null];
	}

	/**
	 * Check if a stage is valid
	 *
	 * @param string $stage
	 *
	 * @return boolean
	 */
	public function isValidStage($stage)
	{
		return in_array($stage, $this->connections->getStages());
	}

	//////////////////////////////////////////////////////////////////////
	//////////////////////////////// JOBS ////////////////////////////////
	//////////////////////////////////////////////////////////////////////

	/**
	 * Decomposes the queue into parallel/sequential tasks
	 *
	 * @param array $queue
	 *
	 * @return array
	 * @throws TaskCompositionException
	 */
	protected function decomposeDependenciesTree(array $queue)
	{
		$executed = [];
		$tree     = [];
		$job      = [];

		$flattenedQueue = array_map(function(AbstractTask $task) {
			return $task->getName();
		}, $queue);

		foreach ($queue as $key => $task) {
			$instance     = $this->builder->buildTask($task);
			$dependencies = (array) $instance->getDependencies();

			// Create a new Job if dependencies are not met
			$unmetDependencies = array_diff($dependencies, $executed);
			if ($dependencies && $unmetDependencies) {

				// If the dependency isn't in the queue, add it
				if (array_diff($dependencies, $flattenedQueue)) {
					$builtDependencies = $this->builder->buildTasks($dependencies);
					$job = array_merge($builtDependencies, $job);
				}

				$tree[]   = $job;
				$job      = [];
				$executed = array_merge($executed, $dependencies);
			}

			$job[] = $task;
		}

		$tree[] = $job;

		return $tree;
	}

	/**
	 * @param string $connection
	 * @param string $server
	 * @param string $stage
	 * @param array  $jobs
	 *
	 * @return Job
	 */
	protected function createJob($connection, $server, $stage, $jobs)
	{
		$job = new Job(array(
			'connection' => $connection,
			'server'     => $server,
			'stage'      => $stage,
			'queue'      => $jobs,
		));

		// If all the tasks in the job are parallelizable,
		// mark the Job as such
		$parallelizable = array_filter($jobs, function(AbstractTask $task) {
			return $task->isParallelizable();
		});

		if (count($parallelizable) === count($jobs)) {
			$job->setParallelizable(true);
		}

		return $job;
	}
}
