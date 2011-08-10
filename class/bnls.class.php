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

class bnls{
    var $fp;
    var $errno;
    var $errstr;
    var $delayed_messages = array();
    var $username;
    var $password;

    function connect($host,$username,$password,$port=9367,$timeout=10) {
        if (isset($this->fp)) return -3;

        $this->fp = fsockopen($host,$port,$this->errno,$this->errstr,$timeout);
        if (!$this->fp) return -2;
        
        $this->username = $username;
        $this->password = $password;
        
        /*
        echo "Request Ver Byte: \r\n";
        $this->print_respond($this->getRequestVerByte("\x08"));
        
        echo "Choosen LS Revision: \r\n";
        var_dump($this->getChoosenLSRevision("\x02"));
        
        echo "Logon Challenge:\r\n";
        $this->print_respond($this->getLogonChallenge());
        
        echo "Hashdata:\r\n";
        $this->print_respond($this->getHashdata("\x02","\x00\x00\x00\x00",$server_token));
        */
        return true;
    }
    
    function getRequestVerByte($product_id="\x08")
    {
    	fwrite($this->fp,"\x07\x00\x10".$product_id."\x00\x00\x00");
    	$content = $this->getNextLine();
    	$result = (ord($content[3])!=0) ? true:false;
    	if(!$result) return false;
    	
    	for($i=7; $i < strlen($content); $i++) $output .= $content[$i];
      return $output;
    }
    
    function getChoosenLSRevision($flag="\x02")
    {
    	fwrite($this->fp,"\x07\x00\x0D".$flag."\x00\x00\x00");
    	$content = $this->getNextLine();
    	$result = ord($content[3]);
    	return ($result==1) ? true:false;
    }
    
    function getLogonChallenge()
    {
    	  $accname_accpass = $this->username."\x00".$this->password."\x00";
        $len = chr( strlen($accname_accpass) + 3 );
        $data = $len."\x00\x02".$accname_accpass;
        fwrite($this->fp,$data);
        $content = $this->getNextLine();
        
        for($i=3, $output=''; $i < strlen($content); $i++) $output .= $content[$i];
        return $output;
    }
    
    function getHashdata($flag = "\x02", $client_key='',$server_key='',$cookie='')
    {
    	  //HASHDATA_FLAG_UNUSED (0x01)
        //HASHDATA_FLAG_DOUBLEHASH (0x02) client and server key 
        //HASHDATA_FLAG_COOKIE (0x04) cookie supported
        
        $passlen = strlen($this->password);
        $extra_len = strlen($client_key.$server_key.$cookie);
        $len     = chr( $passlen + 11 + $extra_len );
        $passlen = chr( $passlen );
        $data = $len."\x00\x0b".$passlen."\x00\x00\x00".$flag."\x00\x00\x00".$this->password.$client_key.$server_key;
        fwrite($this->fp,$data);
        $content = $this->getNextLine();
        
        for($i=3, $output=''; $i < strlen($content); $i++) $output .= $content[$i];
        return $output;
    }
    
    function print_respond($data)
    {
    	$data_len = strlen($data);
    	for($i=0; $i < $data_len; $i++)
    	{
    		echo sprintf('0x%02x ', ord($data[$i]));
    	}
    	echo "\r\n";
    }
 
    function getNextLine() {
    	$i=0;
    	$content = '';
    	for(;;)
    	{
    		$buffer = fread($this->fp,1);
    		if($buffer === "") return false;
    		$content .= $buffer;
    		if($i == 0)
    		{
    			$data_len = ord($buffer);
    		}
    		if($i == 1)
    		{
    			$data_len += (ord($buffer) * 256);
    		}
    		else if($i > 2)
    		{
    			if($i+1 >= $data_len) break;
    		}
        ++$i;
    	}

      return $content;
    }
    
    function debug()
    {
    	var_dump($this->delayed_messages);
    }
    
    function getraw($len=1) {
        return fread($this->fp,$len);
    }
    
    function connected() {
        return isset($this->fp);
    }

    function disconnect() {
        fwrite($this->fp,"/quit\r\n");
        fclose($this->fp);
        unset($this->fp);
    }

    function geterrno() {
        return $this->errno;
    }

    function geterrstr() {
        return $this->errstr;
    }
}
?>