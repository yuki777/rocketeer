<?php
namespace Rocketeer\Interfaces;

interface ParallelizableInterface
{
	/**
	 * @return boolean
	 */
	public function isParallelizable();

	/**
	 * @param boolean $parallelizable
	 */
	public function setParallelizable($parallelizable);
}
