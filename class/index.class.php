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

class index
{
	private $indexes = array(), $file, $fp, $loaded = false, $total = 0;	
	
	public function __construct($file, $read_only = false)
	{
		if($this->loaded) return false;
	
		$rawdata = @file_get_contents($file);
		if(isset($rawdata) && !empty($rawdata))
		{
			$records = split("\n", $rawdata);
			$this->file = $file;
			foreach($records as $record)
			{
				$this->insert($record);
			}
		}
		
		$this->loaded = true;
		
		if(!$read_only)
		{
			$this->fp = fopen($file, 'a');
		
			if($this->fp)
			return $this;
		
			return false;
		}
	}
	
	public function insert($data)
	{
		if(isset($data[0]) && isset($data[1]) && isset($data[2]))
		{
			$data_len = strlen($data);
			$primary_index = (ord($data[0]) & 1); //divide the index by ascii value odd or even of the first character
			$this->indexes[$primary_index][$data_len][$data[0]][$data[1]][$data[2]][] = $data;
			$this->total++;
			if($this->loaded && isset($this->fp))
			fwrite($this->fp, $data . "\n");
		}
	}
	
	public function search($data)
	{
		$data = strtolower($data);
		$primary_index = (ord($data[0]) & 1);		
		$data_len = strlen($data);
		if(isset($this->indexes[$primary_index][$data_len]))
		{
			if(isset($this->indexes[$primary_index][$data_len][$data[0]][$data[1]][$data[2]]))
			{
				foreach($this->indexes[$primary_index][$data_len][$data[0]][$data[1]][$data[2]] as $existing_data)
				{
					if(strcmp($existing_data, $data) == 0)
					{
						return true;
					}
				}
			}
		}
		return false;
	}
		
	public function getTotal()
	{
		return $this->total;
	}
}
?>