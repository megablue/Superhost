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

if(!defined('functions_class'))
{
	define('functions_class', 1);
	define('E_UNDEFINED_VARIABLE', 8);
	define('E_SOCKET_ACCEPT', 2048);

	function encodeGameString($data)
	{
		$data_len = strlen($data);
		$gamestring  ='';
		
		for($i=0,$temp =''; $i < $data_len; $i++)
		{
		  $temp .= $data[$i];
		  $temp_len = strlen($temp);
		  
		  if($temp_len == 7 || ($i+1) == $data_len)
		  {     
		    $key = 0x01;
		    $encoded = "\x00";
		    for($y=0; $y < $temp_len; $y++)
		    {
		      //if it is odd value
		      if(  (ord($temp[$y]) & 1)  )
		      {
		        $key = $key | (2 << $y);
		        $encoded[$y] = chr((ord($temp[$y])));
		      }
		      else //if it is even value
		      {
		        $encoded[$y] = chr((ord($temp[$y]) | 0x01));
		      }
		    }
		    $gamestring .= chr($key) . $encoded;
		    $temp = '';
		  }
		}
		return $gamestring;
	}
	
	function decodeGameString($encodedstring)
	{
		$decoded_gamestring = '';
		$encoded = '';
		$stringlen = strlen($encodedstring);
		for($z=0; $z < $stringlen; $z++)
		{
			$encoded .= $encodedstring[$z];
			if( strlen($encoded)==8 || ($z + 1) == $stringlen )
			{
			    $temp_string = '';
			    $key = ord($encoded[0]);
			    $datalen = strlen($encoded);
			    
			    if($datalen > 8) return false;
			    
			    //decode key into keys
			    for($i=7, $y= (2 << ($i-1)); $i >= 0; $i--, $y >>= 1)
			    {
			      $temp = ($key & $y);
			      $temp = ($temp  >> $i);
			      $keys[$i] = $temp;
			    }
			    
			    if($keys[0] != 0x01) return false;
			    
			    for($i=1; $i < $datalen; $i++)
			    {
			      $temp = ord($encoded[$i]);
			      $temp = ($temp >> 1);
			      $temp = ($temp << 1);
			      $temp = ($temp | $keys[$i]);
			      $temp_string .= chr($temp);
			      
			    }
			    $decoded_gamestring .= $temp_string;
			    $encoded = '';
			}
		}
		
		return $decoded_gamestring;
	}
	
	function INT2WORD($value)
	{
		$value = intval($value);
		$part1 = ($value     )  & 0xFF;
		$part2 = ($value >> 8)  & 0xFF;
		return chr($part2).chr($part1);
	}
	
	function WORD2INT($value)
	{
		$a = isset($value[1]) ? ord($value[1]) : 0;
		$b = isset($value[0]) ? ord($value[0]) << 8 : 0;
		
		return  $a+$b;
	}
	
	function LONG2DWORD($value)
	{
		$value = intval($value);
		$part1 = ($value     )  & 0xFF;
		$part2 = ($value >> 8)  & 0xFF;
		$part3 = ($value >> 16) & 0x00FF;
		$part4 = ($value >> 24) & 0x00FF;
		return chr($part4).chr($part3).chr($part2).chr($part1);
	}
		
	function DWORD2LONG($value)
	{
		$a = isset($value[3]) ? ord($value[3]) : 0;
		$b = isset($value[2]) ? ord($value[2]) << 8 : 0;
		$c = isset($value[1]) ? ord($value[1]) << 16 : 0;
		$d = isset($value[0]) ? ord($value[0]) << 24 : 0;
		
		return  $a+$b+$c+$d;
	}
	
	//reserve the data stream 
	//to convert from little endian to big endian or vise versa
	function toEndian($data)
	{
		$max = strlen($data) -1;
		$temp = '';
		for($i=$max; $i > -1 ; $i--)
		{
			$temp .= $data[$i];
		}
		
		return $temp;
	}
	
	function getNetworkByte($long_ip, $port)
		{
			$raw_ip = LONG2DWORD($long_ip);
			$raw_port = INT2WORD($port);
			
			return "\x02\x00" . $raw_port . $raw_ip;
		}

		function getRandomSeed()
		{
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
			$part2 		= ($pingseed_02 >> 8 ) & 0xFF;
			$part3 	 	= ($pingseed_01 >> 8 ) & 0xFF;
			$part4 	 		= ($pingseed_02      ) & 0xFF;

			$output 			= chr($part1).chr($part2).chr($part3).chr($part4);
			
			return $output;
		}  
	
	function _die($text, $file, $line, $function)
	{
		$time = date("d M Y, h:i a", time());
		$fp = fopen(WORKING_PATH . 'data/fatal.log', 'a');
		fwrite($fp, $time . " " . $text . " at file: $file  line: $line function: $function \n");
		fclose($fp);
		exit(0);
	}
		
	function parseBotCommands($text, $prefix="-")
	{
		if($text[0] != $prefix) return false;
		
		$cmd_len = strlen($text);
		$open_quote = false;
		$close_quote = false;
		$x = 0;
		$found_reserve = false;
		
		for($i=1; $i < $cmd_len; $i++)
		{
			if($text[$i] == "'")
			{
			    for($i = $i+1; $i < $cmd_len && $text[$i] != "'"; $i++)
			    {
			      if(is_allowedChar($text[$i]))
			      $message .= $text[$i];
			    }
			}
			else if($text[$i] == '"')
			{
			    for($i = $i+1; $i < $cmd_len && $text[$i] != '"'; $i++)
			    {
			      if(is_allowedChar($text[$i]))
			      $message .= $text[$i];
			    }
			}
			else 
			{
			    for($message = ''; $i < $cmd_len && $text[$i] != ' '; $i++)
			    {
			      if(is_allowedChar($text[$i]))
			      $message .= strtolower($text[$i]);
			    }
			}
		  
			if($message)
			{
				$commands[] = $message;
				$message = '';
			}
		}
		
		return $commands;
	}
	
	function is_allowedChar($input)
	{
		$key = ord($input);
		/*
		32
	 		48 - 57
		65 - 90
		97 - 122
		*/
		if($key != 32  
				&& $key != 46
				&& !($key >= 40 && $key <= 41)
		   && !($key >= 48 && $key <= 57)
		   && !($key >= 65 && $key <= 90)
		   && !($key >= 97 && $key <= 122)
		   && !($key == 45)
		   && !($key == 95)
		   && !($key == 91)
		   && !($key == 93)
		   && !($key == 64)
		   && !($key == 45)
		  ) return false;
		  
		return true;
	}
	
	function arguments($argv) 
	{
		$_ARG = array();
		foreach ( $argv as $arg) {
		    if (ereg('--[a-zA-Z0-9]*=.*',$arg)) {
		        $str = split("=",$arg); $arg = '';
		        $key = ereg_replace("--",'',$str[0]);
		        for ( $i = 1; $i < count($str); $i++ ) {
		            $arg .= $str[$i];
		        }
		        $_ARG[$key] = $arg;
		    } elseif(ereg('-[a-zA-Z0-9]',$arg)) {
		        $arg = ereg_replace("-",'',$arg);
		        $_ARG[$arg] = 'true';
		    }
	 
		}
			return $_ARG;
	}
		
	function call_method($method, $obj)
	{
		$undefined_object = false;
		$non_object = false;
		$nonexistent_method = false;
		
		$undefined_object = !isset($obj) ? true : false;
		$non_object = !is_object($obj) ? true : false;
		$nonexistent_method = !method_exists($obj, $method) ? true : false; 
		
		if($non_object)
		{
			if($undefined_object)
			{
				throw new UndefinedVariable
				('Call to a variable with a non existence method ( ' . $method .').');	
			}
			
			throw new UndefinedVariable
			('Unsupported call to an non object with a method ('. $method.').');
		}
		else if($nonexistent_method)
		{
			throw new UndefinedVariable
			('Call to an object with a non existence method (' . $method .').');	
		}
		
		$args = func_get_args();
		
		//remove the proxy function parameters
		//it should be much faster without using loop
		array_shift($args);
		array_shift($args);
		
		//if the caller passed parameters to the target method
		return count($args) 
		? call_user_method_array($method, $obj, $args)
		: call_user_method($method, $obj);
		
	}
			
	function var_name(&$var, $scope=false)
	{
		if(is_object($var))
		{
			return get_class($var);
		}
		
		if($scope) $vals = $scope;
		else      $vals = $GLOBALS;
		$old = $var;
		$var = $new = 'xc#!t&x'.rand().'zjk?@zlo';
		$vname = FALSE;
		foreach($vals as $key => $val) {
		  if($val === $new)
		  {
		  	$vname = $key;
		  	break;
		  }
		}
		$var = $old;
		return $vname;
	}
  
	function myErrorHandler($errno, $errstr, $errfile, $errline)
	{
		switch ($errno) 
		{	
			//suspress error
			case E_SOCKET_ACCEPT:
				return true;
			
			case E_UNDEFINED_VARIABLE:
				return false;
		
			default:
					//Execute PHP internal error handler
			    return false;
		}
	
		// Don't execute PHP internal error handler
		return true;
	}
}
?>
