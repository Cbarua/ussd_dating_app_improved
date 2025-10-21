--
-- Database: `telco`
--
CREATE DATABASE IF NOT EXISTS telco;
-- --------------------------------------------------------

--
-- Table structure for table `state`
--

CREATE TABLE IF NOT EXISTS `state` (
  `address` varchar(100) NOT NULL,
  `flow` varchar(30),
  `stage` varchar(30),
  PRIMARY KEY (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
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
-- Table structure for table `search`
--

CREATE TABLE IF NOT EXISTS `search` (
  `address` varchar(100) NOT NULL,
  `sex` varchar(6),
  `age_range` char(5),
  `total` int,
  `offset` int,
  `chosen_address` varchar(100),
  PRIMARY KEY (`address`),
  FOREIGN KEY (`chosen_address`) REFERENCES `users`(`address`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `dashboard`
--

CREATE TABLE IF NOT EXISTS `dashboard` (
  `date` date NOT NULL,
  `reg` int,
  `unreg` int,
  `pending` int,
  `active` int,
  PRIMARY KEY (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- New table for users with OTP and registration status
CREATE TABLE `otp_users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `phone` VARCHAR(15) NULL UNIQUE,
  `subscriberId` VARCHAR(150) NULL,
  `os` VARCHAR(30) NULL,
  `device` VARCHAR(100) NULL,
  `ip` VARCHAR(50) NULL,
  `refNo` VARCHAR(50) NULL,
  `otp` INT(6) NULL,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `times` INT(6) NOT NULL DEFAULT 1,
  `status` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
