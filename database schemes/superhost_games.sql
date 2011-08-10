-- phpMyAdmin SQL Dump
-- version 2.10.0.2
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Nov 25, 2007 at 06:55 PM
-- Server version: 4.1.12
-- PHP Version: 4.4.7

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- 
-- Database: `blueserver`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `superhost_games`
-- 

CREATE TABLE `superhost_games` (
  `game_id` int(12) NOT NULL auto_increment,
  `host_id` int(12) NOT NULL default '0',
  `game_name` varchar(40) NOT NULL default '',
  `game_requester_uid` int(12) NOT NULL default '0',
  `created_time` int(12) NOT NULL default '0',
  `game_state` tinyint(1) NOT NULL default '0',
  `winner` tinyint(1) NOT NULL default '0',
  `game_started_at` int(12) NOT NULL default '0',
  `game_length` int(12) NOT NULL default '0',
  `total_player` tinyint(2) NOT NULL default '0',
  `room_hits` int(12) NOT NULL default '0',
  `upload_band` int(12) NOT NULL default '0',
  `download_band` int(12) NOT NULL default '0',
  `total_band` int(12) NOT NULL default '0',
  `transfer_rate` float NOT NULL default '0',
  PRIMARY KEY  (`game_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=26 ;
