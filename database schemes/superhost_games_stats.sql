-- phpMyAdmin SQL Dump
-- version 2.10.0.2
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Nov 25, 2007 at 06:56 PM
-- Server version: 4.1.12
-- PHP Version: 4.4.7

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- 
-- Database: `blueserver`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `superhost_games_stats`
-- 

CREATE TABLE `superhost_games_stats` (
  `game_id` int(12) NOT NULL default '0',
  `slot_id` tinyint(2) NOT NULL default '0',
  `uid` int(12) NOT NULL default '0',
  `team` tinyint(1) NOT NULL default '0',
  `left_time` int(12) NOT NULL default '0',
  `left_reason` tinyint(2) NOT NULL default '0',
  `desync` tinyint(4) NOT NULL default '0',
  `loadtime` tinyint(4) NOT NULL default '0',
  `latency` int(5) NOT NULL default '0',
  `herokills` tinyint(3) NOT NULL default '0',
  `herodeaths` tinyint(3) NOT NULL default '0',
  `creepkills` tinyint(5) NOT NULL default '0',
  `creepdenies` tinyint(5) NOT NULL default '0',
  PRIMARY KEY  (`game_id`,`slot_id`),
  KEY `uid` (`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
