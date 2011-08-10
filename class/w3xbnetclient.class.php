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

if(defined('w3xbnetclient_class')) return;
define('w3xbnetclient_class', 1);

class w3xBnetClient
{
  const USERNAME_MIN_LEN = 3;
  const USERNAME_MAX_LEN = 15;
  const PROTOCOL_ID = "\x01";
  const SID_AUTH_INFO = "\x50";
  const SID_CHATEVENT = "\x0F";
  const SID_PING = "\x25";
  const SID_AUTH_CHECK = "\x51";
  const SID_AUTH_ACCOUNTLOGON = "\x53";
  const SID_AUTH_ACCOUNTLOGONPROOF = "\x54";
  const SID_NETGAMEPORT = "\x45";
  const SID_ENTERCHAT = "\x0A";
  const SID_GETICONDATA = "\x2D";
  const SID_JOINCHANNEL = "\x0C";
  const SID_CHATCOMMAND = "\x0E";
  
  const CHAT_USER_FLAG = 12;
  const CHAT_SHOWUSER = "\x01";
  const CHAT_JOIN = "\x02";
  const CHAT_LEAVE = "\x03";
  const CHAT_WHISPER = "\x04";
  const CHAT_TALK = "\x05";
  const CHAT_BROADCAST = "\x06";
  const CHAT_CHANNEL = "\x07";
  const CHAT_USERFLAGS = "\x09";
  const CHAT_WHISPERSENT = "\x0A";
  const CHAT_CHANNELFULL = "\x0D";
  const CHAT_CHANNELDOESNOTEXIST = "\x0E";
  const CHAT_CHANNELRESTRICTED = "\x0F";
  const CHAT_INFO = "\x12";
  const CHAT_ERROR = "\x13";
  const CHAT_EMOTE = "\x17";
  
  const GS_CLOSED = 0;
	const GS_OPEN = 1;
	const GS_FULL = 2;
	const GS_STARTED = 3;
    
  //declare the socket as public variable
  //so that it can be easily interact with other objects
  public $socket;
  private $remote_address;
  private $remote_port;
  private $local_addr;
  private $local_port;
  private $username;
  private $gameport;
  private $buffer_size = 256;
  private $write_buffer;
  private $write_len = 0;
  private $read_buffer;
  private $default_channel = 'Bluelab';
  private $timeslot = 100;
  private $timers = array();
  private $connected = false;
  private $start_time = 0;
  private $gameRoomStatus = '';
  private $mapSelector;
  private $game_room_status = '';
  private $game_created = false;
  private $announce_timer;
  private $created_since;
  
  public function __construct($remote_address, $remote_port=6112, $gameport=6112, $username)
  {  	
    $this->remote_address = gethostbyname($remote_address);
    $this->remote_port = $remote_port;
    
    if($this->start_time == 0) $this->start_time = time();
    
    while(!$this->connected)
    {
	    try
	    {
		    $this->buildSocket();
		    $this->connected = true;
		  } catch (clientException $e) 
		  {
		  	//echo "Error in connecting to Blueserver...retrying...\n"; 
		  	sleep(2); //retry after 2 sec
		  }
		}
        
    $username_length = strlen($username);
    
    if( $username_length < self::USERNAME_MIN_LEN || $username_length > self::USERNAME_MAX_LEN )
    {
    	_die("Invalid username", __FILE__, __LINE__, __FUNCTION__ );
    }
    
    $this->announce_timer = new timer('ann', 60000);
    $this->gameport = intval($gameport) ? intval($gameport) : 0;
    $this->username = $username;
    $this->sendAuthInfo();
  }
  
  private function buildSocket()
  {
  	$timeout['sec'] = 0;
    $timeout['usec'] = 0;
    
  	if(isset($this->socket)) 
    {
      socket_close($this->socket);
      unset($this->socket);
    }
    
    if( ($this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) === false )
    {
      throw new clientException("Could not create socket: ".$this->get_error());
    }

    if (!socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1)) 
    {
      throw new clientException("Could not set SO_REUSEADDR: ".$this->get_error());
    }
    
    if(!socket_connect($this->socket, $this->remote_address, $this->remote_port))
    {
      throw new clientException("Could not connect to {$this->remote_address}:{$this->remote_port}".$this->get_error());
    }
    
    if (!socket_getsockname($this->socket, $this->local_addr, $this->local_port)) 
    {
      throw new clientException("Could not retrieve local address & port: ".$this->get_error());
    }
    
    if (!socket_set_nonblock($this->socket)) 
    {
      throw new clientException("Could not set socket to non-blocking: ".$this->get_error());
    }
    
    if (!socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, $timeout )) 
    {
      throw new clientException("Could net set recieve timeout: ".$this->get_error());
    }
    
    if (!socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, $timeout )) 
    {
      throw new clientException("Could net set send timeout: ".$this->get_error());
    }
    
    $this->mapSelector = new mapSelector();
  }
  
  public function publishGame($map_index, $gamename, $public)
  {
  	$this->game_created = true;
  	
  	$public = ($public == 'public') ? true : false;
		/*
		FF 1C 82 00 
		10 00 00 00 (State)
		00 00 00 00 (Time Count since creation)
		01 20       (Game Type)
		49 00       (Parameter)
		FF 03 00 00 (Unknown)
		00 00 00 00 (BOOLEAN)(Ladder)
		*/
		if( strlen($gamename) > 31 ) return false;
		
		if($public)  $state = "\x10\x00\x00\x00";
		if(!$public) $state = "\x11\x00\x00\x00";
		$time  				= "\x00\x00\x00\x00";
		$game_type  	= "\x01\x20\x19\x00";
		$unknown1		  = "\xFF\x03\x00\x00";
		$ladder 		  = "\x00\x00\x00\x00";
		$gamename			= $gamename . "\x00";
		$unknown2			= "\x00\x62\x31\x30\x30\x30\x30\x30\x30\x30";
		$gs_gameflags    = "\x02\x48\x06\x40";
		//leave it as default
		$gs_custom_game  = "\x00" . "\x7C" . "\x00" . "\x7C" . "\x00";		

		//fail safe mekanism
		$total_map = $this->mapSelector->getTotal();
		//if the provided index is out of range
		//simpily get the last map.
		if($map_index < 0 || $map_index >= $total_map)
		{
			$map_index = $total_map - 1;
		}
		$mapdata = $this->mapSelector->getMap($map_index);
		$gs_mapcrc32    = $mapdata['crc32'];
		$gs_mappath			= $mapdata['filename'] . "\x00";
			
		$gs_hostname		  = $this->username . "\x00" . "\x00";
		$raw_gamestring = $gs_gameflags . $gs_custom_game . $gs_mapcrc32 . $gs_mappath . $gs_hostname;
		$gamestring   = encodeGameString($raw_gamestring);
		
		//packet dump
		$preoutput    = $state . $time . $game_type . $unknown1 . $ladder . $gamename . $unknown2 . $gamestring . "\x00"; 
		$header  = $this->buildHeader("\x1C", $preoutput);
		$output =  $header . $preoutput;
		
		$this->game_room_status = $output;
		
		$this->created_since = time();
		
		return $this->push($output);
  }
  
  public function updateGameRoom($status)
  {
  	if($this->game_room_status == '') return;
  	
  	$duration = time() - $this->created_since;
  	$duration_bytes = toEndian(LONG2DWORD($duration));
  	$this->game_room_status[8] = $duration_bytes[0];
  	$this->game_room_status[9] = $duration_bytes[1];
  	$this->game_room_status[10] = $duration_bytes[2];
  	$this->game_room_status[11] = $duration_bytes[3];
  	
  	switch($status)
  	{
  		case self::GS_CLOSED:
    		$this->game_room_status[4] = "\x12";
    		$this->game_room_status = '';
    		$this->push( "\xFF\x02\x04\x00");
    		$this->push( "\xFF\x44\x09\x00\x07\x04\x00\x00\x00");
    		return $this->joinChannel($this->default_channel);
  		break;
  		
  		case self::GS_OPEN:
  			$this->game_room_status[4] = "\x10";
  		break;
  		
  		case self::GS_FULL:
  			$this->game_room_status[4] = "\x12";
  		break;
  		
  		case self::GS_STARTED:
  			$this->game_room_status[4] = "\x12";
  			$this->push("\xFF\x02\x04\x00");
  		break;
  	}
  	  	
  	$this->push($this->game_room_status);
  }
  
  public function commit()
  {
  	return $this->mapSelector->commit();
  }
  
  public function insertMap($version, $filename, $filesize, $signature, $crc32)
  {
  	return $this->mapSelector->insert($version, $filename, $filesize, $signature, $crc32);
  }
  
  public function getMapsInfo()
  {
  	$total = $this->mapSelector->getTotal();
  	$last_offset = $total - 1;
  	
  	for($i = 0; $i < $total; $i++)
  	{
  		$temp = $this->mapSelector->getMap($i);
  		
		//if it is not the last map; by default  superhost uses last map
  		if($i < $last_offset)
  		{
  			$mapsinfo[] = '[ ' . $i . ' ]' . '   ' . $temp['filename'];
  		}
  		else
  		{
  			$mapsinfo[] = '[ ' . $i . ' ]' . '   ' . $temp['filename'] . '      (selected by default)';
  		}
  	}
  	
  	return $mapsinfo;
  }
    
  //when the bot recieved a message
  private function processChat($event)
  {
    switch($event[4])
    {
      case self::CHAT_TALK:
        $this->onTalk($event);
      break;

      case self::CHAT_WHISPER:
        $this->onWhisper($event);
      break;
      
      case self::CHAT_INFO:
      	$this->onInform($event);
      break;
    }
  }
  
  //when the bot get disconnected
  public function onDisconnect()
  {
    $this-> __construct($this->remote_address, $this->remote_port, $this->gameport, $this->username);
  }
  
  public function onTimer($task_name)
  {   
    //do something if task name matched 
    //do something if task id matched 
    switch($task_name)
    {
    	case 'timer':
    		$this->sendText("timer is running....");
    		$this->sendText("/timer 5");
    	break;
    }
  }

  //when the bot recieved event(s)
  public function onRecieve($packets)
  {
    while(strlen($packets)!=0)
    {
      //get the following packet length
      $packet_len = ord($packets[2]) + (ord($packets[3]) << 8);
      //fetch the following event packet until the specified offset
      for($i=0, $event = ''; $i < $packet_len; $i++) $event .= $packets[$i];
    
      //fetch remaing events
      for($temp = '', $data_len = strlen($packets); $i < $data_len; $i++) $temp .= $packets[$i];
      //assign it to data
      $packets = $temp;
      
      //get event type
      $event_type = $event[1];
      
      switch($event_type)
      {
      	//ping pong \(^.^)/
        case self::SID_PING:
          $this->push($event);
        break;
        
        //chat event
        case self::SID_CHATEVENT:
          $this->processChat($event);
        break;
                
        case self::SID_AUTH_INFO:
          $this->sendAuthCheck();
        break;
        
        case self::SID_AUTH_CHECK:
          $this->sendAuthAccountLogon();
        break;
        
        case self::SID_AUTH_ACCOUNTLOGON:
          $this->sendAuthLogonProof();
        break;
        
        case self::SID_AUTH_ACCOUNTLOGONPROOF:
          $this->sendNetGamePort();
          $this->sendEnterChat();
        break;
        
        case self::SID_ENTERCHAT:
          $this->sendGetIconData();
          $this->joinChannel($this->default_channel);
        break;
        
        default:
        //ignore other events
      }
    }
  }
    
  public function onTalk($event)
  {
    $target_player = $this->getChatEventPlayer($event);
    $text = $this->getChatEventTalk($event);
    //echo $target_player . ' says: ' . $text . "\n";
    
    //if player issused a command, trigger the onCommand event.
    if($text[0] == "-") $this->onCommand($target_player, $text);
  }
  
  public function onCommand($event) {}
  
  public function onWhisper($event)
  {
    $target_player = $this->getChatEventPlayer($event);
    $text = $this->getChatEventTalk($event);
    //echo $target_player . ' says: ' . $text . "\n";
    
    //if player issused a command, trigger the onCommand event.
    //if($text[0] == "-") $this->onCommand($target_player, $text);
  }
  
  public function onInform($event)
  {
  	$info = $this->getChatEventInfo($event);
  }
  
  function getChatEventInfo($respond)
  {
  	$data_len = strlen($respond);
   	for($i=29, $info = ''; $i < $data_len; $i++)
		{
			if($respond[$i] == "\x00") break;
			$info .= $respond[$i];
		}
		return $info;
	}
  
  private function getChatEventPlayer($respond)
  {
    $data_len = strlen($respond);
    for($i=28, $new_player=''; $i < $data_len; $i++)
    {
      if($respond[$i] == "\x00") break;
      $new_player .= $respond[$i];
    }
    return $new_player;
  }
  
  private function getChatEventTalk($respond)
  {
    $data_len = strlen($respond);
    for($i = 28; $i < $data_len && $respond[$i] != "\x00"; $i++);
    for($i = $i+1, $text=''; $i < $data_len && $respond[$i] != "\x00"; $i++) $text .= $respond[$i];
    return $text;
  }
   
  //send text
  public function sendText($text)
  {
    if(strlen($text) > 251) return false;
    $header  = $this->buildHeader( self::SID_CHATCOMMAND, $text . "\x00" );
    $data    = $header . $text . "\x00";
    $this->push($data);
    return true;
  }
  
  //join channel
  public function joinChannel($channelName)
  {
    $preoutput = "\x02\x00\x00\x00" . $channelName . "\x00";
    $header  = $this->buildHeader(self::SID_JOINCHANNEL,$preoutput);
    $output= $header . $preoutput;
    $this->push($output);
  }
  
  //multiple simultaneous timers supported 
  public function checkTimer()
  {
    if(count($this->timers))
    {
      foreach($this->timers as &$timer)
      {
        if($escaped_time = $timer->isExpired())
        {
          $this->onTimer($timer->getTaskName());
        }
      }
    }
    
    if($this->game_created)
    {
    	if($this->announce_timer->isExpired())
    	{
    		$this->updateGameRoom(self::GS_OPEN);	
    	}
  	}
  }
    
  protected function sendGetIconData()
  {
    $this->push("\xFF\x2D\x04\x00");
  }
  
  protected function sendEnterChat()
  {
    $this->push("\xFF\x0A\x06\x00\x00\x00");
  }
  
  protected function sendNetGamePort()
  {
    $preoutput = toEndian(INT2WORD($this->gameport));
    $header  = $this->buildHeader(self::SID_NETGAMEPORT, $preoutput); 
    $output = $header . $preoutput;
    $this->push($output);
  }
  
  protected function sendAuthLogonProof()
  {
	//change your passhash accordingly
    $passhash = "\xa1\x17\xf3\x18\xcd\xf6\xe9\xf6\x6f\x7e\x38\xb5\xba\x8f\xf9\x5f\x8a\x49\xc1\x31";
    $header = $this->buildHeader(self::SID_AUTH_ACCOUNTLOGONPROOF, $passhash);
    $output = $header . $passhash;
    $this->push($output);
  }
  
  protected function sendAuthAccountLogon()
  {    
    $logonchallenge = "\x45\x07\xed\x8a\x10\xf3\x73\xe9\xfc\xd5\xb5\xc6\x7d\xd5\xc8\xab\xfb\x08\x79\x64\x2a\xca\x16\x20\x65\x49\x16\x4f\xdc\xeb\x78\x3e";
    $preoutput = $logonchallenge . $this->username . "\x00";//32byte Client Key ('A') + username
    $header = $this->buildHeader(self::SID_AUTH_ACCOUNTLOGON,$preoutput); 
    $output = $header . $preoutput;
    $this->push($output);
  }
  
  protected function sendAuthCheck()
  {
    //SID_AUTH_CHECK 0x51
    $preoutput = "\x00\x00\x00\x00" //Client Token
                ."\x77\x00\x15\x01" //EXE Version
                ."\x02\xEF\xCD\xAB" //EXE Hash
                ."\x02\x00\x00\x00" //Number of keys in this packet
                ."\x00\x00\x00\x00" //Using Spawn (32-bit)? true for yes false for no
                ."\x00\x00\x00\x00" //empty cdkey set 1
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00" 
                ."\x00\x00\x00\x00" //empty cdkey set 2
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."\x00\x00\x00\x00"
                ."war3.exe 12/28/06 20:35:20 1572307\x00" //Exe Infomation
                ."BlueGameBot\x00";                       //CDkey owner
        $header = $this->buildHeader(self::SID_AUTH_CHECK,$preoutput); 
        $output = $header . $preoutput;
        $this->push($output);
  }
  
  protected function sendAuthInfo()
  {
  	//prepare handshake data
    $preoutput = "\x00\x00\x00\x00"   //protocol id
              ."\x36\x38\x58\x49"   //platform id "68XI"
              ."\x50\x58\x33\x57"   //product id  "PX3W"
              ."\x15\x00\x00\x00"   //version byte
              ."\x53\x55\x6E\x65"   //product language
              ."\x00\x00\x00\x00"   //Local IP for NAT compatibility
              ."\x00\x00\x00\x00"   //Time zone bias
              ."\x00\x00\x00\x00"   //Locale ID
              ."\x00\x00\x00\x00"   //Language ID
              ."USA\x00"            //Country abreviation, String
              ."United States\x00"; //Country, String
                  
    $header  = $this->buildHeader(self::SID_AUTH_INFO,$preoutput);
    $output = $header . $preoutput;
    $this->push(self::PROTOCOL_ID);
    $this->push($output);
  }
  
  //select which socket(s) channel is accessible
  public function select()
  {
  	//declare static variable to save cpu cycle
  	$timeout = $this->timeslot * 1000;
  	
    $r = array($this->socket);
    $ret = socket_select($r, $w = NULL, $e = NULL, 0, $timeout);
    if($ret > 0)
    {
      if(count($r)) $this->pull(); 
    }
  }
    
  //push data to socket
  private function push($data='')
  { 
    $this->write_buffer .= $data;
    $buffer_len = strlen($this->write_buffer);    
    $byte_written = socket_write($this->socket, $this->write_buffer, $buffer_len);
    
    if ($byte_written === false) 
    {
      $this->connected = false;
      $this->onDisconnect();
    }
    
    if($byte_written < $buffer_len)
    {
      $this->write_buffer = substr($this->write_buffer, $byte_written);
      $this->write_len = $buffer_len - $byte_written;
    }
    else
    {
      $this->write_buffer = '';
      $this->write_len = 0;
    }
  }
  
  //pull data from socket
  private function pull()
  {
    $buffer = socket_read($this->socket, $this->buffer_size, PHP_BINARY_READ);
				
    //no packet means connection closed / failed / disconnected 
    if(!$buffer) 
    {
      $this->connected = false;
      $this->onDisconnect();
    }
    
    $buffer = $this->read_buffer .= $buffer ? $buffer : '';
    $buffer_len = strlen($buffer);
    $packets = '';
          
    //packet must start with a valid header
    if($buffer_len > 0 && $buffer[0] != "\xff" && $buffer[0] != '') 
    { 
      _die("Invalid packet header. Suppose to be 0xff", __FILE__, __LINE__, __FUNCTION__ );
    }
    
    //to form a complete packet
    //minimum packet len must be at least 4 byte.
    //if the buffer doesn't reach 4 byte yet return false
    if($buffer_len > 3)
    {
      $packet_len = ord($buffer[2]) + (ord($buffer[3]) << 8);
      
      //check for completeness of packet again, return false if not complete
      if($buffer_len >= $packet_len)
      {
        do
        {
          //fetch buffer unitl specified offset
          for($i=0, $temp=''; $i < $packet_len; $i++)
          {
            $temp .= $buffer[$i];
          }
          
          //not suppose to happen
          if($temp[0] != "\xff") 
          {
            echo "Discarding unexpected packets...\n";
            $temp = '';
            _die("Invalid packet header. Suppose to be 0xff", __FILE__, __LINE__, __FUNCTION__ );
          }
          
          //stack the packets
          $packets .= $temp;
          
          //get remaining buffer
          for($temp=''; $i < $buffer_len; $i++)
          {
            $temp .= $buffer[$i];
          }
          
          //assign remaining buffer to buffer variable
          $buffer = $temp;
          //recalculate buffer lenght
          $buffer_len = strlen($buffer);
          //reset packet lenght
          $packet_len = 0;
          //clear temporarily variable
          $temp = '';
          
          //buffer len > 3    
          if($buffer_len > 3)
          //calculate next packet ending offset
          $packet_len = ord($buffer[2]) + (ord($buffer[3]) << 8);
          
        //if next packet lenght is more than 0 and still have remaining buffer is complete to reach next process, repeats loop
        }while($packet_len > 0 && $buffer_len >= $packet_len);
        
        //store incomplete packet into buffer
        $this->read_buffer = $buffer;
        //handle the event
        $this->onRecieve($packets);
      }
    }
  }
  
  //build packet header
  private function buildHeader($header_type, $data)
  {
  	$data_len = strlen($data) + 4;
    if(strlen($header_type) != 1) return false;
    if($data_len == 4)  return false;
    $header = "\xFF" . $header_type . toEndian(INT2WORD($data_len));
    return $header;
  }
  
  //get socket error, dont rely on the error message it is not always as it said.
  private function get_error()
  {
    $error_no = socket_last_error($this->socket);
    $error = 'error no: ' . $error_no . ', ' . socket_strerror($error_no);
    socket_clear_error($this->socket);
    return $error;
  }
}
?>
