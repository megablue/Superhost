<?php
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

class Room
{
  public $max_player;
  private $slots = array();
  //private $seed = "\xFC\xEB\x3A\x06";
  private $seed = "\x11\x22\x33\x44";
  
  public function __construct($max_player)
  {
    $this->max_player = intval($max_player) ? intval($max_player) : 0;
    
    if($this->max_player == 0) return false;
    
    $this->initSlots();
  }
  
  private function initSlots()
  {
    //human players
    $player_id       = chr(0);      //player_id assigned by host
    $download_status = chr(0xFF);  //map download percentage (0xFF) = not client, not sure how to detect the player got the map or not yet
    $slot_status     = chr(0);      //0x00= open, 0x01 = closed, 0x02 = occupied by human player
    $controller      = chr(0);
    $controller_type = chr(01);
    $handicap        = chr(100);
    $max_player = $this->max_player;
    $reserved = chr(0);
    
    for($i=0; $i < $max_player; $i++)
    {
      $team_number     = ($i < 5) ? chr(0) : chr(1);    //team 0 = sentinel, team 1 = scourge.
      $color_number    = ($i < 5) ? chr($i + 1) : chr($i + 2);  //color
      $race_flag       = ($i < 5) ? "\x04" : "\x08"; //0x04 = night elf, 0x08 = undead
      
      //observer
      //01 64 02 00 0C 0C 60 01 64
      //if it is the last slot 
      //assign the gamehosting bot to this slot 
      if($i > 9)
      {
        $team_number  = chr(12);  //observer
        $color_number = chr(12);  //observer
        $race_flag    = "\x60";   //0x60 = observer
        $slot_status = chr(01);
        $controller_type = chr(00);
        /*
	        if(!$this->allow_human_observer)
	        {
	        	$slot_status = chr(01);
	        	$controller_type = chr(01);
	        }
	        */
      }
      
      //last slot = host
      if($i == $max_player - 1)
      {
        $player_id    = chr(01);
        $download_status = chr(100);
        $slot_status  = chr(2);
        $controller_type = chr(01); 
      }
       
      //for ease of remembering and accessing the slots
      //slots index will start from 1 instead of 0
      $this->slots[$i+1] = array(
      'player_id'       => $player_id,
      'download_status' => $download_status,
      'slot_status' => $slot_status,
      'controller'  => $controller,
      'team_number' => $team_number,
      'color_number' => $color_number,
      'race_flag'    => $race_flag,
      'controller_type' => $controller_type,
      'handicap' => $handicap,
      'reserved' => $reserved);
    }
  }
  
  public function printAll()
  {
  	echo "\nindex\tpid\tdl\tslot\tcont\tteam\tcolor\trace\ttype\thandi\n";
  	
  	for($i=1; $i <= $this->max_player; $i++)
  	{
  		$this->printSlot($i);
  	}
  }
  
  public function printSlot($slot_index)
  {
  	$slot_index = $this->validateSlotIndex($slot_index);
		
		$slot = $this->slots[$slot_index];;
		
		echo $slot_index . "\t" .
				 ord($slot['player_id']) . "\t" . 
				 ord($slot['download_status']) . "\t" .
				 ord($slot['slot_status']) . "\t" .
				 ord($slot['controller']) . "\t" .
				 ord($slot['team_number']) . "\t" .
				 ord($slot['color_number']) . "\t" .
				 ord($slot['race_flag']) . "\t" .
				 ord($slot['controller_type']) . "\t" .
				 ord($slot['handicap']) . "\n";
  }
  
  public function updateSlot($slot_index, $element, $ele_value)
  {
  	if( !($slot_index = $this->validateSlotIndex($slot_index) ) ) return false;
  	
  	$slot = $this->slots[$slot_index]; 
  	  	
  	//if uninitalize element found, treat it as invalid element.
  	if(!isset($slot[$element])) return false;
  	
  	if($this->checkElement($element, $ele_value))
  	{
  		$this->slots[$slot_index][$element] = chr($ele_value);
  	}
   	
  	return true;
  }
    
  private function getRawSlot($slot_index)
  {
		if( !($slot_index = $this->validateSlotIndex($slot_index) ) ) return false;
   	
   	//echo "here $slot_index   ";
   	$slot = $this->slots[$slot_index];
   	
   	//var_dump($slot);
   	
   	return $slot['player_id'] 
   	. $slot['download_status'] 
   	. $slot['slot_status'] 
   	. $slot['controller'] 
   	. $slot['team_number'] 
   	. $slot['color_number'] 
   	. $slot['race_flag'] 
   	. $slot['controller_type'] 
   	. $slot['handicap'];
  }
  
  private function getSlot($slot_index)
  {
		if( !($slot_index = $this->validateSlotIndex($slot_index) ) ) return false;
   	$slot = $this->slots[$slot_index];
   	return $slot;
  }
  
  public function getRawSlots($onJoin = false, $new_pid=0, $sockaddr_in = '')
	{
		$max_slots = chr($this->max_player);
		$raw_slots_packets = '';
		$assigned_pid = '';
		$hostside = '';
		$max_player = $this->max_player;
		
		//building raw slots data
		for($i=1; $i <= $max_player; $i++)
		{
			$raw_slots_packets .= $this->getRawSlot($i);
		}
		
		$tickcount =  $this->getSeed() . "\x03"; //GetTickCount WinAPI value of host
		$max_players = chr(0x0a);
		
		if($onJoin)
		{			
			//assigning new player id to the client
			$assigned_pid = chr($new_pid); 
			$salen = strlen($sockaddr_in);
			$hostside = $sockaddr_in;
			if($salen == 8)
			$hostside .= "\x00\x00\x00\x00\x00\x00\x00\x00";
		}
		
		$slotsinfo_data_size = chr( strlen($max_slots) 
																+ strlen($raw_slots_packets) 
																+ strlen($tickcount) 
																+ strlen($max_players)
															) . "\x00";
		$preoutput = $slotsinfo_data_size 
								. $max_slots 
								. $raw_slots_packets 
								. $tickcount 
								. $max_players 
								. $assigned_pid. $hostside;
		
		return $preoutput;
	}
	
	public function setMapStatus($player_id)
	{
		$slot_index = $this->pidToSlotIndex($player_id);
		$this->updateSlot($slot_index, 'download_status', 100);
	}
	
	public function openReferee()
	{
		$this->updateSlot(11, 'slot_status', 0);
		$this->updateSlot(11, 'controller_type', 1);
	}
	
	public function swap($slot_index_1, $slot_index_2)
	{
		$pslot1 = $this->getSlot($slot_index_1);
		$pslot2 = $this->getSlot($slot_index_2);
		$pid1 = ord($pslot1['player_id']);
		$pid2 = ord($pslot2['player_id']);
		
		$this->removePlayer($pid1);
		$this->removePlayer($pid2);		
		$this->updateSlot($slot_index_1, 'player_id', $pid2);
		$this->updateSlot($slot_index_1, 'slot_status', ord($pslot2['slot_status']) );
		$this->updateSlot($slot_index_1, 'download_status', ord($pslot2['download_status']));
		$this->updateSlot($slot_index_2, 'player_id', $pid1);
		$this->updateSlot($slot_index_2, 'slot_status', ord($pslot1['slot_status']) );
		$this->updateSlot($slot_index_2, 'download_status', ord($pslot1['download_status']));
		
		
		return array($pid1, $pid2);
	}
	
	public function switchTeam($player_id, $team)
	{
		$i = $this->pidToSlotIndex($player_id);
		
		switch($team)
		{
			//sentinel
			case 0x00:
				$start_offset = 1;
				$end_offset = 5;
			break;
			
			//scourge
			case 0x01:
				$start_offset = 6;
				$end_offset = 10;
			break;
			
			//observer
			case 0x0C:
				$start_offset = 11;
				$end_offset = 12;
			break;
		}
		
		$max_loop = $end_offset - $start_offset;
		
		for($counter=0; $counter <= $max_loop; $i++, $counter++)
		{
			if($i < $start_offset || $i > $end_offset) $i = $start_offset;
			
			$slot = $this->getSlot($i);
			
			if( ord($slot['reserved']) == 0
				&& ord($slot['player_id'])== 0 
				&& ord($slot['slot_status'])== 0 
				&& ord($slot['controller_type']) == 1)
			{
				$this->removePlayer($player_id);
				
				$this->updateSlot($i, 'player_id', $player_id);
				$this->updateSlot($i, 'slot_status', 2);
				$this->updateSlot($i, 'download_status', 100);
				
				//return the new slot index
				if($i > 5) ++$i;
				
				return $i;
			}
		}
		
		return false;
	}
	
	public function pidToSlotIndex($player_id)
	{
		$max_player = $this->max_player;
		for($i=1; $i <= $max_player; $i++)
		{
			$slot = $this->getSlot($i);
			
			if(ord($slot['player_id'])== $player_id)
			{
				return $i;
			}
		}
		
		return false;
	}
	
	public function removePlayer($player_id)
	{
		if($slot_index = $this->pidToSlotIndex($player_id))
		{
			$this->updateSlot($slot_index, 'player_id', 0);
			$this->updateSlot($slot_index, 'slot_status', 0);
			$this->updateSlot($slot_index, 'download_status', 0xFF);
		}
	}
	
	public function releaseSlot($slot_index)
	{
		if(isset($this->slots[$slot_index]))
		{
			$this->updateSlot($slot_index, 'reserved', 0);
			return true;
		}
		
		return false;
	}
	
	public function reserveSlot($slot_index)
	{
		if(isset($this->slots[$slot_index]))
		{
			$this->updateSlot($slot_index, 'reserved', 1);
			return true;
		}
		
		return false;
	}
	
	public function insertPlayer($player_id)
	{
		$max_player = $this->max_player;
		for($i=1; $i <= $max_player; $i++)
		{
			$slot = $this->getSlot($i);
			
			//if the slot is free
			if(ord($slot['reserved']) == 0
			&& ord($slot['player_id'])== 0 
			&& ord($slot['slot_status'])== 0 
			&& ord($slot['controller_type']) == 1)
			{
				$this->updateSlot($i, 'player_id', $player_id);
				$this->updateSlot($i, 'slot_status', 2);
				
				if($i > 5) ++$i;
				return $i;
			}
		}
		
		return false;
	}
	
	private function getSeed()
	{
		if($this->seed != '') return $this->seed;
		
		for($i=0, $pingseed_01 = ''; $i < 4; $i++)
		{
			$pingseed_01 .= mt_rand(0, 0xF);
		}
		 
		for($i=0, $pingseed_02 = '' ; $i < 4; $i++)
		{
			$pingseed_02 .= mt_rand(0, 0xF);
		}
		 
		$pingseed_01 = intval($pingseed_01);
		$pingseed_02 = intval($pingseed_02);
		$part1 	 		= ($pingseed_01      ) & 0xFF;
		$part2 		  = ($pingseed_02 >> 8 ) & 0xFF;
		$part3 	 	  = ($pingseed_01 >> 8 ) & 0xFF;
		$part4 	 		= ($pingseed_02      ) & 0xFF;

		$this->seed = chr($part1).chr($part2).chr($part3).chr($part4);
		
		return $this->seed;
	}
  
  private function checkElement($element, $ele_value)
  {
  	//if the element value is not passed as numeric value
  	if(!is_numeric($ele_value)) return false;
  	switch($element)
  	{
  		case 'player_id': //player id
  			if($ele_value < 0 || $ele_value > 13)
				{
					echo "checkElement: Invalid value for player id.\n";
					return false;
				}
				return true;
			break;
				
				case 'download_status': //download status
					if($ele_value != 0xFF 
						&& $ele_value < 0 
						&& $ele_value > 100)
					{
						echo "checkElement: Invalid value for download status.\n";
						return false;
					}
					return true;
				break;
				
				case 'slot_status': //slot status
					if($ele_value < 0 || $ele_value > 2 )
					{
						echo "checkElement: Invalid value for controller.\n";
						return false;
					}
					return true;
				break;
				
				case 'controller': //Controller
					if($ele_value!= 0 && $ele_value != 1)
					{
						echo "checkElement: Invalid value for controller.\n";
						return false;
					}
					return true;
				break;
				
				case 'team_number': //Team Number
					if( $ele_value < 0 || $ele_value > 12 )
					{
						echo "checkElement: Invalid value for Team Number.\n";
						return false;
					}
					return true;
				break;
				
				case 'color_number': //Color Number
					if( $ele_value < 0 || $ele_value > 12 )
					{
						echo "checkElement: Invalid value for Color Number.\n";
						return false;
					}
					return true;
				break;
				
				case 'race_flag': //Race flags
					if( $ele_value != 0x01 &&  
							$ele_value != 0x02 &&  
							$ele_value != 0x04 &&
							$ele_value != 0x08 && 
							$ele_value != 0x20 &&  
							$ele_value != 0x40 &&
							$ele_value != 0x60
						)
					{
						echo "checkElement: Invalid value for race flag.\n";
						return false;
					}
					return true;
				break;
				
				case 'controller_type': //controller type
					if( $ele_value < 0 || $ele_value > 2 )
					{
						echo "checkElement: Invalid value for controller type.\n";
						return false;
					}
					return true;
				break;
				
				case 'handicap': //handicap
					if( ($ele_value < 50) || ($ele_value%10 != 0) )
					{
						echo "checkElement: Invalid value for handicap.\n";
						return false;
					}
					return true;
				break;
				
				case 'reserved':
					if($ele_value == 0 || $ele_value == 1)
					return true;
					
					return false;
				break;
		}
		//otherwise return false
		return false;
  }
  
  private function validateSlotIndex($slot_index)
  {
  	$slot_index = intval($slot_index) ? intval($slot_index) : 0;
   	
   	//if less than the mininum allowed value or maximum allowed slot
   	if($slot_index < 1 || $slot_index > 12) return false;
   	
   	return $slot_index;
  }
}

?>