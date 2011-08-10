<?PHP
/*
 * Copyright (C) 2007 megablue (evertchin@gmail.com, http://megablue.blogspot.com)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */

if(defined('timer_class')) return;
define('timer_class', 1);

class timer
{
	private $start_time, $end_time, $interval_time, $name;
	private $tasker;
	
	public function __construct($name,$expire_time)
	{
		$this->name = $name;
		$this->start_time = microtime(true);
		$this->interval_time = intval($expire_time);
		$this->tasker = new taskExecuter();
	}
	
	public function getTaskName()
	{
		return $this->name;
	}
		
	public function isExpired()
	{
		$this->end_time = microtime(true);
		
		$escaped_time = $this->end_time - $this->start_time;
		$escaped_time = round( $escaped_time, 3) * 1000;
		
		if($escaped_time >= $this->interval_time)
		{
			$this->start_time = microtime(true);
			return $escaped_time;
		}
		
		return false;
	}
	
	public function addTask(&$callerObj, $method, $condition, $args = array())
	{
		$this->tasker->addTask($callerObj, $method, $condition, $args);
	}
	
	public function executeTask()
	{
		return $this->tasker->executeTask();
	}
}
?>
