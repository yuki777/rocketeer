<?php
namespace Rocketeer\Dummies\Tasks;

use Rocketeer\Abstracts\AbstractTask;

class ParallelTask extends AbstractTask
{
	protected $dependencies = ['Rocketeer\Dummies\Tasks\RequiredParallelTask'];

	/**
	 * Run the task
	 *
	 * @return string
	 */
	public function execute()
	{
		// TODO: Implement execute() method.
	}
}
