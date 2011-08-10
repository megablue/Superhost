-- phpMyAdmin SQL Dump
-- version 2.10.0.2
-- http://www.phpmyadmin.net
-- 
-- Host: localhost
-- Generation Time: Dec 06, 2007 at 02:38 AM
-- Server version: 4.1.12
-- PHP Version: 4.4.7

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

-- 
-- Database: `blueserver`
-- 

-- --------------------------------------------------------

-- 
-- Table structure for table `superhost_games_fatal_log`
-- 

CREATE TABLE `superhost_games_fatal_log` (
  `game_id` int(12) NOT NULL default '0',
  `log` text NOT NULL,
  PRIMARY KEY  (`game_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
