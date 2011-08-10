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

if(!defined('WORKING_PATH'))
define('WORKING_PATH', dirname(__FILE__) . "/");

date_default_timezone_set('Asia/Kuala_Lumpur');

if(substr(PHP_OS, 0, 3) == 'WIN') 
define('isWindows', true);
else
define('isWindows', false);

class hostException extends Exception {}
class clientException extends Exception {}
class UndefinedVariable extends Exception {}

require_once(WORKING_PATH . "etc/functions.php");
require_once(WORKING_PATH . "etc/packet.php");
require_once(WORKING_PATH . "class/index.class.php");
require_once(WORKING_PATH . "class/timer.class.php");
require_once(WORKING_PATH . "class/event.class.php");
require_once(WORKING_PATH . "class/timer.class.php");
require_once(WORKING_PATH . "class/player.class.php");
require_once(WORKING_PATH . "class/room.class.php");
require_once(WORKING_PATH . "class/task.class.php");
require_once(WORKING_PATH . "class/mysql.class.php");
require_once(WORKING_PATH . "class/map.class.php");
require_once(WORKING_PATH . "class/w3xbnetclient.class.php");
require_once(WORKING_PATH . "class/gamehost.class.php");
require_once(WORKING_PATH . "class/masterbot.class.php");
require_once(WORKING_PATH . "class/hostassistance.class.php");

$old_error_handler = set_error_handler("myErrorHandler");

$dbserver = "mysql.yourserver.com";
$dbusername = "mysql_user";
$dbpassword = "mysql_password";
$dbname = "botdb";
$server_port = 6113;
$server_addr = 'bnet.blueserver.org';
$gameport = 6999;
?>
