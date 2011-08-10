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
-- Table structure for table `superhost`
-- 

CREATE TABLE `superhost` (
  `id` int(12) NOT NULL auto_increment,
  `hostname` varchar(15) NOT NULL default '',
  `gameport` int(5) NOT NULL default '0',
  `status` tinyint(3) NOT NULL default '0',
  `lastupdate` int(12) NOT NULL default '0',
  PRIMARY KEY  (`id`),
  UNIQUE KEY `hostname_2` (`hostname`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;
