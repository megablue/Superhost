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

if(defined('taskExecuter_class')) return;
define('taskExecuter_class', 1);

define('NO_CHAIN', 0);
define('CHAIN_IF_TRUE',1);
define('CHAIN_IF_FALSE',2);
define('STOP_CHAIN_IF_TRUE',3);
define('STOP_CHAIN_IF_FALSE',4);
define('DESTRUCT_IF_TRUE',5);
define('DESTRUCT_IF_FALSE',6);

class taskExecuter
{
	private $tasks = array();
	
	public function addTask(&$callerObj, $method, $condition = NO_CHAIN, $args = array())
	{
		if(!is_object($callerObj))
		{
			trigger_error("First argument must be an object.",E_USER_ERROR);
		}
		
		if(!method_exists($callerObj, $method))
		{
			trigger_error("Method doesn't belong to the object.",E_USER_ERROR);
		}
		
		$this->tasks[] = array('caller'=> $callerObj, 'method' => $method, 'condition' => $condition, 'arguments' => $args);
	}
	
	public function executeTask()
	{
		$tasks = $this->tasks;
		$prev_condition = NO_CHAIN;
		$resource = NULL;
		
		while($task = array_shift($tasks))
		{
			@ list($a, $b, $c, $d, $e, $f, $g, $h) = $task['arguments'];
									
			switch($task['condition'])
			{				
				case CHAIN_IF_TRUE:
					//echo "CHAIN TRUE:";
					if(isset($resource) && $resource)
					{
						$resource = $task['caller']->$task['method']($a, $b, $c, $d, $e, $f, $g, $h);
					}
				break;
				
				case CHAIN_IF_FALSE:
					//echo "CHAIN FALSE:";
					if(isset($resource) && !$resource)
					{
						$resource = $task['caller']->$task['method']($a, $b, $c, $d, $e, $f, $g, $h);
					}
				break;
				
				case STOP_CHAIN_IF_TRUE:
					//echo "STOP TRUE:";
					$resource = $task['caller']->$task['method']($a, $b, $c, $d, $e, $f, $g, $h);
					if(isset($resource) && $resource)
					{
						//echo "Chain Stopped\n";
						break 2;
					}
				break;
				
				case STOP_CHAIN_IF_FALSE:
					//echo "STOP FALSE:";
					$resource = $task['caller']->$task['method']($a, $b, $c, $d, $e, $f, $g, $h);
					if(isset($resource) && !$resource)
					{
						//echo "Chain Stopped\n";
						break 2;
					}
				break;
				
				case DESTRUCT_IF_TRUE:
					//echo "DESTRUCT TRUE:";
					$resource = $task['caller']->$task['method']($a, $b, $c, $d, $e, $f, $g, $h);
					if(isset($resource) && $resource)
					{
						//echo "Chain Stopped\n";
						return false;
					}
				break;
				
				case DESTRUCT_IF_FALSE:
					//echo "DESTRUCT FALSE:";
					$resource = $task['caller']->$task['method']($a, $b, $c, $d, $e, $f, $g, $h);
					if(isset($resource) && !$resource)
					{
						//echo "Chain Stopped\n";
						return false;
					}
				break;
				
				case NO_CHAIN:
				//echo "NO CHAIN:";
				$resource = $task['caller']->$task['method']($a, $b, $c, $d, $e, $f, $g, $h);
			}
			
			//echo "\n\n\nreturned value:";
			
			$prev_condition = $task['condition'];
		}
		
		return true;
	}
	
}
?>

