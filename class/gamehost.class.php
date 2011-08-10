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

class gameHost
{
	const USERNAME_MIN_LEN = 3;
	const USERNAME_MAX_LEN = 15;

	const W3GS_REQJOIN = "\x1E";
	const W3GS_MAPINFO = "\x42";
	const W3GS_PONG = "\x46";
	const W3GS_MSG = "\x28";
	const W3GS_LEAVE = "\x21";
	const W3GS_SLOTSYNC = "\x27";
	const W3GS_ACTSYNC = "\x26";
	const W3GS_GAMECHAT = "\x34";
	const W3GS_LOADED = "\x23";
	const W3GS_DROPREQ = "\x29";
	const W3GS_ONJOIN = "\x04";
	const W3GS_ONUPDATE = "\x09";
	
	//chat message sub header
	const MSG_ROOM_CHAT = "\x10";
	const MSG_SWITCH_TEAM = "\x11";
	const MSG_GAME_CHAT = "\x20";
	
	//Game state const
	const IN_START	 = 0;
	const IN_ROOM 	 = 1;
	const IN_COUNTING = 2;
	const IN_LOADING = 3;
	const IN_GAME 	 = 4;
	const IN_FINSH 	 = 5;
	const SYNC_TIMES = 1; //number of sync before considering the connection is timeout
	const HOST_STATUS_FREE = 0;
	const HOST_STATUS_RUNNING = 1;
	const HOST_STATUS_FAILED = 2;
	
	const GS_CLOSED = 0;
	const GS_OPEN = 1;
	const GS_FULL = 2;
	const GS_STARTED = 3;
	
	//network settings
	const DESYNCTHRESHOLD = 10; //must be unsigned int
	private $dyn_min_timeslot = 25;
	private $fixed_min_timeslot = 100;
	private $next_timeslot = 0;
	private $dynamic_timeslot = false;
	
	//leave game result
	const REASON_LEFT	 =  "\x07";
	const REASON_DC	 = "\x01";
	const SET_REASON_COMPLETED = 1;
	const SET_REASON_LEFT = 2;
	const SET_REASON_DC = 3;
	const SET_REASON_FORFEIT = 4;
	
	const TEAM_SENTINEL = 1;
	const TEAM_SCOURGE = 2;
	
	
	public $server_socket;
	public $game_state = self::IN_ROOM;
	public $failed = true;
	

	private $hostname;
	private $bind_port, $local_addr, $local_port;
	private $players = array();

	private $client_sockets = array();
	private $left_players = array();
	private $room;
	private $mapfilesize = -1;
	private $upload = 0;
	private $download = 0;
	private $hits = 0;
	private $remaining_seconds = 0;
	private $total_player = 0;
	private $actions = array();
	private $is_waiting = false;
	private $total_remaining_player = 0;
	private $creator = '';
	private $dota_mode = '';
	private $created_time = 0;
	private $started_time = 0;
	private $load_start_time = 0;
	private $db;
	private $host_id;
	private $max_sync_counter = 0;
	private $game_time = 0;
	private $waiting_screen_toggled = false;
	private $bot;
	private $winner = 0;
	private $dota_stats = array();
	private $coutdown_leavers = array();
	private $userlist;
	private $methods_log = array();
	private $fatal_error = false;
	private $fatal_error_event;
	
	//timers
	private $timers = array();
	private $ping_timer;
	private $room_life_timer;
	private $coutdown_timer;
	private $wait_timer;
	private $before, $after;
	
	//commands table
	private $commands_table = array
	(
		'p' => '_cmd_ping_all',
		'ping' => '_cmd_ping_all',
		'usage' => '_cmd_usage',
		
		//'debug' => '_cmd_debug',
		
		'r' => '_cmd_ready',
		'rdy' => '_cmd_ready',
		'ready' => '_cmd_ready',
	);
	
	private $blue_commands_table = array
	(
		'set' => '_cmd_set',
		'k'		=> '_cmd_kick',
		'kick'		=> '_cmd_kick',
		's'		=> '_cmd_swap',
		'referee' => '_cmd_referee',
		'start' => '_cmd_start',
		'swap'		=> '_cmd_swap',
		'shutdown' => '_cmd_shutdown'
	);
		
	public function __construct(hostAssistance &$bot, $bind_port, $hostname, $host_id, $creator, $gamename)
	{
		
		$this->bot = $bot;
		$this->started_time = time();
		if($bind_port < 1024 || $bind_port > 65535)
		{
			_die("Invalid port range", __FILE__, __LINE__, __FUNCTION__ );
			return false;
		}
		
		$this->hostname = $hostname;
		$this->creator = $creator;
		$this->game_name = $gamename;
		$this->room = new Room(12);
		$this->ping_timer = new timer('ping',5000);
		$this->reserver_timer = new timer('reserver', 60000);
		$this->room_life_timer = new timer('room', 300000);
		$this->wait_timer = new timer('wait',1000);
		$this->created_time = time();
	 	$this->userlist = new index(WORKING_PATH . 'data/vouched.txt', $read_only = true);
		$this->bind_port = $bind_port;
		call_method('reserveSlot',$this->room, 1);
	    
		$tries = 0;
		$hosted = false;
		$this->host_id = $host_id;

		while($this->failed)
		{
			try
			{
			  	call_method('buildSocket',$this);
				$this->failed = false;
			}catch (hostException $e) 
			{
				$this->failed = true;
				sleep(1);
				$tries++;
				
				if($tries > 60)
				{
					return;
				}
			}
		}

		$username_length = strlen($hostname);
			
		if( $username_length < self::USERNAME_MIN_LEN || $username_length > self::USERNAME_MAX_LEN )
		{
			_die("Invalid username", __FILE__, __LINE__, __FUNCTION__ );
		}
	}
		
	public function buildSocket()
	{
		$timeout['sec']  = 0;
		$timeout['usec'] = 0;
		if( !($this->server_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) )
		{
			throw new hostException("Could not create socket: ".$this->get_error());
		}
    
	    if(!@ socket_bind($this->server_socket, 0, $this->bind_port))
	    {
	      throw new hostException("Could not bind to {0.0.0.0}:{$this->bind_port}".$this->get_error());
	    }
	    
	    if (!@ socket_getsockname($this->server_socket, $this->local_addr, $this->local_port)) 
	    {
	      throw new hostException("Could not retrieve local address & port: ".$this->get_error());
	    }
	    
	    if (!@ socket_set_option($this->server_socket, SOL_SOCKET, SO_REUSEADDR, 1)) 
	    {
	      throw new hostException("Could not set SO_REUSEADDR: ".$this->get_error());
	    }
	    
	    if (!@ socket_set_nonblock($this->server_socket)) 
	    {
	      throw new hostException("Could not set socket to non-blocking: ".$this->get_error());
	    }
		
	    if (!@ socket_set_option($this->server_socket, SOL_SOCKET, SO_RCVTIMEO, $timeout )) 
	    {
	      throw new hostException("Could net set recieve timeout: ".$this->get_error());
	    }
	    
	    if (!@ socket_set_option($this->server_socket, SOL_SOCKET, SO_SNDTIMEO, $timeout )) 
	    {
	      throw new hostException("Could net set send timeout: ".$this->get_error());
	    }
	    
	    if( !@ socket_listen($this->server_socket) )
	    {
	    	throw new hostException("Unable to listen from socket".$this->get_error());
	    }
	}
	
	//when the server accepted a connection
	public function onAccept($new_client_socket)
	{
		$new_player = new player($new_client_socket, $this, (int)$new_client_socket);
		//store player object into players array with socket resource ids as keys to ease access
		$this->client_sockets[(int)$new_client_socket] = $new_client_socket;
		$this->players[(int)$new_client_socket] = $new_player;
	}
		
	//when a player finish loading
	public function onLoad(player $player)
	{
		$total_load_time = round(microtime(true), 3) - $this->load_start_time;
		call_method('setLoaded', $player, $total_load_time);
		
		//inform other clients the player is finish loading
		foreach($this->players as $client)
		{	
			if(call_method('isOffline', $client)) 
			continue;
			
			$sock_id = call_method('getSocketId', $client);
			$pid = call_method('getPid', $player);
			call_method('write', $this, $sock_id, "\xf7\x08\x05\x00" . chr($pid));
		}
		
		$allfinishloading = false;
		
		//check is it all clients are ready
		foreach($this->players as $client)
		{
			if(call_method('isOffline', $client)) 
			continue;
			
			if(!call_method('isLoaded', $client)) return;
		}
		
		//inform other clients superhost is finish loading
		foreach($this->players as $client)
		{
			if(call_method('isOffline', $client)) 
			continue;
			
			$sock_id = call_method('getSocketId', $client);
			call_method('write',$this, $sock_id, "\xf7\x08\x05\x00" . chr(01) );
		}
		
		$this->game_state = self::IN_GAME;
	}
	
	public function onRecieve($event_holder)
	{
		
		//echo "Recieved events...";
		$socket_id = call_method('getSocketId', $event_holder);
		$player = &$this->players[$socket_id];
		
		while($event = call_method('fetch', $event_holder))
		{
			//echo "process event..event type: \n";
			$event_type = $event[1];
			//packet_dump($event_type);
			//echo "end of event type\n";
	      
			switch($event_type)
			{
				//events after game starts
				//W3GS_SLOTSYNC 0x27
				case self::W3GS_SLOTSYNC:
					call_method('onSync', $this, $player, $event);
					//update the player latency
					call_method('gamePingUpdate', $player);
				break;
		    
			    //W3GS_ACTSYNC 0x26
			    case self::W3GS_ACTSYNC:
			    	call_method('onAction', $this, $player, $event);
			    break;
		    
				//W3GS_GAMECHAT1 0x28
			    //W3GS_GAMECHAT2 0x34
				case self::W3GS_MSG:
				case self::W3GS_GAMECHAT:
					call_method('onMessage', $this, $player, $event);
				break;
						
				//W3GS_LOADED 0x23
				case self::W3GS_LOADED:
					call_method('onLoad', $this, $player, $event);
				break;
						
				//W3GS_DROPREQ 0x29
				case self::W3GS_DROPREQ:
					call_method('onDropRequest', $this, $player, $event);
				break;
		      	
				//Events before game starts
				//W3GS_REQJOIN 0x1E
				case self::W3GS_REQJOIN:
					call_method('onJoin', $this, $player, $event);
				break;
						
				//W3GS_MAPINFO 0x42
				case self::W3GS_MAPINFO:
					call_method('onMapRequest', $this, $player, $event);
				break;
						
				//W3GS_PONG 0x46
				case self::W3GS_PONG:
					//echo "pong pong pong\n";
					call_method('onPong', $this, $player, $event);
				break;
						
				//W3GS_LEAVE 0x21
				case self::W3GS_LEAVE:
					//echo "Disconnecting player...\n";
					call_method('onLeave', $this, $player, $event);
					call_method('onDisconnect', $this, $player, $event);
				break;
			}
		}
	}
	
	//when the player request to drop player(s) in the waiting screen
	public function onDropRequest(player $player)
	{
		
		if(!$this->is_waiting) return;
		
		call_method('voteDrop', $player);
		
		$vote = 0;
		$waiter_count = 0;
		
		foreach($this->players as $othPlayer)
		{
			if(call_method('isOffline', $othPlayer)) 
			continue;
			
			if(!call_method('isWaiting', $othPlayer))
			{
				$waiter_count++;
				if(call_method('isVotedDrop', $othPlayer))
				{
					$vote++;
				}
			}
		}
				
		if($waiter_count == $vote)
		{
			foreach($this->players as $othPlayer)
			{
				if(call_method('isOffline', $othPlayer)) 
				continue;
				
				call_method('clearVotedDrop', $othPlayer);
				if(call_method('isWaiting', $othPlayer))
				{
					if(call_method('getWaitTime', $othPlayer) > 40000)
					{
						call_method('removeFromWaitScreen', $this, $othPlayer);
						call_method('onDrop', $this, $othPlayer);
						call_method('onDisconnect', $this, $othPlayer);
					}
				}
			}
			
			$found_waitee = false;
	  		foreach($this->players as $othPlayer)
	  		{
				if(call_method('isOffline', $othPlayer)) 
				continue;
			
	  			if(call_method('isWaiting', $othPlayer))
	  			{
	  				$found_waitee = true;
	  				break;
	  			}
	  		}
	  		
	  		//if no more interuppters 
	  		if(!$found_waitee)
	  		{
	  			//resume the game
	  			$this->is_waiting = false;
	  			$this->waiting_screen_toggled = false;
	  		}
		}
	}
	
	//when the player send a sync packet
	public function onSync(player $player, $packet)
	{
		
		call_method('updateSyncCounter', $player);
		
		//record the larger(largest?) sync number 
		if(call_method('getSyncCounter', $player) > $this->max_sync_counter)
		{
			$this->max_sync_counter = call_method('getSyncCounter', $player);
		}
	}
	
	public function onAction(player $player, $packet)
	{
		
		$actions_crc32 = ( ord($packet[4]) ) 
										+ ( ord($packet[5]) << 8) 
										+ ( ord($packet[6]) << 16) 
										+ ( ord($packet[7]) << 24);
		
		$reslen = strlen($packet);
		
		switch(ord($packet[8]))
		{
			//actions
			default:
				$raw_actions = '';	
				for($i=8; $i < $reslen; $i++)
				{
					$raw_actions .=  $packet[$i];
				}
				
				if(call_method('isValidActions', $this, $raw_actions, $actions_crc32))
				{
					call_method('parseTriggerEvent', $this, $raw_actions);
					$action_block['pid'] 		 = call_method('getPid', $player);
					$action_block['block'] 	 = $raw_actions;
					$this->actions[] = $action_block;
				}
				else
				{
					call_method('onLeave', $this, $player);
					call_method('onDisconnect', $this, $player);
				}
			break;
		}
	}
	
	public function parseTriggerEvent($actions)
	{
		$actions_len = strlen($actions);
				
		for($i = 0; $i < $actions_len; $i++)
		{
			if($actions[$i] == "\x6B")
			{
				//64 72 2E 78 00
				$max = $i + 6;				
				$gamecache_id = packet_get_string($actions, $i, 6, $i);
				if(strcmp($gamecache_id, 'kdr.x') == 0)
				{					
					$raw_slot_id = packet_get_string($actions, ++$i, 7, $i);
					$slot_id = intval($raw_slot_id) ? intval($raw_slot_id) : 0;
					
					//if it is not a valid slot id
					//possible it is a dota winner flag
					if($slot_id == 0)
					{
						//if winner team is not detected yet
						if(strcmp($raw_slot_id, 'Global') == 0)
						{
							$winner_flag = packet_get_string($actions, ++$i, 7, $i);
							if(strcmp($winner_flag, 'Winner') == 0)
							{
								$this->winner = DWORD2LONG(toEndian(packet_get_conts($actions, ++$i, 4)));
							}
						}
					}
					else //it is a valid slot id
					{
						$category_id = packet_get_string($actions, ++$i, 3, $i);
						$raw = packet_get_conts($actions, ++$i, 4);
						$value = DWORD2LONG(toEndian($raw));
						$this->dota_stats[] = array('slot_id' => $slot_id, 'cat' => $category_id, 'value' => $value);
					}
				}
			}

		}
	}
	
	public function broadcastActions()
	{
		
		$action_blocks = '';
		$this->after = microtime(true);
		$this->next_timeslot = round( ($this->after - $this->before), 3) * 1000;
		
		if($this->dynamic_timeslot)
		{
			$min_timeslot = $this->dyn_min_timeslot;
		}
		else
		{
			$min_timeslot = $this->fixed_min_timeslot;
		}
		
		//to prevent the superhost flood the clients
		if($this->next_timeslot < $min_timeslot)
		{
			$idle = ($min_timeslot - $this->next_timeslot) * 1000;
			
			$before_sleep = microtime(true);
			usleep($idle);
			$after_sleep = microtime(true);
			$time_spend_in_sleep = round( ($after_sleep - $before_sleep), 3) * 1000;
			
			if($time_spend_in_sleep > $min_timeslot)
			{
				$this->next_timeslot = $time_spend_in_sleep + $this->next_timeslot;
			}
			else
			{
				$this->next_timeslot = $min_timeslot;
			}
		}
		
		//merging recieved action blocks.
		if(!empty($this->actions))
		{			
			foreach($this->actions as $action)
			{
				$action_block_len  = toEndian(INT2WORD(strlen($action['block'])));
				$action_blocks .= chr($action['pid']) . $action_block_len . $action['block'];
			}
			
			$checksum  = toEndian(INT2WORD(crc32($action_blocks)));
			$preoutput = toEndian(INT2WORD($this->next_timeslot)) . $checksum . $action_blocks;
			$header 	 = call_method('buildHeader', $this, "\x0c", $preoutput);
			$action_output    = $header . $preoutput;
			//clear the array
			$this->actions = array();
			foreach($this->players as $player)
			{
				if(call_method('isOffline', $player)) 
				continue;
				
				$sock_id = call_method('getSocketId', $player);
				call_method('write', $this, $sock_id, $action_output);
			}
		}
		else
		{
			//dynamic caching mekanism
			$output = "\xf7\x0c\x06\x00" . toEndian(INT2WORD($this->next_timeslot));

			foreach($this->players as $player)
			{
				if(call_method('isOffline', $player)) 
				continue;
				
				$sock_id = call_method('getSocketId', $player);
				call_method('write', $this, $sock_id, $output);
			}
		}
		//update game time
		//to do, detect pause game
		$this->game_time += $this->next_timeslot;
	}
	
	public function isValidActions($actions, $crc32)
	{
		
		$crc32		= sprintf("%u", $crc32);
		$checksum = sprintf("%u", crc32($actions));
		
		if($checksum == $crc32)
		{
			return true;
		}
		
		return false;
	}
	
	public function onMessage(player $player, $packet)
	{
		
		$reslen = strlen($packet);
		$message_type_offset = ord($packet[4]) + 2 + 4;
		$message_type = $packet[$message_type_offset];
		switch($message_type)
		{
			//f7 28 0f 00 01 01 02 10 74 65 73 74 32 32 00
			/*
			//chat or fowarded message by host
				f7 0f 
				0e 00 
				02 ([1 byte]number of players to send to)
				02 03 ([N byte, depends on number of players to send to] list of player id )
				01 (message sender player id)  
				10 (message type, 0x10 = chat, 0x11 = switch team request)
				64 65 6e 67 00 (string, message null terminated) 
				
				F7 0F 
				1E 00 
				01 
				02 
				01 
				20 
				00 00 00 00 (chat type 00 = to all)
				68 65 6C 6C 6F 20 77 6F 72 6C 64 20 7E 7E 7E 7E 7E 00
			*/
			//F7 28 18 00 01 01 02 20 
			//00 00 00 00 
			//Chat To All
			
			//chat to allies
			//F7 28 11 00 01 02 03 20 
			//01 00 00 00 74 65 73 74 00 
			
			//private
			//F7 28 12 00 01 02 03 20 
			//04 00 00 00 68 65 6C 6C 6F 00 
			
			case self::MSG_GAME_CHAT:
				call_method('handleGameChat', $this, $player, $packet, $message_type, $message_type_offset, $reslen);
			break;
			
			//chat message
			case self::MSG_ROOM_CHAT:
				call_method('handleRoomChat', $this, $player, $packet, $message_type, $message_type_offset, $reslen);
			break;
			
			//switch team request
			case self::MSG_SWITCH_TEAM:
				call_method('handleSwitchTeam', $this, $player, ord($packet[$message_type_offset+1]) );
			break;
		}
	}
	
	function sendChatMessage($message)
	{
		$privateReciever = NULL;
		$game_state = $this->game_state;
		$message_header = '';
		if($game_state == self::IN_ROOM || $game_state == self::IN_COUNTING)
		{	
			//if private reciever not specified
			//forward the message to all players
			if($privateReciever == NULL)
			{
				foreach($this->players as $player)
				{
					if(call_method('isOffline', $player)) 
					continue;
					
					$pid = call_method('getPid', $player);
					$message_header .= chr($pid);
				}
			}
			else
			{
				$pid = call_method('getPid', $privateReciever);
				$message_header .= chr($pid);
			}
			
			$message_header = chr(strlen($message_header)) . $message_header;
			$preoutput = $message_header . "\x01\x10" . $message . "\x00";
			$header  	 = call_method('buildHeader', $this, "\x0f", $preoutput);
			$output    = $header . $preoutput;
			foreach($this->players as $player)
			{
				if(call_method('isOffline', $player)) 
				continue;
				
				if(call_method('isIdentified', $player))
				{
					$sock_id = call_method('getSocketId', $player);
					call_method('write', $this, $sock_id, $output);
				}
			}
		}
		else if($game_state == self::IN_GAME)
		{	
			if($privateReciever == NULL)
			{
				foreach($this->players as $player)
				{
					if(call_method('isOffline', $player)) 
					continue;
					
					$preoutput = "\x01\x01" . chr(0x01) . chr(0x20) . "\x00" . "\x00\x00\x00" . $message . "\x00";
					$header  	 = call_method('buildHeader', $this, "\x0f", $preoutput);
					$output    = $header . $preoutput;
					
					$sock_id = call_method('getSocketId', $player);
					call_method('write', $this, $sock_id, $output);
				}
			}
			else
			{
					$preoutput = "\x01\x01" . chr(0x01) . chr(0x20) . "\x00" . "\x00\x00\x00" . "# " . $message . "\x00";
					$header  	 = call_method('buildHeader', $this, "\x0f", $preoutput);
					$output    = $header . $preoutput;
					
					$sock_id = call_method('getSocketId', $privateReciever);
					call_method('write', $this, $sock_id, $output);
			}
		}
	}
	
	public function handleSwitchTeam(player $player, $team)
	{
		
		if($this->game_state != self::IN_ROOM) return;
		
		$pid = call_method('getPid', $player);
		//if switch team request success
		if($slot_index = call_method('switchTeam', $this->room, $pid, $team))
		{
			call_method('setSlot', $player, $slot_index);
		
			foreach($this->players as $othPlayer)
			{
				if(call_method('isOffline', $player)) 
				continue;
				
				if(call_method('isIdentified', $othPlayer))
				call_method('sendUpdatedSlots', $this, $othPlayer);
			}			

		}
	}
	
	public function handleGameChat(player $player, $packet, $message_type, $message_type_offset, $reslen)
	{	
		
		for($i=$message_type_offset+5, $message=''; $i < $reslen -1; $i++) $message .= $packet[$i];
		
		$chat_to = $packet[$message_type_offset+1];
	
		$message = trim($message);

		//forwarding message to other players...
		if(count($this->players) < 1) return;
		
		$preoutput = "\x01\x01" . chr(call_method('getPid', $player)) . $message_type . $chat_to . "\x00\x00\x00" . $message . "\x00";
		$header 	 = call_method('buildHeader', $this, "\x0f", $preoutput);
		$output    = $header . $preoutput;
		$temp_upload = strlen($output);
		switch($chat_to)
		{
			//toall
			case "\x00":
				foreach($this->players as $othPlayer)
				{
					if(call_method('isOffline', $othPlayer)) 
					continue;
				
					if(call_method('getPid', $othPlayer) != call_method('getPid', $player))
					{
						$sock_id = call_method('getSocketId', $othPlayer);
						call_method('write', $this, $sock_id, $output);
					}
				}
			break;
			
			//observer
			case "\x02":
			//allies
			case "\x01":
				foreach($this->players as $othPlayer)
				{
					if(call_method('isOffline', $othPlayer)) 
					continue;
				
					if(call_method('getPid', $othPlayer) != call_method('getPid', $player) 
					&& call_method('getTeam', $player) == call_method('getTeam', $othPlayer))
					{
						$sock_id = call_method('getSocketId', $othPlayer);
						call_method('write', $this, $sock_id, $output);
					}
				}
			break;
			
			//private
			case "\x04":
				for($i=5; $i < 7; $i++)
				{
					if(ord($packet[$i]) != call_method('getPid', $player))
					{
						$reciever_pid = ord($packet[$i]);
						break;
					}
				}
				
				//to do fix this
			break;
		}
		
		if($message[0] == "-") 
		call_method('onCommand', $this, $player, $message);
	}
	
	public function handleRoomChat(player $player, $packet, $message_type, $message_type_offset, $reslen)
	{
		
		for($i=$message_type_offset+1, $message=''; $i < $reslen -1; $i++) $message .= $packet[$i];
		
		$message = trim($message);
		
		//echo $this->players[$pid]['name'] . " says: " . $message . "\r\n";
		//forwarding message to other players...
		if(count($this->players) < 1) return;
		$preoutput = "\x01\x01" . chr(call_method('getPid',$player)) . $message_type . $message . "\x00";
		$header  	 = call_method('buildHeader', $this, "\x0f", $preoutput);
		$output    = $header . $preoutput;
		
		foreach($this->players as $othPlayer)
		{
			if(call_method('isOffline', $othPlayer)) 
			continue;
		
			if(call_method('getPid', $othPlayer) != call_method('getPid', $player)
			&& call_method('isIdentified', $othPlayer) )
			{
				call_method('write', $this, call_method('getSocketId', $othPlayer), $output);
			}
		}
		
		if($message[0] == "-")
		call_method('onCommand', $this, $player, $message);
	}
	
	public function onCommand(player $player, $message)
	{
		
		$msg_len = strlen($message);
		$command = '';
		for($i=1; $i < $msg_len && $message[$i] != ' '; $i++) $command .= $message[$i];

		$command = strtolower($command);
		
		$commands_table = $this->commands_table;
		$found = false;
		//call the corresponding method to handle the command
		foreach($commands_table as $cmdkey => $function_pointer)
		{
			//if matched with the defined command 
			if( strcmp($command, $cmdkey) == 0 )
			{
				//call method dynamically
				if(method_exists($this,$function_pointer))
				{
					if(call_method('isAbleToCommand', $player))
					{
						call_method($function_pointer, $this, $player, $message);
					}
				}
				
				$found = true;
				break;
			}
		}
		
		if($found) return;
		
		//if it is blue player or the game requester
		if( call_method('getSlot', $player) == 1 
		|| call_method('isRequester', $this, call_method('getName', $player) ) )
		{
			$commands_table = $this->blue_commands_table;
			
			foreach($commands_table as $cmdkey => $function_pointer)
			{
				//if matched with the defined command 
				if( strcmp($command, $cmdkey) == 0 )
				{
					//call method dynamically
					if(method_exists($this,$function_pointer))
					call_method($function_pointer, $this, $player, $message);
					
					break;
				}
			}
		}
	}
	
	public function isRequester($playername)
	{
		$playername = strtolower($playername);
		$requester = strtolower($this->creator);
		if(strcmp($requester,$playername) == 0)
		return true;
		
		return false;
	}
	
	public function _cmd_referee(player $player, $message)
	{
		static $open = false;
		
		if($open) return;
	
		if($this->game_state != self::IN_ROOM) return;
		
		call_method('openReferee', $this->room);
		
		foreach($this->players as $othPlayer)
		{
			if(call_method('isOffline', $othPlayer)) 
			continue;
			
			if(call_method('isIdentified', $othPlayer))
			call_method('sendUpdatedSlots', $this, $othPlayer);
		}
		
		call_method('sendChatMessage', $this, 'Referee slot opened...');
		$open = true;
	}
	
	public function _cmd_swap(player $player, $message)
	{
		
		if($this->game_state != self::IN_ROOM) return;
		
		$command = parseBotCommands($message);
		
		if(!isset($command[1]) && !isset($command[2]))
		{
			call_method('sendChatMessage', $this, 'Usage: -swap [1...10] [1...10]');
			foreach($this->players as $othPlayer)
			{
				if(call_method('isOffline', $othPlayer)) 
				continue;
			
				$tmp = $othPlayer->getSlot();
				$tmp = ($tmp > 5) ? --$tmp : $tmp;
				call_method('sendChatMessage', $this, "slot [" . $tmp . "]: " . call_method('getName', $othPlayer));
			}
			
			return;
		}
		
		$slot_1 = intval($command[1]) ? intval($command[1]) : 0;
		$slot_2 = intval($command[2]) ? intval($command[2]) : 0;
		
		if($slot_1 > 10 || $slot_2 > 10)
		{
			call_method('sendChatMessage', $this, 'You can not swap with an observer slot.');
			return;
		}
		else if( $slot_1 <= 0 || $slot_2 <= 0)
		{
			call_method('sendChatMessage', $this, 'You had entered an invalid value.');
			return;
		}
		
		list($pid_1, $pid_2) = call_method('swap', $this->room, $slot_1, $slot_2);
		
		foreach($this->players as $othPlayer)
		{
			if(call_method('isOffline', $othPlayer)) 
			continue;
		
			if(call_method('getPid', $othPlayer) == $pid_1)
			{
				$slot_index = call_method('pidToSlotIndex', $this->room, $pid_1);

				if($slot_index > 5) $slot_index++;
				
				call_method('setSlot', $othPlayer, $slot_index);
			}
			else if(call_method('getPid', $othPlayer) == $pid_2)
			{
				$slot_index = call_method('pidToSlotIndex', $this->room, $pid_2);
				if($slot_index > 5) $slot_index++;
				call_method('setSlot', $othPlayer, $slot_index);
			}
		}
		
		foreach($this->players as $othPlayer)
		{
			if(call_method('isOffline', $othPlayer)) 
			continue;
			
			if(call_method('isIdentified', $othPlayer))
			call_method('sendUpdatedSlots', $this, $othPlayer);
		}
		
	}
	
	public function _cmd_kick(player $player, $message)
	{
		
		if($this->game_state != self::IN_ROOM) return;
		
		$command = parseBotCommands($message);
		
		if(!isset($command[1])) 
		return;
		
		$kick_player = strtolower($command[1]);
		
		if(call_method('isRequester', $this, $kick_player))
		{
			call_method('sendChatMessage', $this, 'Unable to kick game requester.');
			return;
		}
		
		$found = false;
		foreach($this->players as $othPlayer)
		{
			if(call_method('isOffline', $othPlayer)) 
			continue;
		
			if(call_method('isIdentified', $othPlayer))
			{
				$playername = strtolower(call_method('getName', $othPlayer));
				if(strcmp($playername, $kick_player) == 0)
				{
					call_method('onLeave', $this, $othPlayer);
					call_method('onDisconnect', $this, $othPlayer);
					$found = true;
					break;
				}
			}
		}
		
		if(!$found)
		{
			call_method('sendChatMessage', $this, 'Unable to kick'  . $kick_player);
		}
	}
	
	public function _cmd_shutdown(player $player, $message)
	{
		
		if($this->game_state > self::IN_COUNTING) return;
		
		exit;
	}
	
	public function _cmd_start(player $player, $message)
	{
		
		if($this->game_state != self::IN_ROOM) return;
		
		if(!$this->isNameSpoofed())
		{
			call_method('sendChatMessage', $this, 'Forcefully starts the game.');
			call_method('initStartGameCountdown', $this);
			call_method('updateGameRoom', $this->bot, self::GS_FULL);
		}
		else
		{
			call_method('sendChatMessage', $this, 'Countdown is interuptted.');
		}
	}
	
	public function _cmd_ready(player $player, $message)
	{
		
		if($this->game_state != self::IN_ROOM) return;
		
		$command = parseBotCommands($message);
		
		call_method('setReady', $player);
		$total_player = 0;
		
		call_method('sendChatMessage', $this, call_method('getName', $player) . ' is ready.');
			
		foreach($this->players as $othPlayer)
		{
			if(call_method('isOffline', $othPlayer)) 
			continue;
		
			if(	call_method('isReady', $othPlayer)
			&& call_method('isIdentified', $othPlayer) )
			{
				++$total_player;
			}
		}
		
		if($total_player == 10)
		{
			if(!call_method('isNameSpoofed', $this))
			{
				call_method('sendChatMessage', $this, 'All players are ready.');
				call_method('initStartGameCountdown', $this);
				call_method('updateGameRoom', $this->bot, self::GS_FULL);
			}
			else
			{
				call_method('sendChatMessage', $this, 'Coutdown is interrupted');
			}
		}
	}
	
	public function StartGameCountdown()
	{
		
		--$this->remaining_seconds;
		if($this->remaining_seconds > 0)
		{			
			call_method('sendChatMessage', $this, 'Game starting in ' . $this->remaining_seconds);
			return true;
		}
		else
		{
			//Clean up mekanism
			$temp = array();
			foreach($this->players as $player)
			{
				if(call_method('isOnline', $player) 
				&& call_method('isIdentified', $player))
				{
					$sid = call_method('getSocketId', $player);
					$temp[$sid] = $player;
				}
				else
				{
					call_method('removeSocket', $this, $player);
				}
			}
			$this->players = $temp;
		
			unset($this->coutdown_timer);
			foreach($this->players as $player)
			{
				if(call_method('isOffline', $player)) 
				continue;
			
				++$this->total_player;
			}
			
			$this->total_remaining_player = $this->total_player;
			call_method('startGame', $this);
			return false;
		}
	}
	
	public function initStartGameCountdown()
	{
		
		$this->game_state = self::IN_COUNTING;
		$this->remaining_seconds = 11;
		
		$this->coutdown_timer = new timer('StartGameCountdown', 1000);
	}
	
	public function startGame()
	{		
		//trigger load process
		foreach($this->players as $player)
		{
			if(call_method('isOffline', $player)) 
			continue;
			
			call_method('write', $this, call_method('getSocketId', $player), "\xf7\x0a\x04\x00" );
			call_method('write', $this, call_method('getSocketId', $player), "\xf7\x0b\x04\x00" );
		}
		
		//announce the game is closed
		call_method('updateGameRoom', $this->bot, self::GS_STARTED);
		
		call_method('pingAll', $this);
		$this->game_state = self::IN_LOADING;
		$this->load_start_time = round(microtime(true), 3);
		//close the listening socket
		socket_close($this->server_socket);
		unset($this->server_socket);
		$this->game_time = 0;
	}
	
	public function _cmd_set(player $player, $message)
	{
		
		$command = parseBotCommands($message);
		
		if(!isset($command[1])) return;
		
		switch($command[1])
		{
			/*
			case 'read':
				$player->setReadBufferSize($command[2]);
				$summary = 'Read buffer set to ' . $player->getReadBufferSize();
			break;
			
			case 'write':
				$player->setWriteBufferSize($command[2]);
				$summary = 'Write buffer set to ' . $player->getWriteBufferSize();
			break;
			*/
			
			case 'dynamic':
				$this->dynamic_timeslot = true;
				$summary = 'Dynamic Transfer Mode Selected.';
			break;
			
			case 'static':
				$this->dynamic_timeslot = false;
				$summary = 'Static Transfer Mode Selected.';
			break;
			
			/*
			case 'error':
				//purposely calling an non existing method to demostrate the error handling
				call_method('throwError', $this);
			break;
			*/
			
			default:
				$summary = 'Invalid sub command.';
		}
		call_method('sendChatMessage', $this, $summary);
	}
		
	public function _cmd_usage(player $player, $message)
	{
		
		$upload_kb = round($this->upload / 1024, 1);
		$download_kb = round($this->download / 1024, 1);
		$total = $upload_kb + $download_kb;
		
		$summary = "Uploaded: " . $upload_kb . "KB" . " Downloaded: " . $download_kb . "KB Total: " . $total . "KB";
		call_method('sendChatMessage', $this, $summary);
	}
	
	public function _cmd_ping_all(player $player, $message)
	{
		
		$max = 0;
		$min = 100000;
		$total_respond_time = 0;
		
		foreach($this->players as $othPlayer)
		{
			if(call_method('isOffline', $othPlayer) || !call_method('isIdentified', $othPlayer)) 
			continue;
			
			$temp = call_method('getAverageLatency', $othPlayer);
			
			if($temp >= $max) 
			{
				$max_id = sprintf("%15s", call_method('getName', $othPlayer));
				$max = $temp;
			}
			if($temp <= $min) 
			{
				$min_id = sprintf("%15s", call_method('getName', $othPlayer));
				$min = $temp;
			}
			$bar_value = round($temp / 50, 0);
			$bar = sprintf("%-15s", call_method('getName', $othPlayer)) . ' latency bar: I';
			for($i=2; $i < $bar_value && $i < 10; $i++) $bar .= 'I';
			call_method('sendChatMessage', $this, $bar);
			$total_respond_time += $temp;
		}
		
		$total = count($this->players);
		$average = round($total_respond_time / $total, 0);
		$avg_bar = 'I';
		for($i=2; $i < $average && $i < 10; $i++) $avg_bar .= 'I';
		$summary = "Player with highest latency: " . $max_id;
		call_method('sendChatMessage', $this, $summary);
		$summary = "Player with lowest latency:  " . $min_id;
		call_method('sendChatMessage', $this, $summary);
	}
		
	public function _cmd_debug()
	{
		
		var_dump($this);
	}
	
	public function onTimer($timer)
	{ 
		  
	  //do something if task name matched 
	  //do something if task id matched 
	    
	  //echo "Activating timer: " . $timer->getTaskName() . " tasks \n";
		if(!call_method('executeTask',$timer)) unset($timer);
	}
  
	 //multiple simultaneous timers supported 
	public function checkTimer()
	{
		
		//custom timers
		if(count($this->timers))
		{
			foreach($this->timers as &$timer)
			{
				if($escaped_time = call_method('isExpired', $timer))
				{
					call_method('onTimer', $this, $timer);
				}
			}
	    }
    
	    //hard coded timers
	    if($this->game_state < self::IN_GAME)
	    {
	    	if(call_method('isExpired', $this->ping_timer))
	    	{
	    		call_method('pingAll', $this);
	    	}
	    	
	    	if(isset($this->coutdown_timer)
	    		&& strcmp(get_class($this->coutdown_timer), 'timer') == 0 )
	    	{
	    		if(call_method('isExpired', $this->coutdown_timer))
	    		{
	    			call_method('StartGameCountdown', $this);
	    		}
		    }
		    
		    if(isset($this->reserver_timer)
	    		&& strcmp(get_class($this->reserver_timer), 'timer') == 0 )
	    	{
	    		if(call_method('isExpired', $this->reserver_timer))
	    		{
	    			call_method('releaseSlot', $this->room, 1);
	    			call_method('sendChatMessage', $this, 'Slot 1 released.');
	    			unset($this->reserver_timer);
	    		}
		    }
		    
		    if($this->game_state == self::IN_ROOM)
		    {
		    	if(call_method('isExpired', $this->room_life_timer))
		    	{
		    		call_method('sendChatMessage', $this, 
		    		'A room only lasts 5 mins if it is not started. It is now expired. It will be closed in 5 seconds.');
		    		sleep(5);
		    		exit;
		    	}
		    }
	    }
	    else if ($this->game_state == self::IN_GAME)
	    {
	    	//if the game is in waiting for player mode
	    	if($this->is_waiting)
	    	{
	    		if(call_method('isExpired', $this->wait_timer))
	    		{
	    			foreach($this->players as $player)
	    			{
						if(call_method('isOffline', $player)) 
						continue;
						
	    				if(call_method('isWaiting', $player))
	    				{
	    					//increase the wait time by 1000ms
		    				//and
		    				//accumulate his wait time
	    					call_method('updateWaitTime', $player);
	    				}
	    			}
	    		}
		    }
	    }
	}
	
	public function onMapRequest(player $player, $packet)
	{
		
		//echo "map request...\n";
		$reslen = strlen($packet);
		for($i=9, $mapsize=''; $i < $reslen; $i++)
		{
			$mapsize .= $packet[$i];
		}
		
		$request_type = ord($packet[8]);
		
		if($request_type == 0x01)
		{
			if(strcmp($mapsize, $this->mapfilesize) != 0)
			{
				call_method('onLeave', $this, $player);
				call_method('onDisconnect', $this, $player);
				return;
			}
		}
		else if($request_type == 0x03)
		{
			if(strcmp($mapsize, $this->mapfilesize) != 0)
			{
				call_method('onLeave', $this, $player);
				call_method('onDisconnect', $this, $player);
				return;
			}
		}
		
		call_method('setMapStatus', $this->room, call_method('getPid', $player) );
		
		foreach($this->players as $othPlayer)
		{
			if(call_method('isOffline', $othPlayer) || !call_method('isIdentified',$othPlayer)) 
			continue;
			
			call_method('sendUpdatedSlots',$this,$othPlayer);
		}
	}
	
	//W3GS_REQJOIN 0x1E
	public function onJoin(player $player, $packet)
	{
		
		//check for slots
		if( ($new_pid = call_method('getNewPid', $this) ) == 0) 
		{
			call_method('onLeave', $this, $player, self::REASON_LEFT);
			call_method('onDisconnect', $this, $player);
		}
		
		$client_game_port = WORD2INT( toEndian(packet_get_conts($packet, 13, 2)) );
		$raw_client_gameport = packet_get_conts($packet, 14, 1) . packet_get_conts($packet, 13, 1);
		$raw_client_event = packet_get_conts($packet, 15, 4);
		$client_username = packet_get_string($packet, 19, self::USERNAME_MAX_LEN, $i);
		$i = $i + 7;
		$raw_client_private_ip_network_byte = packet_get_conts($packet, $i, 4);
		$private_ip = long2ip(DWORD2LONG($raw_client_private_ip_network_byte));
		socket_getpeername($player->socket, $client_ip, $client_port);
		
		call_method('setName', $player, $client_username);
		call_method('setPid', $player, $new_pid);
		call_method('setRemoteIP', $player, $client_ip);
		call_method('setRemotePort', $player, $client_port);
		call_method('setPublicNodePort', $player, $client_game_port);
		call_method('setPrivateNodeIP', $player, $private_ip);
		call_method('setClientEvent', $player, $raw_client_event);
		
		/*
		if(!$this->userlist->search(strtolower($client_username)))
		{
			call_method('onLeave', $this, $player, self::REASON_LEFT);
			call_method('onDisconnect', $this, $player);
			return;
		}
		*/
		
		if(call_method('isRequester', $this, $client_username))
		{
			//release the reserved slot for the game requester.
			unset($this->reserver_timer);
			call_method('releaseSlot', $this->room, 1);
		}
		
		//when the player sent the correct packet, mark the player as identified
		call_method('identify', $player);
		call_method('sendOnJoin', $this, $player);
		call_method('sendHostname', $this, $player);
		call_method('exchangePeersInfo', $this, $player);
		call_method('sendMapInfo', $this, $player);
								
		if(!$slot_index = call_method('insertPlayer', $this->room, call_method('getPid', $player)))
		{
			call_method('onLeave', $this, $player, self::REASON_LEFT);
			call_method('onDisconnect', $this, $player);
			return;
		}

		call_method('setSlot', $player, $slot_index);
		
		foreach($this->players as $othPlayer)
		{
			if(call_method('isOffline', $othPlayer)) 
			continue;
		
			if(call_method('isIdentified', $othPlayer))
			{
				call_method('sendUpdatedSlots', $this, $othPlayer);
			}
		}
		
		call_method('pingAll', $this);
		call_method('whereIs', $this->bot, $client_username);
		++$this->hits;
	}
	
	public function pingAll()
	{
		
		//skip sending ping when no players in the room
		if(count($this->players) == 0)
		{
				$this->last_ping_check = 0;
				return false;
		}
				
		$preoutput = getRandomSeed();
		$header 	 = call_method('buildHeader', $this, "\x01", $preoutput);
		$output    = $header . $preoutput;
		
		$this->last_ping_check = time();
		
		foreach($this->players as $player)
		{	
			if(call_method('isOffline', $player)) 
			continue;
			
			//if the player didn't respond to ping for certain periodical order
			if( call_method('pingSent', $player) > (call_method('pongRecieved', $player) + self::SYNC_TIMES) )
			{
				call_method('onDrop', $this, $player);
				call_method('onDisconnect', $this, $player);
			}
			else
			{
				call_method('updatePingTime', $player);
				
				if(call_method('isIdentified', $player))
				call_method('write', $this, call_method('getSocketId', $player), $output);
			}
		}
		
		return true;
	}
	
	public function onPong(player $player, $packet)
	{
		call_method('updatePongTime', $player);
	}
	
	public function onLeave(player $player)
	{
		
		//if we already successfully obtain valid info from the player
		
		if( call_method('isIdentified',$player) )
		{
			foreach($this->players as $othPlayer)
			{
				if(call_method('isOffline', $othPlayer)) 
				continue;
				
				if( call_method('getPid', $othPlayer) != call_method('getPid', $player)
						&& call_method('isIdentified', $othPlayer) )
				{
					$output = "\xf7\x07\x09\x00" 
									. chr(call_method('getPid', $player)) 
									. self::REASON_LEFT . "\x00\x00\x00";
					call_method('write', $this, call_method('getSocketId', $othPlayer), $output);
				}
			}
			
			if($this->game_state == self::IN_COUNTING)
			{
				if(!call_method('isNameSpoofed', $this))
				{
					$this->coutdown_leavers[] = strtolower(call_method('getName', $player));
				}
				
				call_method('updateGameRoom', $this->bot, self::GS_OPEN);
			}
			else if( $this->game_state == self::IN_GAME)
			{
				if($this->winner != 0)
				{
					call_method('setLeftReason', $player, self::SET_REASON_COMPLETED);
				}
				else
				{
					call_method('setLeftReason', $player, self::SET_REASON_LEFT);
				}
			}
		}
		
		return true;
	}
	
	public function onDrop(player $player)
	{
		
		//if we already successfully obtain valid info from the player
		if( call_method('isIdentified',$player) )
		{
			foreach($this->players as $othPlayer)
			{
				if(call_method('isOffline', $othPlayer)) 
				continue;
			
				if( call_method('getPid', $othPlayer) != call_method('getPid', $player)
						&& call_method('isIdentified', $othPlayer) )
				{
					$output = "\xf7\x07\x09\x00" 
									. chr(call_method('getPid', $player)) 
									. self::REASON_DC . "\x00\x00\x00";
									
					call_method('write', $this, call_method('getSocketId', $othPlayer), $output);
				}
			}
			
			if( $this->game_state == self::IN_GAME)
			{
				call_method('setLeftReason', $player, self::SET_REASON_DC);
			}
		}
		
		return true;
	}
	
	public function exchangePeersInfo(player $new_player)
	{
		
		foreach($this->players as $player)
		{
			if(call_method('isOffline', $player)) 
			continue;
		
			if($player != $new_player && call_method('isIdentified',$player))
			{
				//send new player info to existing player
				call_method('sendPlayerName', $this, $new_player, $player); 
				
				//send exisitng player info to new player
				call_method('sendPlayerName', $this, $player, $new_player); 
			}
		}
	}
	
	function sendMapInfo(player $player)
	{
		/*
		F6 2A 24 00 
		E5 76 E9 CD 
		CB 76 06 E0 
		*/
		static $output = '';
		
		if($output == '')
		{
			$unknown01 = "\x01\x00\x00\x00"; //Always 0x00000001?
			$mapSelector = new mapSelector();
			//only the last committed map will be read.
			$mapdata = call_method('getLastMap',$mapSelector);
			$this->mapfilesize = $mapdata['filesize'];
			$preoutput = $unknown01 . $mapdata['filename'] . "\x00" . $mapdata['filesize'] . $mapdata['signature'] . $mapdata['crc32'];
			$header = call_method('buildHeader', $this, "\x3d", $preoutput);
			$output = $header . $preoutput;
		}
		
		call_method('write', $this, call_method('getSocketId', $player), $output);
	}
	
	public function sendPlayerName(player $sender, player $reciever)
	{
		
		$player_join_create_counter = "\x00\x00\x00\x00";
		$pid = chr(call_method('getPid', $sender));
		$player_name = call_method('getName', $sender) . "\x00";
		$ipv4_tag = "\x01\x00";
		
		$sockaddr_in_ext = call_method('getPublicNodeNetworkByte', $sender); //External player IP and Port (sockaddr_in structure) (Zero for host)
		//ipv6 compability (disabled)
		$ipv6_ext_part01 = "\x00\x00\x00\x00";
		$ipv6_ext_part02 = "\x00\x00\x00\x00";
		
		$sockaddr_in_internal  = call_method('getPrivateNodeNetworkByte', $sender);
		//ipv6 compability (disabled)
		$ipv6_int_part01 = "\x00\x00\x00\x00";
		$ipv6_int_part02 = "\x00\x00\x00\x00";
		
		/*
		f7 06 31 00 
		28 00 00 00 //join game counter
		02 //pid
		74 65 73 74 32 00 //name
		01 00 
		02 00 17 e2 c0 a8 01 05 
		00 00 00 00 00 00 00 00 
		02 00 17 e2 c0 a8 01 05 
		00 00 00 00 00 00
		*/

		$preoutput = $player_join_create_counter . $pid . $player_name 
							. $ipv4_tag . $sockaddr_in_ext . $ipv6_ext_part01 . $ipv6_ext_part02 
							. $sockaddr_in_internal . $ipv6_int_part01 . $ipv6_int_part02;
		$header = call_method('buildHeader', $this, "\x06", $preoutput);
		$output = $header . $preoutput;
		
		call_method('write', $this, call_method('getSocketId', $reciever), $output);
	}
	
	public function sendHostname(player $player)
	{
		
		static $output = '';
	
		//if not cached
		if($output == '')
		{
			$player_join_create_counter = "\x01\x00\x00\x00";
			$pid = chr(1);
			$player_name = $this->hostname . "\x00";
			$ipv4_tag = "\x01\x00";
			
			$sockaddr_in_ext = "\x00\x00\x00\x00\x00\x00\x00\x00"; //External player IP and Port (sockaddr_in structure) (Zero for host)
			//ipv6 compability (disabled)
			$ipv6_ext_part01 = "\x00\x00\x00\x00";
			$ipv6_ext_part02 = "\x00\x00\x00\x00";
			
			$sockaddr_in_internal  = "\x00\x00\x00\x00\x00\x00\x00\x00";
			//ipv6 compability (disabled)
			$ipv6_int_part01 = "\x00\x00\x00\x00";
			$ipv6_int_part02 = "\x00\x00\x00\x00";
			
			$preoutput = $player_join_create_counter . $pid . $player_name 
								. $ipv4_tag . $sockaddr_in_ext . $ipv6_ext_part01 . $ipv6_ext_part02 
								. $sockaddr_in_internal . $ipv6_int_part01 . $ipv6_int_part02;
			$header = call_method('buildHeader', $this, "\x06", $preoutput);
			$output = $header . $preoutput;
		}
		
		call_method('write', $this, call_method('getSocketId', $player), $output);
	}
	
	//send room and slots status on join
	public function sendOnJoin(player $player)
	{
		
		static $joining = true;
		$raw_room_packet = call_method('getRawSlots', $this->room, $joining, call_method('getPid', $player), call_method('getPublicNetworkByte', $player));	
		$header = call_method('buildHeader', $this, self::W3GS_ONJOIN, $raw_room_packet);
		$output = $header . $raw_room_packet;
		
		call_method('write', $this, call_method('getSocketId', $player), $output);
	}
	
	//send when slot(s) get updated
	public function sendUpdatedSlots(player $player)
	{
		
		static $joining = false;
		$raw_room_packet = call_method('getRawSlots', $this->room, $joining);
		$header = call_method('buildHeader', $this, self::W3GS_ONUPDATE, $raw_room_packet);
		$output = $header . $raw_room_packet;
		
		call_method('write', $this, call_method('getSocketId', $player), $output);
	}
	
	public function getNewPid()
	{
		$totalclient = count($this->players);
		$new_pid=0;
		for($i = 2, $pid_used = false; $i < 12; $i++)
		{
			foreach($this->players as $player)
			{	
				if(call_method('isOffline', $player)) 
				continue;
				
				if(call_method('getPid', $player) == $i)
				{
					$pid_used = true;
					break;
				}
				else
				{
					$pid_used = false;
				}
			}
				
			if(!$pid_used)
			{
				$new_pid = $i;
				break;
			}
		}
		
		return $new_pid;
	}
	
	public function accept()
	{
		
		if(isset($this->server_socket))
		{
			$new_client_socket = @socket_accept($this->server_socket);
			
			//only accept player when the game room is open
			if($this->game_state == self::IN_ROOM && $new_client_socket) 
			{
				call_method('onAccept', $this, $new_client_socket);
			}
		}
	}
	
	public function select()
	{
		$reads = NULL;
		$writes = NULL;
		$writes = $reads = $this->client_sockets;
		
		$timeout = $this->fixed_min_timeslot * 1000;
		$this->before = microtime(true);
		$ret = @socket_select($reads, $writes, $tmp_e = NULL, 0, $timeout);
				
		if($ret > 0)
		{
			if(isset($reads))
		  	foreach($reads as $socket)
		  	{
		  		call_method('pull', $this, (int)$socket);
			}
		}
	  
		if($this->game_state == self::IN_GAME && $this->total_remaining_player > 0)
		{					
			foreach($this->players as $player)
			{
				if(call_method('isOffline', $player)) 
				continue;
			
				//if the player is out of sync by the defined threshold
				if($this->max_sync_counter > call_method('getSyncCounter', $player) + self::DESYNCTHRESHOLD)
				{
					//trigger the desync action
					call_method('onDesync', $this, $player);
				}
				else if($player->isWaiting()) //otherwise if the player is sync again.
				{
					//resume the game, player, and remove the player from the waiting screen 
					call_method('onResume', $this, $player);
				}
			}
			
			if(!$this->is_waiting)
			{
				//broadcast and sync all the queued actions
				call_method('broadcastActions', $this);
			}
			else
			{
				//when superhost is wating for player(s)
				//echo 'superhost is waiting for players'. "\n";
				call_method('onWait', $this);
			}
		}
	}
	
	//when the player is desync.
  public function onDesync(player $player)
  {
  	
  	if(!$player->isWaiting() && !$this->waiting_screen_toggled)
  	{
  		//echo "p:" . $player->getPid(). " n: " . $player->getName() . " onDesync...\n";
  		call_method('setWaiting', $player);
	  	$this->is_waiting = true;
	  }
  }
  
  public function removeFromWaitScreen(player $player)
  {
  	
  	if($player->isWaiting())
  	{
	  	//clear the player name from the waiting screen
	  	$output = "\xF7\x11\x09\x00" . chr(call_method('getPid', $player)) . toEndian(INT2WORD(call_method('getWaitTime', $player))) . "\x00\x00";
			foreach($this->players as $othPlayer)
			{
				if(call_method('isOffline', $othPlayer)) 
				continue;
				
				call_method('write', $this, call_method('getSocketId', $othPlayer), $output);
			}
		}
  }
    
  public function onResume(player $player)
  {
  	
  	if(call_method('isWaiting', $player))
  	{
  		call_method('removeFromWaitScreen', $this, $player);
  		call_method('resume', $player);

  		$found_waitee = false;
  		foreach($this->players as $othPlayer)
  		{
			if(call_method('isOffline', $othPlayer)) 
			continue;
			
  			if(call_method('isWaiting', $othPlayer))
  			{
  				$found_waitee = true;
  				break;
  			}
  		}
  		
  		if(!$found_waitee)
  		{
  			$this->is_waiting = false;
  			$this->waiting_screen_toggled = false;
  		}
  	}
  }
  
  //when waiting for the lagging player(s)
  public function onWait()
  { 
  	
  	if(!$this->waiting_screen_toggled)
  	{
  		//echo "onWait...\n";
	  	//generate the packet to represent the waitees and time spent on waiting
	  	$preoutput = '';
	  	$waitee_counter = 0;
	  	//F7 10 0F 00 02 02 54 1A 00 00 04 64 41 00 00
		//F7 10 0A 00 01 (02) [00 00] 00 00 
		//(02) = pid
		//[00 00] = waited time in milisecond
		foreach($this->players as $player)
		{
			if(call_method('isOffline', $player)) 
			continue;
		
			//if he is a waitee
			if(call_method('isWaiting', $player))
			{
				//echo "player " . $player->getName() . " waits for " . $time_spent_on_waitng . " ms\n";
				$preoutput .= chr(call_method('getPid', $player)) . toEndian(INT2WORD(call_method('getWaitTime', $player))) . "\x00\x00";
				$waitee_counter++;
			}
		}
			
		$preoutput = chr($waitee_counter) . $preoutput;
		$header 	 = call_method('buildHeader', $this, "\x10", $preoutput);
		$output    = $header . $preoutput;
				
		foreach($this->players as $player)
		{
			if(call_method('isOffline', $player)) 
			continue;
			
			call_method('write', $this, call_method('getSocketId', $player), $output);
		}
		
		$this->waiting_screen_toggled = true;
	}
  }
  
  public function onSpoof($fake_players, $real_usernames)
  { 
  	$summary = '';
  	foreach($real_usernames as $tmp)
  	{
  		$summary .= $tmp . ', ';
  	}
  	
  	$summary .= "name spoofed as ";
  	
  	foreach($fake_players as $player)
  	{		
  		$summary .= call_method('getName', $player) . ' ';
		
		if(call_method('isOffline', $player)) 
		continue;
		
  		call_method('onLeave', $this, $player);
  		call_method('onDisconnect', $this, $player);
  	}
  	
  	call_method('sendChatMessage', $this, $summary);
  }
  
  public function isNameSpoofed()
  {
	
  	$validated_namelist = call_method('getValidated', $this->bot);
  	$spoofer_users = array();
  	  	
	foreach($validated_namelist as $valid_name)
	{
		$found = false;
		
		foreach($this->players as $player)
		{
			if(call_method('isOffline', $player) ) 
			continue;
		
			$cur_player = strtolower(call_method('getName',$player));
			if(strcmp($valid_name, $cur_player) == 0)
			{
				$validated_players[] = $cur_player;
				$found = true;
				break;
			}
		}
	
		if(!$found)
		{
			$spoofer_users[] = $valid_name;
		}
	}
  	
  	$n = count($spoofer_users);
  	
  	if($n)
  	{
  		call_method('sendChatMessage', $this, "Found $n spoofed player name.");
  		call_method('sendChatMessage', $this, "Initialzing fake player name handling sequence...");
  		
  		foreach($this->players as $player)
  		{
			if(call_method('isOffline', $player) || !call_method('isIdentified', $player)) 
			continue;
			
  			$cur_player = strtolower(call_method('getName',$player));
  			$found = false;
  			
  			foreach($validated_players as $valid_name)
  			{
  				if(strcmp($valid_name, $cur_player) == 0)
  				{
  					$found = true;
						break;
  				}
  			}
  			
  			if(!$found)
  			{
  				$fake_players[] = $player;
  			}
  		}
  		  		
  		call_method('onSpoof',$this, $fake_players, $spoofer_users);
  		return true;
  	}
		
	call_method('sendChatMessage', $this, "Players name are secured.");
  	return false;  	
  }
	
	public function write($socket_id, $buffer)
	{
		
		$socket_id = (int) $socket_id;
		$player = &$this->players[$socket_id];
		if(!isset($this->players[$socket_id])) return;
		
		$bufsiz = call_method('fillWriteBuffer', $player, $buffer);

		if($bufsiz > call_method('getWriteBufferSize', $player))
		{
			call_method('push', $this, $socket_id);
		}
	}
	
	public function push($socket_id)
	{
		
		$socket_id = (int) $socket_id;
		$player = &$this->players[$socket_id];
		if(!isset($this->players[$socket_id])) return;
		
		$buffer = $player->write_buffer;
		$this->upload += strlen($buffer);
		$buffer_len = strlen($buffer);
		$byte_written = 0;
			
		if($buffer_len > 3)
		{
			$packet_len = ord($buffer[2]) + (ord($buffer[3]) << 8);
			
			//check for completeness of packet again, return false if not complete
			if($buffer_len >= $packet_len)
			{
				do
				{		
					for($i=0, $temp=''; $i < $packet_len; $i++)
					{
						$temp .= $buffer[$i];
					}
					
					//if invalid packet found
					if($temp[0] != "\xf7") 
					{
						//disconnect the player
						call_method('onLeave', $this, $player, self::REASON_LEFT);
						call_method('onDisconnect', $this, $player);
					}
					
					$byte_written = socket_write($player->socket, $temp, $packet_len );
					
					call_method('logBuffer', $player, $temp);
								
					if($byte_written == false)
					{
						return;
					}
					
					for($temp=''; $i < $buffer_len; $i++)
					{
						$temp .= $buffer[$i];
					}
					
					$buffer = $temp;
					$buffer_len = strlen($buffer);
					
					if($buffer_len > 1)
					$packet_len = ord($buffer[2]) + (ord($buffer[3]) << 8);
					$temp = '';
					
				}while($packet_len > 0 && $buffer_len >= $packet_len);
			} 
		}
				
		call_method('updateWriteBufferSize', $player, $byte_written);
		
		if($byte_written < $buffer_len)
		{
			$buffer = substr($buffer, $byte_written);
			$player->write_buffer = $buffer;
		}
		else
		{
			$player->write_buffer = '';
		}
	}	
	
	public function pull($socket_id)
	{
		$socket_id = (int) $socket_id;
		
		$player = $this->players[$socket_id];
		
		if($player == NULL)
		{
			echo $socket_id;
			print_r($this->players);
		}
		$buffer = socket_read($player->socket, call_method('getReadBufferSize', $player));
		
		$this->download += strlen($buffer);
		
		//if no packets returned 
		//means connection error		
		if(!$buffer || socket_last_error() == 10053) 
		{
			//disconnect the player
			call_method('onDrop', $this, $player);
			call_method('onDisconnect', $this, $player);
		}
		
		$player->read_buffer .= $buffer;
		$buffer = $player->read_buffer;
		$buffer_len = strlen($buffer);
		$packets = '';
		
		//closed connection
		if(!isset($buffer[0])) return;
		
		//packet must start with a valid header
		if($buffer[0] != "\xf7") 
		{
			//otherwise disconnect the player
			call_method('onLeave', $this, $player, self::REASON_LEFT);
			call_method('onDisconnect', $this, $player);
		}
		
		//to form a complete packet
		//minimum packet len must be at least 4 byte.
		//if the buffer doesn't reach 4 byte yet then do nothing
		if($buffer_len > 3)
		{
			$packet_len = ord($buffer[2]) + (ord($buffer[3]) << 8);
			
			//check for completeness of packet again, return false if not complete
			if($buffer_len >= $packet_len)
			{
				$event_holder = new event($socket_id);
				do
				{		
					for($i=0, $temp=''; $i < $packet_len; $i++)
					{
						$temp .= $buffer[$i];
					}
					
					//if invalid packet found
					if($temp[0] != "\xf7") 
					{
						//disconnect the player
						call_method('onLeave', $this, $player, self::REASON_LEFT);
						call_method('onDisconnect', $this, $player);
					}
					
					call_method('insert', $event_holder, $temp);
					
					for($temp=''; $i < $buffer_len; $i++)
					{
						$temp .= $buffer[$i];
					}
					
					$buffer = $temp;
					$buffer_len = strlen($buffer);
					
					if($buffer_len > 1)
					$packet_len = ord($buffer[2]) + (ord($buffer[3]) << 8);
					$temp = '';
					
				}while($packet_len > 0 && $buffer_len >= $packet_len);
				
				$player->read_buffer = $buffer;
				call_method('onRecieve', $this, $event_holder);
			} 
		}
	}
	
	public function buildHeader($header_type, $data)
	{
	  	
	  	$data_len = strlen($data) + 4;
	    if(strlen($header_type) != 1) return false;
	    if($data_len == 4)  return false;
	    $header = "\xF7" . $header_type . toEndian(INT2WORD($data_len));
	    return $header;
	}
    
	public function onDisconnect($player, $packet = NULL)
	{		
		
		if( strcmp(get_class($player), 'player') != 0 ) return;
		
		if(call_method('isIdentified', $player))
		{
			if($this->game_state < self::IN_GAME)
			{
				call_method('removePlayer', $this->room, call_method('getPid', $player) );
				
				foreach($this->players as $othPlayer)
				{
					if(call_method('isOffline', $othPlayer)) 
					continue;
					
					if($player != $othPlayer && call_method('isIdentified', $othPlayer))
					{
						call_method('sendUpdatedSlots', $this, $othPlayer);
					}
				}
				
				unset($this->coutdown_timer);
				$this->game_state = self::IN_ROOM;
				
				call_method('removeFromCheckList', $this->bot, call_method('getName', $player) );
			}
			else if($this->game_state == self::IN_GAME)
			{
				call_method('setLeftTime', $player, round($this->game_time/1000, 0) );
				$this->left_players[] = $player;
					call_method('removePlayer', $this->room, call_method('getPid', $player) );
				--$this->total_remaining_player;
			}
		}
		
		echo "socket: #" . (int)$player->socket . " has been disconnected by remote computer\n";
		
		if($this->game_state == self::IN_GAME)
		echo "Remaining player(s): " . $this->total_remaining_player . "\n";
			
		call_method('removeSocket', $this, $player);
		
		//turn the player status to offline
		call_method('setOffline', $player);
				
		if($this->game_state == self::IN_GAME && $this->total_remaining_player == 0) 
		{
			exit;
		}
		
		return true;
	}
	
	public function removeSocket($player)
	{
		$temp = array();
		foreach($this->client_sockets as $tmpsocket)
		{
			if($player->socket != $tmpsocket)
			$temp[] = $tmpsocket;
		}
		$this->client_sockets = $temp;
		socket_close($player->socket);
	}
		
	public function get_error()
	{
		
	  $error_no = socket_last_error($this->server_socket);
	  $error = 'error no: ' . $error_no . ', ' . socket_strerror($error_no);
	  socket_clear_error($this->server_socket);
	  return $error;
	}
	
	public function updateHostStatus($status)
	{
		
		$host_id = $this->host_id;
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
  
	public function __destruct()
	{
		
		global $dbserver, $dbusername, $dbpassword, $dbname;
		$this->db = new sql_db($dbserver, $dbusername, $dbpassword, $dbname, false);
		
		//clean way to handle destruction
		socket_close($this->server_socket);
		foreach($this->client_sockets as $socket)
		{
			socket_close($socket);
		}
		
		if(!$this->failed)
		$this->onSummary();
	}
	 
	public function setFatalError(UndefinedVariable $e)
	{
		$this->fatal_error = true;
		$this->fatal_error_event = $e;
	}
	 
	public function onSummary()
	{
		
		
		if($this->game_state == self::IN_START)
		{
			echo "Something went wrong. GoodGameHost halt at preparing stage.\n";
		}
			
		if($this->game_state == self::IN_GAME && $this->total_remaining_player > 0)
		{
			echo "Something went wrong. GoodGameHost halt at ingame stage.\n";
		}
		
		$game_name = $this->game_name;
		$creator = $this->creator;
		$hostname = $this->hostname;
		$host_id = $this->host_id;
		$created_time = $this->created_time;
		$game_state = $this->game_state;
		$winner = $this->winner;
		$game_started_time = $this->started_time;
		
		//stats calculation
		$endtime = time();
		$rantime = $endtime - $this->started_time;
		//upstream bandwidth
		$upload = $this->upload;
		//downstream bandwidth
		$download = $this->download;
		//bandwidth usage
		$total_bandwidth_usage = round( ($upload + $download) / 1024, 0);
		//transfer rate
		$average_transfer_rate_per_second = round($total_bandwidth_usage / $rantime, 2);
		//how many people had joined the game.
		$hits = $this->hits;
		$total_player = $this->total_player;
		$game_length = round($this->game_time/1000, 0);
		
		echo "\nSummary: \n";
		echo "Game name: $game_name\n";
		echo "Requested by: $creator\n";
		echo "Hosted by: $hostname\n";
		echo "Host id: $host_id\n";
		echo "Game started at: " . date("d M Y, h:i a", $game_started_time) . "\n";
		echo "Game ended at: ". date("d M Y, h:i a", $endtime) . "\n"; 
		echo "Total runtime: " . $rantime . " seconds\n";
		echo "Total bandwidth usage: " . $total_bandwidth_usage . " kilobyte\n";
		echo "Transfer rate: " . $average_transfer_rate_per_second . " kilobyte per seconds\n";
		echo "Hits: " . $hits . "\n";
		echo "Total player: " . $total_player . "\n";
		
		$upload = round( ($upload) / 1024, 0);
		$download = round( ($download) / 1024, 0);
					
		foreach($this->dota_stats as $stat)
		{
			foreach($this->left_players as $player)
			{
				if($stat['slot_id'] == call_method('getSlot', $player))
				{
					switch(intval($stat['cat']))
					{
						case 1:
							call_method('setHeroKills', $player, $stat['value']);
						break;
						
						case 2:
							call_method('setHeroDeaths', $player, $stat['value']);
						break;
						
						case 3:
							call_method('setCreepKills', $player, $stat['value']);
						break;
						
						case 4:
							call_method('setCreepDenies', $player, $stat['value']);
						break;
						
						default:
						//do nothing since we do not need the id cat
					}
					
					break;
				}
			}
		}
					
		//if superhost is connected to a valid database
		if($this->db->db_connect_id)
		{
			$sql = "SELECT `uid` FROM `BNET` WHERE `username` = '$creator'";

			if($result = $this->db->sql_query($sql)) 
			{
					if($row = $this->db->sql_fetchrow($result))
					{
						$game_name = $this->db->sql_string($game_name);
						$game_requester_uid = $row['uid'];
						$sql = "INSERT INTO `superhost_games` "
									." (`host_id`, `game_requester_uid`, `game_name`, `created_time`, `game_state`, "
									." `winner`,`game_started_at`, `game_length`, `total_player`"
									.", `room_hits`, `upload_band`, `download_band`, `total_band`, `transfer_rate`) "
									." VALUES('$host_id', '$game_requester_uid', $game_name, '$created_time', "
									." '$game_state', '$winner', '$game_started_time', '$game_length', "
									. " '$total_player', '$hits', '$upload', '$download', '$total_bandwidth_usage', '$average_transfer_rate_per_second')";

						if($result = $this->db->sql_query($sql)) 
						{
							$sql = "SELECT `game_id` FROM `superhost_games` WHERE `host_id` = '$host_id' AND `created_time` = '$created_time' ";
							
							if($result = $this->db->sql_query($sql))
							{
								if($row = $this->db->sql_fetchrow($result))
								{
									$game_id = $row['game_id'];
									
									if($this->game_state == self::IN_GAME)
									{
										$sql = "INSERT INTO `superhost_games_stats` "
										."(`game_id`, `slot_id`, `uid`, `team`, `left_time`, "
										." `left_reason`, `desync`, `loadtime`, `latency`, `herokills`, `herodeaths`, `creepkills`, `creepdenies`) VALUES";
										
										foreach($this->players as $player)
										{
											$slot_id = call_method('getSlot', $player);
											$team = call_method('getTeam', $player);
											$left_time = call_method('getLeftTime', $player);
											$left_reason = call_method('getLeftReason', $player);
											$desync	= call_method('getDesyncCounter', $player);
											$herokills = call_method('getHeroKills', $player);
											$herodeaths = call_method('getHeroDeaths', $player);
											$creepkills =  call_method('getCreepKills', $player);
											$creepdenies= call_method('getCreepDenies', $player);
											$playername = call_method('getName', $player);
											$loadtime = call_method('getLoadTime', $player);
											$latency = call_method('getAverageLatency', $player);
											
											$sql2 = "SELECT `uid` FROM `BNET` WHERE `username` = '$playername'"; 
											
											if($result2 = $this->db->sql_query($sql2))
											{
												if($row = $this->db->sql_fetchrow($result2))
												{
													$uid = $row['uid'];
													$sql .= " ('$game_id', '$slot_id', '$uid', '$team', '$left_time', '$left_reason', "
															 ." '$desync', '$loadtime', '$latency', '$herokills', '$herodeaths', '$creepkills', '$creepdenies'),";
												}
											}
											
											$sql3 = "INSERT INTO `superhost_games_packet_log` "
														."(`game_id`, `slot_index`, `packet`) VALUES";
											
											
											while($buffer = $player->getBufferLog())
											{
												$packet = packet_dump_hex($buffer);
												
												$sql3 .= "('$game_id', '$slot_id', '$packet'),";
											}
											
											$last_char = strlen($sql3)-1;
											if($sql3[$last_char] == ',') $sql3[$last_char] = '';
											
											$result3 = $this->db->sql_query($sql3);
										}
										
										$last_char = strlen($sql)-1;
										//remove the last ','
										if($sql[$last_char] == ',') $sql[$last_char] = '';
										
										$result = $this->db->sql_query($sql);
									}
								}
							}
							
							if($this->fatal_error)
							{
								$back_trace = $this->fatal_error_event->getMessage();
								$back_trace .= "\n\n" . $this->fatal_error_event->getTraceAsString();
								$back_trace = $this->db->sql_string($back_trace);
								$sql3 = "INSERT INTO `superhost_games_fatal_log` "
										."(`game_id`, `log`) VALUES";
								$sql3 .= "('$game_id', $back_trace)";
								
								$result3 = $this->db->sql_query($sql3);
							}
						}
					}
			}

			call_method('updateHostStatus', $this, self::HOST_STATUS_FREE);
			$this->db->sql_close();	
		}
	}
}

?>
