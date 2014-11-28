<?php
namespace Rocketeer\Traits;

trait Parallelizable
{
	/**
	 * @return boolean
	 */
	public function isParallelizable()
	{
		return $this->parallelizable;
	}

	/**
	 * @param boolean $parallelizable
	 */
	public function setParallelizable($parallelizable)
	{
		$this->parallelizable = $parallelizable;
	}
}
