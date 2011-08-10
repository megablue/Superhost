#!/usr/local/bin/php
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

if(function_exists('pcntl_fork')) 
{
	if( pcntl_fork() != 0) exit;
}

include("init.php");

$supported_commands = array(
								'debug'			 	=> false, 		//debug mode
								'gameport'			=> 6115,        //port that use to host game for this bot
								'username'			=> '$Superhost',//username for the bot
								'hostid'			=> 0,			//reference key to superhost table
								'timeslot'   		=> 100,        	//how fast should host respond
								'creator'    		=> '$Superhost',//player who requested to create the game
								'gamename'   		=> 'Untitled', 	//game name as Untitled by default
								'gamemode'   		=> 'public',  	//public or private
								'map_index'			=> -1,
								'dotamode'  		=> 'ar',      	//ar ap rd lm
								'channel'    		=> 'bluelab', 	//default channel to join once the bot login
								'league_id'			=> 0, 			//league or tournament id
								'team1'   			=> '',			//reservation for team1( Sentinel ), format: player1,player2,player3,player4,player5
								'team2'   			=> '',			//reservation for team2( Scourge ), format: player6,player7,player8,player9,player10 
								'observer' 			=> '',			//gamemaster or observer
								'game_type'  		=> 0 			//0 = custom(public by default), 1 = random team vs random team													 																			//2 = arranged team vs arranged team, 3 = league, 4 = tournament.
							);

//parse arguments
$arguments = arguments($_SERVER['argv']);
foreach($supported_commands as $key => $value)
{
	if(empty($arguments[$key]))
	{
		$cmd[$key] = $value ? $value : '';
	}
	else
	{
		$cmd[$key] = str_replace("'", '', $arguments[$key]);
	}
}
$cmd['username'] = str_replace("\\", '', $cmd['username']);
$cmd['gamename'] = str_replace('_', ' ', $cmd['gamename']);



$client = new hostAssistance(
									$cmd['hostid'], 
									$server_addr, 
									$server_port, 
									$cmd['gameport'], 
									$cmd['username'],
									$cmd['creator'],
									$cmd['gamename'],
									$cmd['map_index'],
									$cmd['gamemode']
									);
$client->process();
?>
