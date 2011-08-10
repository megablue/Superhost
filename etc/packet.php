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

function packet_get_conts($packet,$start_offset, $max_len, &$last_offset=0)
{
	$len = strlen($packet);
	$output = '';
	$max_offset = $start_offset + $max_len;
	
	for($i=$start_offset; $i < $max_offset && $i < $len; $i++) $output .= $packet[$i];
	
	$last_offset = $i;
	return $output;
}

function packet_get_string($packet, $start_offset, $max_len, &$last_offset=0)
{
	$len = strlen($packet);
	$max_offset = $start_offset + $max_len;
	
	for($i=$start_offset, $output=''; $packet[$i] != "\x00" && $i < $max_offset && $i < $len; $i++) $output .= $packet[$i];
	
	$last_offset = $i;
	return $output;
}

function packet_dump_hex($data)
{
	$data_len = strlen($data);
	for($i=0,$temp=''; $i < $data_len; $i++)
	{
		$temp .= sprintf('%02X ', ord($data[$i]));
	}
	
	return $temp;
}

function packet_dump($data)
{ 
	echo "\n";
	$data_len = strlen($data);
	for($i=0,$temp=''; $i < $data_len; $i++)
	{
		echo sprintf('%02X ', ord($data[$i]));
		$temp .= ord($data[$i]) < 0x20 || ord($data[$i]) > 0x7f ? '.' : $data[$i];
		if($i%16 == 15)
		{
			echo '      ' . $temp . "\n";
			$temp = '';
		}
	}
	$max = 16 - ($data_len % 16);
	
	if($max > 0)
	{
		for($i = 0; $i < $max; $i++)
		{
			echo '   ';
		}
	
		echo '      ' . $temp . "\n";
		
	}
	echo "\n";
}

?>