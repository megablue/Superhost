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

if(defined('hostAssistance_class')) return;
define('hostAssistance_class', 1);

class hostAssistance extends w3xBnetClient
{
	public $host;
	private $gamename = '';
	private $unidentify_players = array();
	private $identified_players = array();
	
	const HOST_STATUS_FREE = 0;
	const HOST_STATUS_RUNNING = 1;
	const IN_START	 = 0;
	const IN_ROOM 	 = 1;
	const IN_COUNTING = 2;
	const IN_LOADING = 3;
	const IN_GAME 	 = 4;
	const IN_FINSH 	 = 5;
		
	public function __construct($host_id, $remote_address, $remote_port=6112, $gameport=6112, $username, $creator, $gamename, $map_index, $gametype)
	{
		$this->game_type = $gametype;
		$this->map_index = $map_index;
		$this->game_name = $gamename;
		
		$this->host = new gameHost($this, $gameport, $username, $host_id, $creator, $gamename, $gametype);

		parent::__construct($remote_address, $remote_port, $gameport, $username);
		
		if($this->host->failed)
		{
			parent::sendText("/w $creator Failed to create the game. Perhpas the game port is occupied. Please try again later.");
			exit;
		}
	}
	
	public function process()
	{
		try
		{
			while($this->host->game_state < self::IN_GAME)
			{
				parent::select();
				parent::checkTimer();
				$this->host->accept();
				$this->host->select();
				$this->host->checkTimer();
			}
		  	  
			for(;;)
			{
				$this->host->select();
				$this->host->checkTimer();
			}
		}//catch predictable fatal errors
		catch (UndefinedVariable $e)
		{
			$this->host->setFatalError($e);
			//unset the host object will trigger the onSummary() method.
			//which will save the fatal error trace back as well.
			unset($this->host);
			exit;
		}
	}
	
	//hooks into joinChannel method
	public function joinChannel($channelName)
	{
		//uncomment to trigger the default actions
	  	//parent::joinChannel($channelName);
		parent::publishGame($this->map_index, $this->game_name, $this->game_type);
	}
  
	public function whereIs($player)
	{
		parent::sendText("/where $player");
		$player = strtolower($player);
	}
  
	public function removeFromCheckList($player)
	{
		$player = strtolower($player);
		$temp = array();
		foreach($this->identified_players as $identified_player)
		{
			if(strcmp($identified_player, $player) != 0)
			{
				$temp[] = $identified_player;
			}
		}

		$this->identified_players = $temp;
	}

	//hooks into onInform method
	public function onInform($event)
	{
		$info = $this->getChatEventInfo($event);
		$info_len = strlen($info);

		//parse player name
		for($i = 0, $player = ''; $info[$i] != ' ' && $i < $info_len; $i++)
		{
			$player .= $info[$i];
		}

		$player = strtolower($player);

		for($found = false; $i < $info_len; $i++)
		{
			if($info[$i] == '"')
			{
				$found = true;
				break;
			}
		}
			
		if($found)
		{
			for($i++, $found = false, $game_name = ''; $i < $info_len; $i++)
			{
				if($info[$i] != '"')
				{
					$found = true;
					$game_name .= $info[$i];
				}
				else
				{
					break;
				}
			}
			
			if(strcmp($game_name, $this->game_name) == 0)
			{
				$this->identified_players[] = $player;
			}
		}
	}

	public function getValidated()
	{
		return $this->identified_players;
	}
}

?>
