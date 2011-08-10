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

if(defined('event_class')) return;
define('event_class', 1);

class event
{
	private $socket_id;
	private $events = array();
	
	public function __construct($event_trigger)
	{
		$event_trigger = (int) $event_trigger;
		$this->socket_id = $event_trigger;
	}
	
	public function insert($data)
	{
		$this->events[] = $data;
	}
	
	public function fetch()
	{
		$event_count = count($this->events);
		if(!$event_count) return false;
		$event = array_shift($this->events);
		return $event;
	}
	
	public function getSocketId()
	{
		return $this->socket_id;
	}
}

?>
