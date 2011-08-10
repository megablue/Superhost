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

if(defined('player_class')) return;
define('player_class', 1);

class player
{	
	public $socket, $read_buffer = '', $write_buffer = '';
	public $socket_id = 0;
	private $player_name = '?', $player_id = 0;
	private $player_slot_index = 0;
	private $identified = false;
	private $created_on;
	private $remote_ip;
	private $remote_port;
	private $public_node_port;
	private $private_node_ip;
	private $client_event;
	private $slot_index;
	private $ping_time = 0;
	private $latency = -1;
	private $total_ping = 0;
	private $total_respond_time = 0;
	private $average_latency = 0;
	private $pong_time = 0;
	private $pingSent = 0;
	private $pongRecieved = 0;
	public $write_buffer_size = 0;
	private $readsize = 1024;
	private $writesize = 0;
	private $is_ready = false;
	private $sync_counter = 0;
	private $game_loaded = false;
	private $desync_counter = 0;
	private $load_time = -1;
	private $waiting = false;
	private $total_wait_time = 0;
	private $last_wait  = 0;
	private $voted_drop = false;
	private $hero_kills = 0;
	private $hero_deaths = 0;
	private $creep_kills = 0;
	private $creep_denies = 0;
	private $left_time = 0;
	private $left_reason = 0;
	private $last_written_buffer = array();
	private $command_timer;
	private $alive = true;
	
	public function __construct($new_client_socket)
	{
		$this->socket = $new_client_socket;
		$this->socket_id = (int) $new_client_socket;
		$this->created_on = time();
	}
	
	public function isOnline()
	{
		return $this->alive;
	}
	
	public function isOffline()
	{
		return !$this->alive;
	}
	
	public function setOffline()
	{
		$this->alive = false;
	}
	
	public function setLeftReason($value)
	{
		$this->left_reason = $value;
	}
	
	public function getLeftReason()
	{
		return $this->left_reason;
	}
	
	public function setLeftTime($gametime)
	{
		$this->left_time = $gametime;
	}
	
	public function getLeftTime()
	{
		return $this->left_time;
	}
	
	public function setHeroKills($value)
	{
		$this->hero_kills = intval($value);
	}
	
	public function setHeroDeaths($value)
	{
		$this->hero_deaths = intval($value);
	}
	
	public function setCreepKills($value)
	{
		$this->creep_kills = intval($value);
	}
	
	public function setCreepDenies($value)
	{
		$this->creep_denies = intval($value);
	}
	
	public function getHeroKills()
	{
		return $this->hero_kills;
	}
	
	public function getHeroDeaths()
	{
		return $this->hero_deaths;
	}
	
	public function getCreepKills()
	{
		return $this->creep_kills;
	}
	
	public function getCreepDenies()
	{
		return $this->creep_denies;
	}
	
	public function voteDrop()
	{
		$this->voted_drop = true;
	}
	
	public function isVotedDrop()
	{
		return $this->voted_drop;
	}
	
	public function clearVotedDrop()
	{
		$this->voted_drop = false;
	}
	
	//clear waiting status
	public function resume()
	{
		$this->waiting = false;
	}
	
	//time limit for command
	public function isAbleToCommand()
	{
		if( !isset($this->command_timer)
		   || strcmp(get_class($this->command_timer), 'timer') != 0)
		{
			$this->command_timer = new timer('flood', 30000);
			return true;
		}
		
		if($this->command_timer->isExpired())
		{
			return true;
		}
		
		return false;
	}
	
	public function isWaiting()
	{
		return $this->waiting ? true : false;
	}
	
	public function getWaitTime()
	{
		return $this->total_wait_time; //respresent time in miliseconds
	}
	
	public function updateWaitTime()
	{
		//if last lag spike is less than a minute
		if($this->last_wait + 60 > time())
		{
			$this->total_wait_time += 1000;
		}
		else
		{
			//otherwise clear the accumulated wait time
			$this->total_wait_time = 0;
		}
		$this->last_wait = time();
	}
	
	public function setWaiting()
	{
		if(!$this->waiting)
		{			
			$this->waiting = true;
		}
	}
	
	public function updateDesyncCounter()
	{
		$this->desync_counter++;
	}
	
	public function getDesyncCounter()
	{
		return $this->desync_counter;
	}
	
	public function setLoaded($time_taken_to_load)
	{
		$this->load_time = round($time_taken_to_load,0);
		$this->game_loaded = true;
	}
	
	public function getLoadTime()
	{
		return $this->load_time;
	}
	
	public function isLoaded()
	{
		return $this->game_loaded;
	}
	
	public function updateSyncCounter()
	{
		$this->sync_counter++;
	}
	
	public function getSyncCounter()
	{
		return $this->sync_counter;
	}
	
	public function setReady()
	{
		$this->is_ready = true;
	}
	
	public function unsetReady()
	{
		$this->is_ready = false;
	}
	
	public function isReady()
	{
		return $this->is_ready;
	}
	
	public function getReadBufferSize()
	{
		return $this->readsize;
	}
	
	public function getWriteBufferSize()
	{
		return $this->writesize;
	}
	
	public function setReadBufferSize($input)
	{
		$input = intval($input);
		$this->readsize  = ($input && $input > 0) ? $input : 32;
	}
	
	public function setWriteBufferSize($input)
	{
		$input = intval($input);
		$this->writesize  = ($input > -1) ? $input : 32;
	}
	
	public function pingSent()
	{
		return $this->pingSent;
	}
	
	public function pongRecieved()
	{
		return $this->pongRecieved;
	}
	
	public function updateWriteBufferSize($byte_written)
	{
		$this->write_buffer_size -= $byte_written;
	}
	
	public function fillWriteBuffer($buffer)
	{				
		$this->write_buffer .= $buffer;
		$s = strlen($this->write_buffer); 
		$this->write_buffer_size = $s;
		return $s;
	}
	
	public function logBuffer($buffer)
	{
		//if the array has 20 elements
		if(count($this->last_written_buffer) == 50)
		{
			//return the first element to keep the last 19 elements
			$temp = array_shift($this->last_written_buffer);
			//remove the first element from the memory
			unset($temp);
		}
		
		if($buffer[1] != "\x0C")
		$this->last_written_buffer[] = $buffer;
	}
	
	public function getBufferLog()
	{
		return array_shift($this->last_written_buffer);
	}
	
	public function gamePingUpdate()
	{
		static $flag = true;
		
		if($flag)
		{
			$this->updatePingTime();
		}
		else
		{
			$this->updatePongTime();
		}
		
		//flip the flag
		$flag = $flag ? false : true;
	}
	
	//time when host sends ping
	public function updatePingTime()
	{
		$this->ping_time = microtime(true);
		++$this->pingSent;
	}
	
	//time when client echo the ping
	public function updatePongTime()
	{
		$pong_time = microtime(true);
		
		$this->latency = round($pong_time - $this->ping_time, 3) * 1000;
		++$this->total_ping;
		$this->total_respond_time += $this->latency;
		
		$avg_lantency = ($this->total_respond_time / $this->total_ping);
		$this->average_latency = round( $avg_lantency, 0);
		$this->pong_time = time();
		++$this->pongRecieved;
	}
	
	public function getTeam()
	{
		$index = $this->player_slot_index;
		switch($index)
		{
			case $index < 6:
				return 1;
			break;
			
			case $index > 5 && $index < 11:
				return 2;
			break;
			
			case $index > 10:
				return 3;
			break;
		}
	}
	
	public function getLatency()
	{
		return $this->latency;
	}
	
	public function getAverageLatency()
	{
		return $this->average_latency;
	}
	
	public function getPingTime()
	{
		return $this->ping_time;
	}
	
	public function getPongTime()
	{
		return $this->pong_time;
	}
	
	public function getSocketId()
	{
		return $this->socket_id;
	}
	
	public function setName($name)
	{
		$this->player_name = $name;
	}
	
	public function setClientEvent($event)
	{
		$this->client_event = $event;
	}
	
	public function getClientEvent()
	{
		return $this->client_event;
	}
	
	public function setPid($pid)
	{
		$this->player_id = $pid;
	}
	
	public function setSlot($slot_index)
	{
		$this->player_slot_index = $slot_index;
	}
	
	public function setRemoteIP($dotted_ip)
	{
		$this->remote_ip = $this->setIP($dotted_ip);
	}
		
	public function setPrivateNodeIP($dotted_ip)
	{
		$this->private_node_ip = $this->setIP($dotted_ip);
	}
	
	public function setPublicNodePort($port)
	{
		$this->public_node_port = $this->setPort($port);
	}
	
	public function setRemotePort($port)
	{
		$this->remote_port = $this->setPort($port);
	}
	
	public function getRemoteIP()
	{
		return $this->remote_ip;
	}
	
	public function getRemotePort()
	{
		return $this->remote_port;
	}
	
	public function getPublicNodeIP()
	{
		return $this->public_node_ip;
	}
	
	public function getPrivateNodeIP()
	{
		return $this->private_node_ip;
	}
	
	public function getPublicNodePort()
	{
		return $this->public_node_port;
	}
	
	//where the player connected from, remote ip and remote port
	public function getPublicNetworkByte()
	{
		return getNetworkByte($this->getRemoteIP(), $this->getRemotePort());
	}
	
	//player game port and public ip to act as node for WAN
	public function getPublicNodeNetworkByte()
	{
		return getNetworkByte($this->getRemoteIP(), $this->getPublicNodePort());
	}
	
	//player game port and private ip to act as node for LAN
	public function getPrivateNodeNetworkByte()
	{
		return getNetworkByte($this->getPrivateNodeIP(), $this->getPublicNodePort());
	}
		
	private function setPort($port)
	{
		$port = intval($port);
		if($port == 0)
		{
			throw new hostException("Invalid remote port.");
		}
		return $port;
	}
	
	private function setIP($IP)
	{
		$IP = ip2long($IP);
		if($IP == -1)
		{
			throw new hostException("Invalid remote IP address.");
		}
		return $IP;
	}
	
	public function getPid()
	{
		return $this->player_id;
	}
	
	public function getName()
	{
		return $this->player_name;
	}
	
	public function getSlot()
	{
		return $this->player_slot_index;
	}
	
	public function identify()
	{
		$this->identified = true;
	}
	
	public function isIdentified()
	{
		return $this->identified;
	}
	
}
?>
