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

if(defined('sharedMemory_class')) return;
define('sharedMemory_class', 1);

if(!isWindows)
{
	class sharedMemory
	{
		const KEY_BLOCK_NAME = '_mem_block_name';
		const KEY_CREATED = '_mem_time_created';
		const KEY_LAST_ACCESSED = '_mem_last_accessed';
		private $shm_id;
		
		public function __construct($seg_key, $name, $size)
		{
			if(!is_numeric($seg_key))
			{
				trigger_error("Segment key must be numeric.",E_USER_ERROR);
			}
			else if( !is_string($name) )
			{
				trigger_error("Block name must be string.",E_USER_ERROR);
			}
			
			if($shm_id = shm_attach($seg_key, $size, 0600))
			{
				$this->shm_id = $shm_id;
				
				$block_name = $this->read(self::KEY_BLOCK_NAME);
				
				//if no block name defined
				if(!$block_name)
				{
					//assign a name to the allocated memory block
					$this->write(self::KEY_BLOCK_NAME,$name);
					$this->write(self::KEY_CREATED,time());
				}
				else if(strcmp($name, $block_name) != 0)
				{
					throw new Exception("Block name mismatched.");
				}
			}
			else
			{
				throw new Exception("Unable to create or open shared memory segment.");
			}
			
			$this->updateAccessTime();
		}
		
		private function updateAccessTime()
		{
			shm_put_var($this->shm_id, self::KEY_LAST_ACCESSED, time());
		}
		
		public function getLastAccessTime()
		{
			return $this->read(self::KEY_LAST_ACCESSED);
		}
		
		public function getBlockSize()
		{
			return shmop_size($this->shm_id);
		}
		
		public function write($key, $value)
		{
			$this->updateAccessTime();
			return shm_put_var($this->shm_id, $key, $value);
		}
		
		public function read($key)
		{
			$this->updateAccessTime();
			return shm_get_var($this->shm_id, $key);
		}
		
		public function varUnset($key)
		{
			$this->updateAccessTime();
			return shm_remove_var($this->shm_id, $key);
		}
		
		public function flush()
		{
			return shm_remove($this->shm_id);
		}
		
		public function detach()
		{
			$this->updateAccessTime();
			return shm_detach($this->shm_id);
		}
		
		public function __destruct()
		{
			$this->detach();
		}
	}
}
else if(isWindows)
{
	class sharedMemory
	{
		const KEY_BLOCK_NAME = '_mem_block_name';
		const KEY_CREATED = '_mem_time_created';
		const KEY_LAST_ACCESSED = '_mem_last_accessed';
		private $shm_id;
		private $used_space = 0;
		
		public function __construct($seg_key, $name, $size)
		{
			if(!is_numeric($seg_key))
			{
				trigger_error("Segment key must be numeric.",E_USER_ERROR);
			}
			else if( !is_string($name) )
			{
				trigger_error("Block name must be string.",E_USER_ERROR);
			}
			
			
			if($shm_id = shmop_open($seg_key, 'c', 0600, $size ))
			{
				$this->shm_id = $shm_id;
				
				$block_name = $this->read(self::KEY_BLOCK_NAME);
				
				//if no block name defined
				if(!$block_name)
				{
					//assign a name to the allocated memory block
					$this->write(self::KEY_BLOCK_NAME,$name);
					$this->write(self::KEY_CREATED,time());
				}
				else if(strcmp($name, $block_name) != 0)
				{
					throw new Exception("Block name mismatched.");
				}
			}
			else
			{
				throw new Exception("Unable to create or open shared memory segment.");
			}
		}
		
		public function updateAccessTime()
		{
			$dataset = $this->internalUnpack();
			$dataset[self::KEY_LAST_ACCESSED] = time();
			$this->internalPack($dataset);
		}
		
		public function getLastAccessTime()
		{
			return $this->read(self::KEY_LAST_ACCESSED);
		}
		
		public function getFreeSpace()
		{
			return ($this->getBlockSize() - $this->getUsedSpace());
		}
		
		public function getUsedSpace()
		{
			return $this->used_space;
		}
		
		public function getBlockSize()
		{
			return shmop_size($this->shm_id);
		}
		
		public function __call($method, $args)
	  {
	  	$method = 'internal' . $method;
	  	if (!method_exists($this, $method)) 
	  	{
	  		throw new Exception("unknown method [$method]");
	    }
	    
	    $this->updateAccessTime();
	    return call_user_func_array(array($this, $method),$args);
	  }
		
		public function internalwrite($key, $value)
		{
			$dataset = $this->internalUnpack();
			$dataset[$key] = $value;
			$result = $this->internalPack($dataset);
			return $result;
		}
		
		public function internalread($key)
		{
			$dataset = $this->internalUnpack();
			$value = isset($dataset[$key]) ? $dataset[$key] : false;
			return $value;
		}
		
		public function varUnset($key)
		{
			$dataset = $this->internalUnpack();
			unset($dataset[$key]);
			$result = $this->internalPack($dataset);
			return $result;
		}
		
		public function flush()
		{
			return shmop_delete($this->shm_id);
		}
		
		public function detach()
		{
			$this->updateAccessTime();
			return shmop_close($this->shm_id);
		}
		
		private function internalPack(array $dataset)
		{
			$raw_data = serialize($dataset);
			$datalen = strlen($raw_data);
			
			if($this->getBlockSize() < $datalen)
			{
				trigger_error("Not enough allocated memory.",E_USER_ERROR);
			}
			
			$byte_written = shmop_write ($this->shm_id, $raw_data, 0);
			
			$this->used_space = $byte_written;
			
			if($byte_written == $datalen ) return true;
			
			return false;
		}
		
		private function internalUnpack()
		{
			$raw_data = shmop_read($this->shm_id, 0, $this->getBlockSize());
			$this->str_from_mem($raw_data);
			
			//if memory is uninitialized
			if(ord($raw_data[0]) != 0)
			{
				$dataset = unserialize($raw_data);
				return $dataset;
			}
			
			return array();
		}
			
		private function str_from_mem(&$value) 
		{
		  $i = strpos($value, "\0");
		  if ($i === false) {
		    return $value;
		  }
		  $result =  substr($value, 0, $i);
		  return $result;
		}
		
		public function __destruct()
		{
			$this->detach();
		}
	
	}
}


/* exmaple usage*/
/*
$a = new sharedMemory(123,"fun", 1000);
$a->write('fun', 1);
$a->write('halok', 5);
echo "key: _mem_block_name, value: " . $a->read('_mem_block_name') . "\n";
echo "key: halok, value: " . $a->read('halok') . "\n";
echo "Used space: " . $a->getUsedSpace() . " bytes\n"; //only works in Windows
echo "Free space: " . $a->getFreeSpace() . " bytes\n"; //only works in Windows
echo "Last Accessed: " . $a->getLastAccessTime();
*/
?>
