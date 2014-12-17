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

use Illuminate\Support\Arr;
use Rocketeer\Abstracts\AbstractTask;
use Rocketeer\Bash;
use Rocketeer\Traits\Parallelizable;
use Rocketeer\Traits\Configurable;

/**
 * Core class for strategies
 *
 * @author Maxime Fabre <ehtnam6@gmail.com>
 */
abstract class AbstractStrategy extends AbstractTask
{
	use Configurable;

	/**
	 * @type array
	 */
	protected $options = [];

	/**
	 * Default role of the strategy
	 *
	 * @type string
	 */
	protected $role;

	/**
	 * @return string
	 */
	public function getRole()
	{
		return $this->role;
	}

	/**
	 * @param string $role
	 */
	public function setRole($role)
	{
		$this->role = $role;
	}

	/**
	 * Whether this particular strategy is runnable or not
	 *
	 * @return boolean
	 */
	public function isExecutable()
	{
		return true;
	}

	/**
	 * Run the task
	 *
	 * @return string
	 */
	public function execute()
	{
		return $this->{$this->role}();
	}

	//////////////////////////////////////////////////////////////////////
	////////////////////////////// HELPERS ///////////////////////////////
	//////////////////////////////////////////////////////////////////////

	/**
	 * Display what the command is and does
	 *
	 * @return $this
	 */
	public function displayStatus()
	{
		// Recompose strategy and implementation from
		// the class name
		$components = get_class($this);
		$components = explode('\\', $components);

		$name     = Arr::get($components, count($components) - 1);
		$strategy = Arr::get($components, count($components) - 2);

		$parent   = ucfirst($strategy);
		$concrete = str_replace('Strategy', null, $name);
		$details  = $this->getDescription();

		$this->explainer->display($parent.'/'.$concrete, $details);

		return $this;
	}
}
