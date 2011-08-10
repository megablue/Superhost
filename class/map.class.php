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

if(defined('mapSelector_class')) return;
define('mapSelector_class', 1);

class mapSelector
{
	private $datafile;
	private $maps;
	private $total=0;
	private $commit_index = -1;
	
	public function __construct()
	{
		$this->datafile = WORKING_PATH . '/data/maps.txt';
		$this->loadData();
	}
	
	private function loadData()
	{
		$rawdata = @ file_get_contents($this->datafile);
		
		$datalen = strlen($rawdata);
		
		for($i=0; $i < $datalen; $i++)
		{
			if($rawdata[$i] == '[')
			{
				//found new section
				$section = '';
				for(++$i; $rawdata[$i] != ']' && $i < $datalen; $i++) $section .= $rawdata[$i];
				++$this->total;
			}
			else if($rawdata[$i] == '!')
			{
				//found new field and value
				$field = '';
				$value = '';
				for(++$i; $rawdata[$i] != '=' && $i < $datalen; $i++)  $field .= $rawdata[$i];
				for(++$i; $rawdata[$i] != "\n" && $i < $datalen; $i++) $value .= $rawdata[$i];
				
				$this->maps[intval($section)][$field] = $value;
			}
		}
	}
	
	public function insert($version, $filename, $filesize, $signature, $crc32)
	{
		$error_flag = false;
		
		if(!$error_flag && !is_string($version)) $error_flag = true;
		if(!$error_flag && strlen($version) > 20) $error_flag = true;
		if(!$error_flag && !is_string($filename)) $error_flag = true;
		if(!$error_flag && hexdec($filesize) > 4294967295) $error_flag = true;
		if(!$error_flag && hexdec($signature) > 4294967295) $error_flag = true;
		if(!$error_flag && hexdec($crc32) > 4294967295)$error_flag = true;
		
		if(!$error_flag)
		{
			$this->maps[$this->total]['version'] = $version;
			$this->maps[$this->total]['filename'] = $filename . ".w3x";
			$this->maps[$this->total]['filesize'] = $filesize;
			$this->maps[$this->total]['signature'] = $signature;
			$this->maps[$this->total]['crc32'] = $crc32;
			$this->maps[$this->total]['temp']  = true;
			$this->commit_index = $this->total;
			++$this->total;
			return true;
		}
				
		return false;
	}
	
	//clear uncommited map info
	public function clear()
	{
		if($this->commit_index >= 0)
		{
			$total = $this->total;
			for($i=$this->commit_index; $i < $total; $i++)
			{
				$data = &$this->maps[$index];
				if(isset($data['temp']) && $data['temp'])
				{
					unset($data);
					--$this->total;
				}
			}
			$this->commit_index = -1;
			return true;
		}
	}
	
	public function commit()
	{
		if($this->commit_index >= 0)
		{
			$fp = fopen($this->datafile, 'a');
			$total = $this->total;
			for($i=$this->commit_index; $i < $total; $i++)
			{
				$data = &$this->maps[$i];
				if(isset($data['temp']) && $data['temp'])
				{
					$temp = "[".$i."]\n";
					$temp .= "!version=" . $data['version'] . "\n";
					$temp .= "!filename=" . $data['filename'] . "\n";
					$temp .= "!filesize=" . $data['filesize'] . "\n";
					$temp .= "!signature=" . $data['signature'] . "\n";
					$temp .= "!crc32=" . $data['crc32'] . "\n";
					unset($data['temp']);
					
					if(!fwrite($fp, $temp))
					{
						fclose($fp);
						return false;
					}
				}
			}
			
			fclose($fp);
			$this->commit_index = -1;
			return true;
		}
	}
	
	public function getMap($index)
	{
		if($index > -1 && $index < $this->total)
		{
			$data = $this->maps[$index];
			
			$data['filesize'] = LONG2DWORD(hexdec($data['filesize']));
			$data['signature']= LONG2DWORD(hexdec($data['signature']));
			$data['crc32']= LONG2DWORD(hexdec($data['crc32']));
			
			return $data;
		}
		else
		{
			return false;
		}
	}
	
	public function getLastMap()
	{
		if($this->total <= 0) return false;
		return $this->getMap($this->total-1);
	}
	
	public function getTotal()
	{
		return $this->total;
	}
}

?>
