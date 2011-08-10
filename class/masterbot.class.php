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

if(defined('masterBot_class')) return;
define('masterBot_class', 1);

class masterBot extends w3xBnetClient
{
	private $user_commands_table = array
	(
		'c' => '_cmd_createGame',
		'c8' => '_cmd_createGame',
		'cre8' => '_cmd_createGame',
		'create' => '_cmd_createGame',
						
		'lm' => '_cmd_listmap',
		'listmap' => '_cmd_listmap',
			
		's' => '_cmd_set',
		'set' => '_cmd_set'
	);
	
	private $marshall_commands_table = array
	(
		'c' => '_cmd_createGame',
		'c8' => '_cmd_createGame',
		'cre8' => '_cmd_createGame',
		'create' => '_cmd_createGame',
		'vouch'	=> '_cmd_vouch'
	);
	
	private $admin_commands_table = array
	(
		'vouch'			=> '_cmd_vouch',
		'setadmin'			=> '_cmd_setadmin',
		'insertmap' => '_cmd_insertMap',
		'commit' => '_cmd_commit',
		'addhost' => '_cmd_addhost',
		'tourney'	=> '_cmd_tourney',
		'shutdown' => '_cmd_shutdown'
	);
	
	public $game_counter = 0;
	public $host;
	private $games = array();
	private $db;
	private $userlist, $adminlist;
	private $flood_list = array();
	private $free_host_timer;
	private $clear_flood_timer;
	private $last_host_id = 0;
	private $tourney_mode = false;
	
	const HOST_STATUS_FREE = 0;
	const HOST_STATUS_RUNNING = 1;
	const HOST_STATUS_FAILED = 2;
		
	public function __construct($remote_address, $remote_port=6112, $gameport=6112, $username)
	{
		parent::__construct($remote_address, $remote_port, $gameport, $username);
		global $dbserver, $dbusername, $dbpassword, $dbname;
		$this->db = new sql_db($dbserver, $dbusername, $dbpassword, $dbname, false);
		
		if(!$this->db->db_connect_id) 
		{
			die("failed to connect to database\n");
		}

		$this->userlist = new index(WORKING_PATH . 'data/vouched.txt');
		$this->adminlist = new index(WORKING_PATH . 'data/commanders.txt');
		$this->marshall_list = new index(WORKING_PATH . 'data/marshalls.txt');
		var_dump($this->marshall_list);
		$this->clear_flood_timer = new timer('clearflood', 3600000);
		$this->free_host_timer = new timer('free', 1000);
	}
		
	public function process()
	{
		for(;;)
		{
			$this->select();
			$this->checkTimer();
		}
	}
	
	public function checkTimer()
	{
		if($this->clear_flood_timer->isExpired())
		{
			unset($this->flood_list);
		}
		
		if($this->free_host_timer->isExpired())
		{
			$this->checkFreeHost();
		}
		
		//resumes the hooked method
		parent::checkTimer();
	}
		
	//hook the onCommand method
	public function onCommand($player, $command_string)
	{
		$cs_len = strlen($command_string);
		$command = '';
		for($i=1; $i < $cs_len && $command_string[$i] != ' '; $i++) $command .= $command_string[$i];
		
		$command = strtolower($command);
		
		$found_user_command = false;
		$found_marshall_command = false;
		$found_admin_command = false;
		//call the corresponding method to handle the command
		
		if(!$this->tourney_mode)
		if($this->userlist->search($player))
		{
			foreach($this->user_commands_table as $cmdkey => $function_pointer)
			{
				//if matched with the defined command 
				if( strcmp($command, $cmdkey) == 0 )
				{
					//call method dynamically
					if(method_exists($this, $function_pointer))
					{
						if(isset($this->flood_list[$player]) && isset($this->flood_list[$player]['cmd']))
						{
							if(!$this->flood_list[$player]['cmd']->isExpired())
							{
								return;
							}
						}
						else
						{
							$this->flood_list[$player]['cmd'] = new timer('flood', 5000);
						}
						
						$this->{$function_pointer}($player,$command_string);
					}
					else
					{
						parent::sendText('/w ' . $player . ' Invalid command method. Please inform admin about this issue.');
					}
					
					$found_user_command = true;
					break;
				}
			}
		}
		else
		{
			parent::sendText('/w ' . $player . ' Non-vouched player is not able to access.');
		}
		
		if($found_user_command) return;
		
		if($this->tourney_mode)
		{
			echo "$player issued\n";
			if($this->marshall_list->search($player))
			{
			echo "here\n";
				foreach($this->marshall_commands_table as $cmdkey => $function_pointer)
				{
					//if matched with the defined command 
					if( strcmp($command, $cmdkey) == 0 )
					{
						//call method dynamically
						if(method_exists($this, $function_pointer))
						{
							if(isset($this->flood_list[$player]) && isset($this->flood_list[$player]['cmd']))
							{
								if(!$this->flood_list[$player]['cmd']->isExpired())
								{
									return;
								}
							}
							else
							{
								$this->flood_list[$player]['cmd'] = new timer('flood', 5000);
							}
							
							$this->{$function_pointer}($player,$command_string);
						}
						else
						{
							parent::sendText('/w ' . $player . ' Invalid command method. Please inform admin about this issue.');
						}
						
						$found_user_command = true;
						break;
					}
				}
			}
			else
			{
				parent::sendText('/w ' . $player . ' Tournament mode enabled, only marshalls or admins are able to access.');
			}
		}
		
		if($found_marshall_command) return;
		
		if($this->adminlist->search($player))
		{
			foreach($this->admin_commands_table as $cmdkey => $function_pointer)
			{
				//if matched with the defined command 
				if( strcmp($command, $cmdkey) == 0 )
				{
					//call method dynamically
					if(method_exists($this, $function_pointer))
					{
						$this->{$function_pointer}($player,$command_string);
					}
					else
					{
						parent::sendText('/w ' . $player . ' Invalid command method. Please inform admin about this issue.');
					}
					
					$found_admin_command = true;
					break;
				}
			}
		}
		
		if($found_admin_command) return;
	}

	private function _cmd_tourney($player, $command_string)
	{
		if($this->tourney_mode)
		{
			parent::sendText('/w ' . $player . ' Tourney Mode Off');
			$this->tourney_mode = false;
		}
		else
		{
			parent::sendText('/w ' . $player . ' Tourney Mode On');
			$this->tourney_mode = true;
		}
	}
	
	private function _cmd_addhost($player, $command_string)
	{
		if(!$commandset = parseBotCommands($command_string)) return;
		
		if(isset($commandset[1]) && isset($commandset[2]))
		{
			$hostname = $this->db->sql_string('$'. $commandset[1]);
			$hostport = $commandset[2];
			$now = time();
			
			$sql = "INSERT INTO `superhost` (`hostname`, `gameport`, `lastupdate`) "
						." VALUES($hostname, '$hostport', '$now') ";
			if($result = $this->db->sql_query($sql)) 
			{
				parent::sendText('/w ' . $player . ' Successfully added a new host');
			}
			else
			{
				parent::sendText('/w ' . $player . ' Failed to add host');
			}
		}
	}
	
	private function _cmd_vouch($player, $command_string)
	{
		if(!$commandset = parseBotCommands($command_string)) return;
		
		if(!isset($commandset[1]))
		{
			parent::sendText('/w ' . $player . ' Please speficy an username to vouch. Usage: -vouch <username>');
		}
		else
		{
			$this->userlist->insert($commandset[1]);
			parent::sendText('Successfully vouched ' . $commandset[1]);
		}
	}
	
	private function _cmd_setadmin($player, $command_string)
	{
		if(!$commandset = parseBotCommands($command_string)) return;
		
		if(!isset($commandset[1]))
		{
			parent::sendText('/w ' . $player . ' Please speficy an username to promote as superhost admin. Usage: -setadmin <username>');
		}
		else
		{
			$this->adminlist->insert($commandset[1]);
			parent::sendText('Successfully promoted ' . $commandset[1]);
		}
	}
	
	private function _cmd_createGame($player, $command_string)
	{
		if(isset($this->flood_list[$player]) && isset($this->flood_list[$player]['game']))
		{
			if(!$this->flood_list[$player]['game']->isExpired())
			{
				return;
			}
		}
		else
		{		
			$this->flood_list[$player]['game'] = new timer('flood', 1800000);
		}

		//check is there any unoccupied host
		if($hostdata = $this->getFreeHost())
		{
			$mode = 'public';
			$map_index = -1;
		
			if(!$commandset = parseBotCommands($command_string)) return;
			
			/*
			if(isset($this->userlist[$player]) && isset($this->userlist[$player]))
			{
				$map_index = $this->userlist[$player]['map_index'];
			}
			*/
			
			$game_name = "HOSTED DA #" . $this->game_counter++;
			$game_name_cmd = str_replace(' ', '_', $game_name);
			
			$host_id = $hostdata['id'];
			$host_username = isWindows ? $hostdata['hostname'] : "\\" .$hostdata['hostname'];
			$host_gameport = $hostdata['gameport'];
			$cmd_string = WORKING_PATH . 'host.php ';
			$cmd_string .= '"--creator=' . $player . '" '; //who requested the game
			$cmd_string .= '"--username=' . $host_username . '" '; //host account
			$cmd_string .= '"--hostid=' . $host_id . '" '; //host id
			$cmd_string .= '"--gamemode=' . $mode . '" '; //public or  private
			$cmd_string .= '"--gameport=' . $host_gameport . '" '; //gameport
			$cmd_string .= '"--gamename=' . $game_name_cmd . '" '; //game title
			$cmd_string .= '"--map_index=' . $map_index . '" '; //game title
			
			echo "\n" . $cmd_string . "\n";
			
			$this->games[$host_username] = popen($cmd_string, 'r');
			$this->updateHostStatus($host_id, self::HOST_STATUS_RUNNING);
			parent::sendText('/w ' . $player . ' The game "'. $game_name .'" is created, please look for the game at Custom Games.');
			parent::sendText('The game "'. $game_name .'" is created, please look for the game at Custom Games.');
		}
		else
		{
			parent::sendText('/w ' . $player . ' Sorry, all hosting bots are currently occupied. Please try again later.');
		}
	}
		
	private function _cmd_insertMap($player, $command_string)
	{
		$public = true;
		if(!$commandset = parseBotCommands($command_string)) return;
		
		if(!isset($commandset[1]))
		{
			parent::sendText('/w ' . $player . ' Usage: /insertmap <version> <filename> <filesize> <signature> <crc32>');
		}
		else
		{
			if(!parent::insertMap($commandset[1], $commandset[2], $commandset[3], $commandset[4], $commandset[5]))
			{
				parent::sendText('/w ' . $player . ' Failed to insert map.');
			}
			else
			{
				parent::sendText('/w ' . $player . ' Successfully insert a new map, please use -commit to save changes');
			}
		}
	}
	
	private function _cmd_commit($player, $command_string)
	{
		if(!parent::commit())
		{
			parent::sendText('/w ' . $player . ' Failed to commit changes to map list. No permanent changes has been made.');
		}
		else
		{
			parent::sendText('/w ' . $player . ' Successfully committed changes to map list.');
		}
	}
	
	private function _cmd_listmap($player, $command_string)
	{
		$mapset = parent::getMapsInfo();
		
		parent::sendText('/w ' . $player . ' To select your prefered version use "-set map <index>"');
		parent::sendText('/w ' . $player . ' [index] [map filename] [default]');
		foreach($mapset as $map)
		{
			parent::sendText('/w ' . $player . ' ' . $map);
		}
	}
	
	//allow mutliple instances of user preferenece
	private function _cmd_set($player, $command_string)
	{
		parent::sendText('/w ' . $player . ' This command does nothing yet.');
	}
	
	private function _cmd_shutdown($player, $command_string)
	{
		exit;
	}
	
	private function updateHostStatus($host_id, $status)
	{
		$host_id = intval($host_id) ? intval($host_id) : 0;
		$status = intval($status) ? intval($status) : 0;
		$now = time();
		
		$sql = "UPDATE `superhost` SET `status` = '$status', `lastupdate` = '$now' WHERE `id`= '$host_id'";
		if(!$result = $this->db->sql_query($sql)) 
		{
			$sql_error = $db->sql_error();
			echo $sql_error['code'] . ': ' . $sql_error['message'] . "\n";
		}
		else
		{
			return true;
		}
		
		return false;
	}
	
	private function getFreeHost()
	{
		static $lastcall = 0;
		
		$sql = "SELECT `hostname` FROM `superhost` WHERE `status` = '0' AND `lastupdate` >= $lastcall";
		if(!$result = $this->db->sql_query($sql))
		{
			$sql_error = $this->db->sql_error();
			echo $sql_error['code'] . ': ' . $sql_error['message'] . "\n";
		}
		else
		{
			while($row = $this->db->sql_fetchrow($result))
			{
				$hostname = $row['hostname'];
				if(isset($this->games[$hostname]))
				{
					//free the pipe
					@ pclose($this->games[$hostname]);
					unset($this->games[$hostname]);
				}
			}
			
			$lastcall = time();
		}
		
		$sql = "SELECT `id`,`hostname`,`gameport` FROM `superhost` WHERE `status` = '0' ORDER BY `id` ASC LIMIT 0,1";
		if(!$result = $this->db->sql_query($sql)) 
		{
			$sql_error = $this->db->sql_error();
			echo $sql_error['code'] . ': ' . $sql_error['message'] . "\n";
		}
		else
		{
			if($row = $this->db->sql_fetchrow($result))
			{
				return $row;
			}
		}
		
		return false;
	}
	
	public function checkFreeHost()
	{
		$host_id = $this->last_host_id;
		
		$sql = "SELECT max(`id`) as `max_id` FROM `superhost` " 
					."WHERE `status` = '1' ";
		
		if(!$result = $this->db->sql_query($sql)) 
		{
			 $this->last_host_id = 0;
		}
		else
		{
			if($row = $this->db->sql_fetchrow($result))
			{
				if($row['max_id'] == $host_id)
				{
					$this->last_host_id = 0;
				}
			}
		}
		
		$sql = "SELECT `id`, `hostname` FROM `superhost` " 
					."WHERE `status` = '1' AND `id` > '$host_id' "
					."ORDER BY `id` ASC LIMIT 0,1 ";
		if(!$result = $this->db->sql_query($sql)) 
		{
			 $this->last_host_id = 0;
		}
		else
		{
			if($row = $this->db->sql_fetchrow($result))
			{
				$hostname = $row['hostname'];
				$this->sendText('/where ' . $hostname);
				$this->last_host_id = $row['id'];
			}
		}
		
	}
	
	public function onInform($event)
  {
  	$info = $this->getChatEventInfo($event);
	
  	if($info[0] == 'U')
  	{
  		$temp = packet_get_conts($info, 0, 8);
  		
  		if( strcmp($temp, 'User was') == 0 || strcmp($info, 'User is offline') == 0)
  		{
  			$this->updateHostStatus($this->last_host_id, self::HOST_STATUS_FREE);
  		}
  	}
  }
}

?>
