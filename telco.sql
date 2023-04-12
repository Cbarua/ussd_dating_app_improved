-- phpMyAdmin SQL Dump
-- version 4.6.6deb5
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 13, 2019 at 04:10 PM
-- Server version: 10.1.41-MariaDB-0ubuntu0.18.04.1
-- PHP Version: 7.2.19-0ubuntu0.18.04.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `telco`
--
CREATE DATABASE IF NOT EXISTS telco;
-- --------------------------------------------------------

--
-- Table structure for table `telco_state`
--

CREATE TABLE IF NOT EXISTS `telco_state` (
  `address` varchar(100) NOT NULL,
  `flow` varchar(30),
  `stage` varchar(30),
  PRIMARY KEY (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `telco_users`
--

CREATE TABLE IF NOT EXISTS `telco_users` (
  `address` varchar(100) NOT NULL,
  `name` varchar(30),
  `username` varchar(30) UNIQUE,
  `birthdate` date,
  `sex` char(6),
  `sub_status` varchar(30),
  `sub_date` date,
  PRIMARY KEY (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `telco_search`
--

CREATE TABLE IF NOT EXISTS `telco_search` (
  `address` varchar(100) NOT NULL,
  `sex` varchar(6),
  `age_range` char(5),
  `total` int,
  `offset` int,
  `chosen_address` varchar(100),
  PRIMARY KEY (`address`),
  FOREIGN KEY (`chosen_address`) REFERENCES `telco_users`(`address`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `telco_dashboard`
--

CREATE TABLE IF NOT EXISTS `telco_dashboard` (
  `date` date NOT NULL,
  `reg` int,
  `unreg` int,
  `pending` int,
  `active` int,
  PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;



/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
