-- phpMyAdmin SQL Dump
-- version 4.7.7
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 26, 2018 at 10:23 AM
-- Server version: 5.7.20
-- PHP Version: 7.1.7

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `t2ii_cleandb_1`
--

-- --------------------------------------------------------

--
-- Table structure for table `acc_closing`
--

CREATE TABLE `acc_closing` (
  `id` bigint(20) NOT NULL,
  `client_id` bigint(20) NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `date` date NOT NULL,
  `total` double(20,4) NOT NULL,
  `balance` double(20,4) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `acc_credit`
--

CREATE TABLE `acc_credit` (
  `id` bigint(20) NOT NULL,
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `account_id` bigint(20) NOT NULL,
  `receiver_id` bigint(20) NOT NULL,
  `credit` double(20,4) NOT NULL,
  `debit` double(20,4) NOT NULL,
  `balance` double(20,4) NOT NULL,
  `belong_id` bigint(20) NOT NULL,
  `reference_id` bigint(20) NOT NULL,
  `batch_id` bigint(20) NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` bigint(20) NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `translation_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `data` text COLLATE utf8_unicode_ci NOT NULL,
  `creator_id` bigint(20) NOT NULL,
  `creator_type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` bigint(20) NOT NULL,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `suspended` tinyint(1) NOT NULL,
  `role_id` bigint(20) NOT NULL,
  `last_login` datetime NOT NULL,
  `session_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `last_activity` datetime NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `username`, `name`, `password`, `email`, `disabled`, `suspended`, `role_id`, `last_login`, `session_id`, `last_activity`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin', '$2y$10$z9bslhjLLMN2sLz7tO9QQeboiIToDbrzwBvHwhQEApLywXzpS67Dm', 'admin@ttwoweb.com', 0, 0, 2, '2018-05-14 20:40:02', 'ce673e109529677e00309111ec33e304', '2018-05-14 22:14:34', 0, '2017-08-18 11:00:46', '2018-05-14 20:40:02');

-- --------------------------------------------------------

--
-- Table structure for table `api`
--

CREATE TABLE `api` (
  `id` bigint(20) NOT NULL,
  `command` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `module` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `site` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `duration` int(10) NOT NULL,
  `no_of_queries` int(10) NOT NULL,
  `check_duplicate` tinyint(1) NOT NULL,
  `check_duplicate_interval` int(10) NOT NULL,
  `sample` tinyint(1) NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `api`
--

INSERT INTO `api` (`id`, `command`, `module`, `description`, `site`, `duration`, `no_of_queries`, `check_duplicate`, `check_duplicate_interval`, `sample`, `disabled`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'superAdminLogin', '', 'Super Admin login', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(2, 'apiList', '', 'Get API listing', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(3, 'newApi', '', 'Add new API', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(4, 'deleteApi', '', 'Delete API', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(5, 'editApi', '', 'Edit API', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(6, 'getEditApiData', '', 'Get API data for edit api', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(7, 'getApiData', '', 'Get API data', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(8, 'getNewUpgrades', '', 'Get new upgrades', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(9, 'getUpgradesHistory', '', 'Get upgrades listing', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(10, 'updateAllUpgrades', '', 'Update all new upgrades', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(11, 'getUsers', '', 'Get User listing', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(12, 'addUser', '', 'Add new user', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(13, 'editUser', '', 'Edit user', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(14, 'getUserDetails', '', 'Get user details for edit user', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(15, 'deleteUser', '', 'Delete user', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(16, 'getRoles', '', 'Get Role listing', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(17, 'addRole', '', 'Add new role', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(18, 'editRole', '', 'Edit role', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(19, 'getRoleDetails', '', 'Get role details for edit role', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(20, 'deleteRole', '', 'Delete role', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(21, 'messageAssignedList', '', 'Get Message Assigned listing', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(22, 'deleteMessageAssigned', '', 'Delete message assigned', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(23, 'getMessageCode', '', 'Get Message Code listing', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(24, 'newMessageAssigned', '', 'Add new message assigned', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(25, 'editMessageAssigned', '', 'Edit message assigned', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(26, 'getEditMessageAssignedData', '', 'Get message assigned data for edit message assigned', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(27, 'getMessageSearchData', '', 'Get message search data', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(28, 'getMessageSentList', '', 'Get message sent listing', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(29, 'getMessageErrorList', '', 'Get message error listing', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(30, 'getMessageQueueList', '', 'Get message queue list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(31, 'getErrorCode', '', 'Get error code', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(32, 'getMessageCodes', '', 'Get message code list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(33, 'saveMessageCodeData', '', 'Add new message code data', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(34, 'deleteMessageCode', '', 'Delete message code', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(35, 'editMessageCode', '', 'Edit message code', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(36, 'getEditMessageCodeData', '', 'Get message code data for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(37, 'searchMessageCode', '', 'Get search message code data', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(38, 'newApiParam', '', 'Add new API parameters', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(39, 'getApiParamData', '', 'Get API parameters', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(40, 'getApiName', '', 'Get API name', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(41, 'getEditParamData', '', 'Get API parameters for edit parameters', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(42, 'editParam', '', 'Edit API parameters', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(43, 'deleteApiParam', '', 'Delete API parameters', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(44, 'getApiSearchData', '', 'Get API parameters for edit parameters', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(45, 'searchParamHistory', '', 'Get API parameters for edit parameters', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(46, 'getPermissionsList', '', 'Get permissions listing', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(47, 'deletePermissions', '', 'Delete permissions', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(48, 'newPermission', '', 'Add new permissions', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(49, 'getPermissionData', '', 'Get permission data for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(50, 'editPermissionData', '', 'Edit permission data', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(51, 'getPermissionTree', '', 'Get permission tree', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(52, 'editRolePermission', '', 'Edit role permission', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(53, 'getPermissionNames', '', 'Get permission name', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(54, 'getRoleNames', '', 'Get role name', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(55, 'deleteRolePermission', '', 'Delete role permission', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(56, 'getRolePermissionData', '', 'Get role permission data for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(57, 'newSetting', '', 'Add new setting', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(58, 'getSettingsList', '', 'Get setting list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(59, 'deleteSettings', '', 'Delete setting', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(60, 'getSettingData', '', 'Get role permission data for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(61, 'editSettingData', '', 'Delete setting', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(62, 'editLanguageData', '', 'Edit language data', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(63, 'getLanguageData', '', 'Get language data for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(64, 'getLanguageList', '', 'Get language list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(65, 'deleteLanguage', '', 'Delete language', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(66, 'newLanguage', '', 'Add new language', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(67, 'editLanguageCodeData', '', 'Edit language code data', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(68, 'getLanguageCodeData', '', 'Get language code data for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(69, 'getLanguageCodeList', '', 'Get language code list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(70, 'deleteLanguageCode', '', 'Delete language code', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(71, 'newLanguageCode', '', 'Add new language code', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(72, 'getLanguageRows', '', 'Get language rows', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(73, 'uploadFile', '', 'Upload language file', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(74, 'newProvider', '', 'Add new provider', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(75, 'getProviderData', '', 'Get provider list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(76, 'getEditProviderData', '', 'Get provider data for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(77, 'deleteProvider', '', 'Delete provider', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(78, 'editProvider', '', 'Edit provider', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(79, 'newInternalAccount', '', 'Add new internal account', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(80, 'getInternalAccountsList', '', 'Get internal account list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(81, 'getInternalAccountData', '', 'Get internal account data for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(82, 'deleteInternalAccount', '', 'Delete internal account', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(83, 'editInternalAccountData', '', 'Edit internal account', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(84, 'newJournalTable', '', 'Add new journal table', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(85, 'getJournalTablesList', '', 'Get journal table list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(86, 'getJournalTableData', '', 'Get journal table data for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(87, 'deleteJournalTables', '', 'Delete journal table', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(88, 'editJournalTableData', '', 'Edit journal table', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(89, 'getJournalTableNames', '', 'Get journal table names', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(90, 'newCountry', '', 'Add new country', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(91, 'getCountriesList', '', 'Get country list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(92, 'getCountryData', '', 'Get country data for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(93, 'deleteCountry', '', 'Delete country', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(94, 'editCountryData', '', 'Edit country', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(95, 'addCredit', '', 'Add new credit', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(96, 'getCredits', '', 'Get credit list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(97, 'getCreditDetails', '', 'Get credit details for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(98, 'deleteCredit', '', 'Delete credit', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(99, 'editCredit', '', 'Edit credit', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(100, 'getCreditSettingDetails', '', 'Get credit setting details for edit', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(101, 'editCreditSetting', '', 'Edit credit setting', 'SuperAdmin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(102, 'getClientSettings', '', 'Get client settings', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(103, 'getClientDetails', '', 'Get client details', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(104, 'getWebservices', '', 'Get webservices listing', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(105, 'getAdmins', '', 'Get admin list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(106, 'getAdminDetails', '', 'Get admin details for edit admin', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(107, 'addAdmin', '', 'Add new admin', 'Admin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(108, 'editAdmin', '', 'edit admin', 'Admin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(109, 'deleteAdmin', '', 'delete admin', 'Admin', 1, 10, 1, 60, 0, 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(110, 'getAPIParams', '', 'Test API Usage', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2017-09-25 00:00:00', '2017-09-25 00:00:00'),
(111, 'getActivity', '', 'Get activity log list', 'SuperAdmin', 5, 5, 0, 0, 0, 0, 0, '2018-01-04 13:04:59', '2018-01-04 13:04:59');

-- --------------------------------------------------------

--
-- Table structure for table `api_params`
--

CREATE TABLE `api_params` (
  `id` bigint(20) NOT NULL,
  `api_id` bigint(20) NOT NULL,
  `params_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `params_type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `web_input_type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `compulsory` tinyint(1) NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `api_params`
--

INSERT INTO `api_params` (`id`, `api_id`, `params_name`, `params_type`, `web_input_type`, `compulsory`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 10, 'username', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(2, 12, 'fullName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(3, 12, 'email', 'general', 'email', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(4, 12, 'password', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(5, 12, 'roleID', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(6, 12, 'status', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(7, 13, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(8, 13, 'fullName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(9, 13, 'email', 'general', 'email', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(10, 13, 'roleID', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(11, 13, 'status', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(12, 15, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(13, 17, 'roleName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(14, 17, 'description', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(15, 17, 'status', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(16, 18, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(17, 18, 'roleName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(18, 18, 'description', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(19, 18, 'status', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(20, 20, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(21, 22, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(22, 24, 'messageCode', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(23, 24, 'messageRecipient', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(24, 24, 'messageType', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(25, 25, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(26, 25, 'code', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(27, 25, 'recipient', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(28, 25, 'type', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(29, 33, 'code', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(30, 33, 'description', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(31, 34, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(32, 35, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(33, 35, 'code', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(34, 35, 'description', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(35, 47, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(36, 48, 'name', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(37, 48, 'type', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(38, 48, 'description', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(39, 48, 'parent', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(40, 48, 'filePath', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(41, 48, 'priority', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(42, 48, 'iconClass', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(43, 48, 'disabled', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(44, 50, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(45, 50, 'name', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(46, 50, 'type', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(47, 50, 'description', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(48, 50, 'parent', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(49, 50, 'filePath', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(50, 50, 'priority', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(51, 50, 'iconClass', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(52, 50, 'disabled', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(53, 52, 'roleName', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(54, 52, 'permissionsList', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(55, 55, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(56, 57, 'name', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(57, 57, 'type', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(58, 57, 'reference', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(59, 57, 'value', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(60, 59, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(61, 61, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(62, 61, 'name', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(63, 61, 'type', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(64, 61, 'reference', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(65, 61, 'value', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(66, 62, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(67, 62, 'languageName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(68, 62, 'languageCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(69, 62, 'isoCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(70, 62, 'status', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(71, 65, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(72, 66, 'language', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(73, 66, 'languageCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(74, 66, 'isoCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(75, 66, 'status', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(76, 67, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(77, 67, 'contentCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(78, 67, 'module', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(79, 67, 'language', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(80, 67, 'site', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(81, 67, 'category', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(82, 67, 'content', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(83, 70, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(84, 71, 'contentCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(85, 71, 'site', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(86, 71, 'category', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(87, 71, 'module', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(88, 71, 'languageData', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(89, 73, 'data', 'general', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(90, 73, 'type', 'general', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(91, 73, 'fileName', 'general', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(92, 74, 'commandName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(93, 74, 'username', 'general', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(94, 74, 'password', 'alphanumeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(95, 74, 'company', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(96, 74, 'apiKey', 'alphanumeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(97, 74, 'type', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(98, 74, 'priority', 'integer', 'number', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(99, 74, 'disabled', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(100, 74, 'defaultSender', 'alphanumeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(101, 74, 'url1', 'general', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(102, 74, 'url2', 'general', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(103, 74, 'remark', 'alphanumeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(104, 74, 'name', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(105, 74, 'currency', 'alphanumeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(106, 74, 'balance', 'numeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(107, 77, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(108, 78, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(109, 78, 'commandName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(110, 78, 'username', 'general', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(111, 78, 'password', 'alphanumeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(112, 78, 'company', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(113, 78, 'apiKey', 'alphanumeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(114, 78, 'type', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(115, 78, 'priority', 'integer', 'number', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(116, 78, 'disabled', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(117, 78, 'defaultSender', 'alphanumeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(118, 78, 'url1', 'general', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(119, 78, 'url2', 'general', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(120, 78, 'remark', 'alphanumeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(121, 78, 'name', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(122, 78, 'currency', 'alphanumeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(123, 78, 'balance', 'numeric', 'text', 0, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(124, 79, 'username', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(125, 79, 'name', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(126, 79, 'description', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(127, 82, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(128, 83, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(129, 83, 'username', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(130, 83, 'name', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(131, 83, 'description', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(132, 84, 'tableName', 'general', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(133, 84, 'type', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(134, 84, 'disabled', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(135, 87, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(136, 88, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(137, 88, 'tableName', 'general', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(138, 88, 'type', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(139, 88, 'disabled', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(140, 90, 'name', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(141, 90, 'isoCode2', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(142, 90, 'isoCode3', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(143, 90, 'countryCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(144, 90, 'currencyCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(145, 93, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(146, 94, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(147, 94, 'name', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(148, 94, 'isoCode2', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(149, 94, 'isoCode3', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(150, 94, 'countryCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(151, 94, 'currencyCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(152, 95, 'creditName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(153, 95, 'description', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(154, 95, 'translationCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(155, 95, 'priority', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(156, 98, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(157, 99, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(158, 99, 'creditName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(159, 99, 'description', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(160, 99, 'translationCode', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(161, 99, 'priority', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(162, 101, 'creditID', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(163, 101, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(164, 101, 'values', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(165, 38, 'apiId', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(166, 38, 'apiParamName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(167, 38, 'apiParamValue', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(168, 42, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(169, 42, 'commandName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(170, 42, 'apiId', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(171, 42, 'apiParamName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(172, 42, 'apiParamValue', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(173, 43, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(174, 107, 'fullName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(175, 107, 'username', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(176, 107, 'email', 'general', 'email', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(177, 107, 'password', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(178, 107, 'roleID', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(179, 107, 'status', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(180, 108, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(181, 108, 'fullName', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(182, 108, 'username', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(183, 108, 'email', 'general', 'email', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(184, 108, 'roleID', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(185, 108, 'status', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(186, 109, 'id', 'integer', 'number', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(187, 3, 'command', 'alphanumeric', 'text', 1, 0, '2017-08-30 10:12:28', '0000-00-00 00:00:00'),
(188, 3, 'duration', 'integer', 'number', 1, 0, '2017-08-30 10:12:28', '0000-00-00 00:00:00'),
(189, 3, 'queries', 'integer', 'number', 1, 0, '2017-08-30 10:12:28', '0000-00-00 00:00:00'),
(190, 3, 'description', 'general', 'text', 0, 0, '2017-08-30 10:12:28', '0000-00-00 00:00:00'),
(191, 3, 'status', 'integer', 'number', 1, 0, '2017-08-30 10:12:28', '0000-00-00 00:00:00'),
(192, 12, 'username', 'alphanumeric', 'text', 1, 0, '2017-08-23 22:09:10', '2017-08-23 22:09:10'),
(193, 35, 'module', 'alphanumeric', 'text', 1, 0, '2018-03-16 01:10:02', '2018-03-16 01:10:02'),
(194, 35, 'title', 'alphanumeric', 'text', 1, 0, '2018-03-16 01:10:02', '2018-03-16 01:10:02'),
(195, 33, 'module', 'alphanumeric', 'text', 1, 0, '2018-03-16 01:10:02', '2018-03-16 01:10:02'),
(196, 33, 'title', 'alphanumeric', 'text', 1, 0, '2018-03-16 01:10:02', '2018-03-16 01:10:02'),
(197, 57, 'module', 'alphanumeric', 'text', 1, 0, '2018-03-16 01:10:02', '2018-03-16 01:10:02'),
(198, 61, 'module', 'alphanumeric', 'text', 1, 0, '2018-03-16 01:10:02', '2018-03-16 01:10:02'),
(199, 33, 'content', 'general', 'text', 1, 0, '2018-05-15 01:20:32', '2018-05-15 01:20:32'),
(200, 35, 'content', 'general', 'text', 1, 0, '2018-05-15 01:20:32', '2018-05-15 01:20:32');

-- --------------------------------------------------------

--
-- Table structure for table `api_sample`
--

CREATE TABLE `api_sample` (
  `id` bigint(20) NOT NULL,
  `api_id` bigint(20) NOT NULL,
  `output` longtext COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit`
--

CREATE TABLE `audit` (
  `id` bigint(20) NOT NULL,
  `method` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `row_id` bigint(20) NOT NULL,
  `old_value` text COLLATE utf8_unicode_ci NOT NULL,
  `new_value` text COLLATE utf8_unicode_ci NOT NULL,
  `column_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_type` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `city`
--

CREATE TABLE `city` (
  `id` bigint(20) NOT NULL,
  `state_id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `translation_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cleanup_table`
--

CREATE TABLE `cleanup_table` (
  `id` bigint(20) NOT NULL,
  `table_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `table_type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `days` int(10) NOT NULL,
  `created_at` datetime NOT NULL,
  `backup` tinyint(1) NOT NULL,
  `disabled` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `cleanup_table`
--

INSERT INTO `cleanup_table` (`id`, `table_name`, `table_type`, `days`, `created_at`, `backup`, `disabled`) VALUES
(1, 'web_services', 'daily table', 7, '2017-08-08 11:34:47', 0, 0),
(2, 'sent_history', 'daily table', 90, '2017-08-28 00:00:00', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `client`
--

CREATE TABLE `client` (
  `id` bigint(20) NOT NULL,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `transaction_password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `token_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `session_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `country_id` bigint(20) NOT NULL,
  `state_id` bigint(20) NOT NULL,
  `county_id` bigint(20) NOT NULL,
  `city_id` bigint(20) NOT NULL,
  `sponsor_id` bigint(20) NOT NULL,
  `placement_id` bigint(20) NOT NULL,
  `placement_unit` int(10) NOT NULL,
  `placement_position` tinyint(1) NOT NULL,
  `activated` tinyint(1) NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `suspended` tinyint(1) NOT NULL,
  `freezed` tinyint(1) NOT NULL,
  `last_login` datetime NOT NULL,
  `last_activity` datetime NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `client`
--

INSERT INTO `client` (`id`, `username`, `name`, `password`, `transaction_password`, `token_id`, `session_id`, `type`, `description`, `email`, `phone`, `address`, `country_id`, `state_id`, `county_id`, `city_id`, `sponsor_id`, `placement_id`, `placement_unit`, `placement_position`, `activated`, `disabled`, `suspended`, `freezed`, `last_login`, `last_activity`, `deleted`, `created_at`, `updated_at`) VALUES
(1000000, 'director', 'director', '$2y$10$BmqFdX2WgjjXqALKhvAiuuZsySM2OrkImluuNok74Xv', '$2y$10$BmqFdX2WgjjXqALKhvAiuuZsySM2OrkImluuNok74Xv', '', '', 'Client', 'First account in the company', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, '2017-08-16 00:00:00', '2017-08-16 00:00:00'),
(1, 'creditSales', 'creditSales', '', '', '', '', 'Internal', 'Expenses', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, '2017-08-16 00:00:00', '0000-00-00 00:00:00'),
(2, 'withdrawal', 'withdrawal', '', '', '', '', 'Internal', 'Suspense', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, '2017-08-16 00:00:00', '0000-00-00 00:00:00'),
(3, 'transfer', 'transfer', '', '', '', '', 'Internal', 'Suspense', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, '2017-08-16 00:00:00', '0000-00-00 00:00:00'),
(4, 'convert', 'convert', '', '', '', '', 'Internal', 'Suspense', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, '2017-08-16 00:00:00', '0000-00-00 00:00:00'),
(5, 'payout', 'payout', '', '', '', '', 'Internal', 'Expenses', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, '2017-08-16 00:00:00', '0000-00-00 00:00:00'),
(6, 'creditAdjustment', 'creditAdjustment', '', '', '', '', 'Internal', 'Earnings', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, '2017-08-16 00:00:00', '0000-00-00 00:00:00'),
(7, 'creditRefund', 'creditRefund', '', '', '', '', 'Internal', 'Expenses', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, '2017-08-16 00:00:00', '0000-00-00 00:00:00'),
(8, 'creditSpending', 'creditSpending', '', '', '', '', 'Internal', 'Earnings', '', '', '', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00', 0, '2017-08-16 00:00:00', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `client_setting`
--

CREATE TABLE `client_setting` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `value` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `reference` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `client_id` bigint(20) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `country`
--

CREATE TABLE `country` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `iso_code2` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `iso_code3` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `country_code` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `currency_code` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `translation_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `country`
--

INSERT INTO `country` (`id`, `name`, `iso_code2`, `iso_code3`, `country_code`, `currency_code`, `translation_code`, `created_at`, `updated_at`) VALUES
(1, 'Afghanistan', 'AF', 'AFG', '93', 'AFN', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(2, 'Albania', 'AL', 'ALB', '355', 'ALL', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(3, 'Algeria', 'DZ', 'DZA', '213', 'DZD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(4, 'American Samoa', 'AS', 'ASM', '1684', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(5, 'Andorra', 'AD', 'AND', '376', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(6, 'Angola', 'AO', 'AGO', '244', 'AOA', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(7, 'Anguilla', 'AI', 'AIA', '1264', 'XCD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(8, 'Antarctica', 'AQ', 'ATA', '0', '', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(9, 'Antigua and Barbuda', 'AG', 'ATG', '1268', 'XCD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(10, 'Argentina', 'AR', 'ARG', '54', 'ARS', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(11, 'Armenia', 'AM', 'ARM', '374', 'AMD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(12, 'Aruba', 'AW', 'ABW', '297', 'AWG', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(13, 'Australia', 'AU', 'AUS', '61', 'AUD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(14, 'Austria', 'AT', 'AUT', '43', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(15, 'Azerbaijan', 'AZ', 'AZE', '994', 'AZN', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(16, 'Bahamas', 'BS', 'BHS', '1242', 'BSD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(17, 'Bahrain', 'BH', 'BHR', '973', 'BHD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(18, 'Bangladesh', 'BD', 'BGD', '880', 'BDT', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(19, 'Barbados', 'BB', 'BRB', '1246', 'BBD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(20, 'Belarus', 'BY', 'BLR', '375', 'BYR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(21, 'Belgium', 'BE', 'BEL', '32', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(22, 'Belize', 'BZ', 'BLZ', '501', 'BZD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(23, 'Benin', 'BJ', 'BEN', '229', 'XOF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(24, 'Bermuda', 'BM', 'BMU', '1441', 'BMD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(25, 'Bhutan', 'BT', 'BTN', '975', 'BTN', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(26, 'Bolivia', 'BO', 'BOL', '591', 'BOB', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(27, 'Bosnia and Herzegovina', 'BA', 'BIH', '387', 'BAM', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(28, 'Botswana', 'BW', 'BWA', '267', 'BWP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(29, 'Bouvet Island', 'BV', 'BVT', '0', 'NOK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(30, 'Brazil', 'BR', 'BRA', '55', 'BRL', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(31, 'British Indian Ocean Territory', 'IO', 'IOT', '246', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(32, 'Brunei Darussalam', 'BN', 'BRN', '673', 'BND', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(33, 'Bulgaria', 'BG', 'BGR', '359', 'BGN', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(34, 'Burkina Faso', 'BF', 'BFA', '226', 'XOF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(35, 'Burundi', 'BI', 'BDI', '257', 'BIF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(36, 'Cambodia', 'KH', 'KHM', '855', 'KHR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(37, 'Cameroon', 'CM', 'CMR', '237', 'XAF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(38, 'Canada', 'CA', 'CAN', '1', 'CAD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(39, 'Cape Verde', 'CV', 'CPV', '238', 'CVE', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(40, 'Cayman Islands', 'KY', 'CYM', '1345', 'KYD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(41, 'Central African Republic', 'CF', 'CAF', '236', 'XAF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(42, 'Chad', 'TD', 'TCD', '235', 'XAF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(43, 'Chile', 'CL', 'CHL', '56', 'CLP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(44, 'China', 'CN', 'CHN', '86', 'CNY', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(45, 'Christmas Island', 'CX', '', '61', 'AUD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(46, 'Cocos (Keeling) Islands', 'CC', '', '672', 'AUD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(47, 'Colombia', 'CO', 'COL', '57', 'COP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(48, 'Comoros', 'KM', 'COM', '269', 'KMF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(49, 'Congo', 'CG', 'COG', '242', 'XAF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(50, 'Congo, the Democratic Republic of the', 'CD', 'COD', '243', 'CDF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(51, 'Cook Islands', 'CK', 'COK', '682', 'NZD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(52, 'Costa Rica', 'CR', 'CRI', '506', 'CRC', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(53, 'Cote D\'Ivoire', 'CI', 'CIV', '225', 'XOF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(54, 'Croatia', 'HR', 'HRV', '385', 'HRK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(55, 'Cuba', 'CU', 'CUB', '53', 'CUP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(56, 'Cyprus', 'CY', 'CYP', '357', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(57, 'Czech Republic', 'CZ', 'CZE', '420', 'CZK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(58, 'Denmark', 'DK', 'DNK', '45', 'DKK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(59, 'Djibouti', 'DJ', 'DJI', '253', 'DJF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(60, 'Dominica', 'DM', 'DMA', '1767', 'XCD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(61, 'Dominican Republic', 'DO', 'DOM', '1809', 'DOP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(62, 'Ecuador', 'EC', 'ECU', '593', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(63, 'Egypt', 'EG', 'EGY', '20', 'EGP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(64, 'El Salvador', 'SV', 'SLV', '503', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(65, 'Equatorial Guinea', 'GQ', 'GNQ', '240', 'XAF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(66, 'Eritrea', 'ER', 'ERI', '291', 'ERN', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(67, 'Estonia', 'EE', 'EST', '372', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(68, 'Ethiopia', 'ET', 'ETH', '251', 'ETB', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(69, 'Falkland Islands (Malvinas)', 'FK', 'FLK', '500', 'FKP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(70, 'Faroe Islands', 'FO', 'FRO', '298', 'DKK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(71, 'Fiji', 'FJ', 'FJI', '679', 'FJD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(72, 'Finland', 'FI', 'FIN', '358', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(73, 'France', 'FR', 'FRA', '33', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(74, 'French Guiana', 'GF', 'GUF', '594', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(75, 'French Polynesia', 'PF', 'PYF', '689', 'XPF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(76, 'French Southern Territories', 'TF', '', '0', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(77, 'Gabon', 'GA', 'GAB', '241', 'XAF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(78, 'Gambia', 'GM', 'GMB', '220', 'GMD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(79, 'Georgia', 'GE', 'GEO', '995', 'GEL', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(80, 'Germany', 'DE', 'DEU', '49', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(81, 'Ghana', 'GH', 'GHA', '233', 'GHS', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(82, 'Gibraltar', 'GI', 'GIB', '350', 'GBP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(83, 'Greece', 'GR', 'GRC', '30', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(84, 'Greenland', 'GL', 'GRL', '299', 'DKK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(85, 'Grenada', 'GD', 'GRD', '1473', 'XCD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(86, 'Guadeloupe', 'GP', 'GLP', '590', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(87, 'Guam', 'GU', 'GUM', '1671', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(88, 'Guatemala', 'GT', 'GTM', '502', 'GTQ', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(89, 'Guinea', 'GN', 'GIN', '224', 'GNF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(90, 'Guinea-Bissau', 'GW', 'GNB', '245', 'XOF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(91, 'Guyana', 'GY', 'GUY', '592', 'GYD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(92, 'Haiti', 'HT', 'HTI', '509', 'HTG', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(93, 'Heard Island and Mcdonald Islands', 'HM', '', '0', 'AUD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(94, 'Holy See (Vatican City State)', 'VA', 'VAT', '39', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(95, 'Honduras', 'HN', 'HND', '504', 'HNL', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(96, 'Hong Kong', 'HK', 'HKG', '852', 'HKD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(97, 'Hungary', 'HU', 'HUN', '36', 'HUF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(98, 'Iceland', 'IS', 'ISL', '354', 'ISK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(99, 'India', 'IN', 'IND', '91', 'INR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(100, 'Indonesia', 'ID', 'IDN', '62', 'IDR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(101, 'Iran, Islamic Republic of', 'IR', 'IRN', '98', 'IRR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(102, 'Iraq', 'IQ', 'IRQ', '964', 'IQD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(103, 'Ireland', 'IE', 'IRL', '353', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(104, 'Israel', 'IL', 'ISR', '972', 'ILS', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(105, 'Italy', 'IT', 'ITA', '39', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(106, 'Jamaica', 'JM', 'JAM', '1876', 'JMD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(107, 'Japan', 'JP', 'JPN', '81', 'JPY', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(108, 'Jordan', 'JO', 'JOR', '962', 'JOD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(109, 'Kazakhstan', 'KZ', 'KAZ', '7', 'KZT', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(110, 'Kenya', 'KE', 'KEN', '254', 'KES', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(111, 'Kiribati', 'KI', 'KIR', '686', 'AUD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(112, 'Korea, Democratic People\'s Republic of', 'KP', 'PRK', '850', 'KPW', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(113, 'Korea, Republic of', 'KR', 'KOR', '82', 'KRW', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(114, 'Kuwait', 'KW', 'KWT', '965', 'KWD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(115, 'Kyrgyzstan', 'KG', 'KGZ', '996', 'KGS', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(116, 'Lao People\'s Democratic Republic', 'LA', 'LAO', '856', 'LAK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(117, 'Latvia', 'LV', 'LVA', '371', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(118, 'Lebanon', 'LB', 'LBN', '961', 'LBP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(119, 'Lesotho', 'LS', 'LSO', '266', 'LSL', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(120, 'Liberia', 'LR', 'LBR', '231', 'LRD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(121, 'Libyan Arab Jamahiriya', 'LY', 'LBY', '218', 'LYD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(122, 'Liechtenstein', 'LI', 'LIE', '423', 'CHF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(123, 'Lithuania', 'LT', 'LTU', '370', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(124, 'Luxembourg', 'LU', 'LUX', '352', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(125, 'Macao', 'MO', 'MAC', '853', 'MOP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(126, 'Macedonia, the Former Yugoslav Republic of', 'MK', 'MKD', '389', 'MKD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(127, 'Madagascar', 'MG', 'MDG', '261', 'MGA', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(128, 'Malawi', 'MW', 'MWI', '265', 'MWK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(129, 'Malaysia', 'MY', 'MYS', '60', 'MYR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(130, 'Maldives', 'MV', 'MDV', '960', 'MVR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(131, 'Mali', 'ML', 'MLI', '223', 'XOF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(132, 'Malta', 'MT', 'MLT', '356', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(133, 'Marshall Islands', 'MH', 'MHL', '692', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(134, 'Martinique', 'MQ', 'MTQ', '596', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(135, 'Mauritania', 'MR', 'MRT', '222', 'MRO', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(136, 'Mauritius', 'MU', 'MUS', '230', 'MUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(137, 'Mayotte', 'YT', '', '269', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(138, 'Mexico', 'MX', 'MEX', '52', 'MXN', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(139, 'Micronesia, Federated States of', 'FM', 'FSM', '691', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(140, 'Moldova, Republic of', 'MD', 'MDA', '373', 'MDL', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(141, 'Monaco', 'MC', 'MCO', '377', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(142, 'Mongolia', 'MN', 'MNG', '976', 'MNT', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(143, 'Montserrat', 'MS', 'MSR', '1664', 'XCD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(144, 'Morocco', 'MA', 'MAR', '212', 'MAD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(145, 'Mozambique', 'MZ', 'MOZ', '258', 'MZN', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(146, 'Myanmar', 'MM', 'MMR', '95', 'MMK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(147, 'Namibia', 'NA', 'NAM', '264', 'NAD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(148, 'Nauru', 'NR', 'NRU', '674', 'AUD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(149, 'Nepal', 'NP', 'NPL', '977', 'NPR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(150, 'Netherlands', 'NL', 'NLD', '31', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(151, 'Netherlands Antilles', 'AN', 'ANT', '599', 'ANG', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(152, 'New Caledonia', 'NC', 'NCL', '687', 'XPF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(153, 'New Zealand', 'NZ', 'NZL', '64', 'NZD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(154, 'Nicaragua', 'NI', 'NIC', '505', 'NIO', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(155, 'Niger', 'NE', 'NER', '227', 'XOF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(156, 'Nigeria', 'NG', 'NGA', '234', 'NGN', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(157, 'Niue', 'NU', 'NIU', '683', 'NZD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(158, 'Norfolk Island', 'NF', 'NFK', '672', 'AUD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(159, 'Northern Mariana Islands', 'MP', 'MNP', '1670', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(160, 'Norway', 'NO', 'NOR', '47', 'NOK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(161, 'Oman', 'OM', 'OMN', '968', 'OMR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(162, 'Pakistan', 'PK', 'PAK', '92', 'PKR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(163, 'Palau', 'PW', 'PLW', '680', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(164, 'Palestinian Territory, Occupied', 'PS', '', '970', 'ILS', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(165, 'Panama', 'PA', 'PAN', '507', 'PAB', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(166, 'Papua New Guinea', 'PG', 'PNG', '675', 'PGK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(167, 'Paraguay', 'PY', 'PRY', '595', 'PYG', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(168, 'Peru', 'PE', 'PER', '51', 'PEN', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(169, 'Philippines', 'PH', 'PHL', '63', 'PHP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(170, 'Pitcairn', 'PN', 'PCN', '0', 'NZD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(171, 'Poland', 'PL', 'POL', '48', 'PLN', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(172, 'Portugal', 'PT', 'PRT', '351', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(173, 'Puerto Rico', 'PR', 'PRI', '1787', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(174, 'Qatar', 'QA', 'QAT', '974', 'QAR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(175, 'Reunion', 'RE', 'REU', '262', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(176, 'Romania', 'RO', 'ROM', '40', 'RON', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(177, 'Russian Federation', 'RU', 'RUS', '7', 'RUB', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(178, 'Rwanda', 'RW', 'RWA', '250', 'RWF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(179, 'Saint Helena', 'SH', 'SHN', '290', 'SHP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(180, 'Saint Kitts and Nevis', 'KN', 'KNA', '1869', 'XCD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(181, 'Saint Lucia', 'LC', 'LCA', '1758', 'XCD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(182, 'Saint Pierre and Miquelon', 'PM', 'SPM', '508', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(183, 'Saint Vincent and the Grenadines', 'VC', 'VCT', '1784', 'XCD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(184, 'Samoa', 'WS', 'WSM', '684', 'WST', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(185, 'San Marino', 'SM', 'SMR', '378', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(186, 'Sao Tome and Principe', 'ST', 'STP', '239', 'STD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(187, 'Saudi Arabia', 'SA', 'SAU', '966', 'SAR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(188, 'Senegal', 'SN', 'SEN', '221', 'XOF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(190, 'Seychelles', 'SC', 'SYC', '248', 'SCR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(191, 'Sierra Leone', 'SL', 'SLE', '232', 'SLL', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(192, 'Singapore', 'SG', 'SGP', '65', 'SGD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(193, 'Slovakia', 'SK', 'SVK', '421', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(194, 'Slovenia', 'SI', 'SVN', '386', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(195, 'Solomon Islands', 'SB', 'SLB', '677', 'SBD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(196, 'Somalia', 'SO', 'SOM', '252', 'SOS', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(197, 'South Africa', 'ZA', 'ZAF', '27', 'ZAR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(198, 'South Georgia and the South Sandwich Islands', 'GS', '', '0', 'FKP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(199, 'Spain', 'ES', 'ESP', '34', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(200, 'Sri Lanka', 'LK', 'LKA', '94', 'LKR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(201, 'Sudan', 'SD', 'SDN', '249', 'SDG', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(202, 'Suriname', 'SR', 'SUR', '597', 'SRD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(203, 'Svalbard and Jan Mayen', 'SJ', 'SJM', '47', 'NOK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(204, 'Swaziland', 'SZ', 'SWZ', '268', 'SZL', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(205, 'Sweden', 'SE', 'SWE', '46', 'SEK', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(206, 'Switzerland', 'CH', 'CHE', '41', 'CHF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(207, 'Syrian Arab Republic', 'SY', 'SYR', '963', 'SYP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(208, 'Taiwan, Province of China', 'TW', 'TWN', '886', 'TWD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(209, 'Tajikistan', 'TJ', 'TJK', '992', 'TJS', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(210, 'Tanzania, United Republic of', 'TZ', 'TZA', '255', 'TZS', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(211, 'Thailand', 'TH', 'THA', '66', 'THB', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(212, 'Timor-Leste', 'TL', '', '670', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(213, 'Togo', 'TG', 'TGO', '228', 'XOF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(214, 'Tokelau', 'TK', 'TKL', '690', 'NZD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(215, 'Tonga', 'TO', 'TON', '676', 'TOP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(216, 'Trinidad and Tobago', 'TT', 'TTO', '1868', 'TTD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(217, 'Tunisia', 'TN', 'TUN', '216', 'TND', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(218, 'Turkey', 'TR', 'TUR', '90', 'TRY', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(219, 'Turkmenistan', 'TM', 'TKM', '7370', 'TMT', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(220, 'Turks and Caicos Islands', 'TC', 'TCA', '1649', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(221, 'Tuvalu', 'TV', 'TUV', '688', 'AUD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(222, 'Uganda', 'UG', 'UGA', '256', 'UGX', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(223, 'Ukraine', 'UA', 'UKR', '380', 'UAH', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(224, 'United Arab Emirates', 'AE', 'ARE', '971', 'AED', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(225, 'United Kingdom', 'GB', 'GBR', '44', 'GBP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(226, 'United States', 'US', 'USA', '1', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(227, 'United States Minor Outlying Islands', 'UM', '', '1', '', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(228, 'Uruguay', 'UY', 'URY', '598', 'UYU', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(229, 'Uzbekistan', 'UZ', 'UZB', '998', 'UZS', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(230, 'Vanuatu', 'VU', 'VUT', '678', 'VUV', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(231, 'Venezuela', 'VE', 'VEN', '58', 'VEF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(232, 'Viet Nam', 'VN', 'VNM', '84', 'VND', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(233, 'Virgin Islands, British', 'VG', 'VGB', '1284', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(234, 'Virgin Islands, U.s.', 'VI', 'VIR', '1340', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(235, 'Wallis and Futuna', 'WF', 'WLF', '681', 'XPF', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(236, 'Western Sahara', 'EH', 'ESH', '212', 'MAD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(237, 'Yemen', 'YE', 'YEM', '967', 'YER', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(238, 'Zambia', 'ZM', 'ZMB', '260', 'ZMW', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(239, 'Zimbabwe', 'ZW', 'ZWE', '263', 'ZWL', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(240, 'Serbia', 'RS', 'SRB', '381', 'RSD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(241, 'Asia / Pacific Region', 'AP', '0', '0', '', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(242, 'Montenegro', 'ME', 'MNE', '382', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(243, 'Aland Islands', 'AX', 'ALA', '358', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(244, 'Bonaire, Sint Eustatius and Saba', 'BQ', 'BES', '599', 'USD', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(245, 'Curacao', 'CW', 'CUW', '599', 'ANG', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(246, 'Guernsey', 'GG', 'GGY', '44', 'GBP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(247, 'Isle of Man', 'IM', 'IMN', '44', 'GBP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(248, 'Jersey', 'JE', 'JEY', '44', 'GBP', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(249, 'Kosovo', 'XK', '---', '381', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(250, 'Saint Barthelemy', 'BL', 'BLM', '590', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(251, 'Saint Martin', 'MF', 'MAF', '590', 'EUR', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(252, 'Sint Maarten', 'SX', 'SXM', '1', 'ANG', '', '2017-08-02 15:54:56', '0000-00-00 00:00:00'),
(253, 'South Sudan', 'SS', 'SSD', '211', 'SSP', '', '2017-08-02 16:20:30', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `county`
--

CREATE TABLE `county` (
  `id` bigint(20) NOT NULL,
  `city_id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `translation_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credit`
--

CREATE TABLE `credit` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `translation_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `priority` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credit_setting`
--

CREATE TABLE `credit_setting` (
  `id` bigint(20) NOT NULL,
  `credit_id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `reference` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credit_setting_preset`
--

CREATE TABLE `credit_setting_preset` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `reference` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enumerators`
--

CREATE TABLE `enumerators` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `translation_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `priority` int(10) NOT NULL,
  `deleted` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_tables`
--

CREATE TABLE `journal_tables` (
  `id` bigint(20) NOT NULL,
  `table_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `languages`
--

CREATE TABLE `languages` (
  `id` bigint(20) NOT NULL,
  `language` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `language_code` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `iso_code` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `languages`
--

INSERT INTO `languages` (`id`, `language`, `language_code`, `iso_code`, `disabled`, `created_at`, `updated_at`) VALUES
(1, 'english', 'L00001', 'en', 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'chineseSimplified', 'L00002', 'zh-Hans', 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3, 'chineseTraditional', 'L00003', 'zh-Hant', 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(4, 'malay', 'L00004', 'ml', 1, '0000-00-00 00:00:00', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `language_import`
--

CREATE TABLE `language_import` (
  `id` bigint(20) NOT NULL,
  `file_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `processed` tinyint(1) NOT NULL,
  `upload_id` bigint(20) NOT NULL,
  `created_by` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `language_translation`
--

CREATE TABLE `language_translation` (
  `id` bigint(20) NOT NULL,
  `code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `module` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `language` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `site` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `content` text COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `log_upgrade`
--

CREATE TABLE `log_upgrade` (
  `id` bigint(20) NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `created_by` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_assigned`
--

CREATE TABLE `message_assigned` (
  `id` bigint(20) NOT NULL,
  `code` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `recipient` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_code`
--

CREATE TABLE `message_code` (
  `id` bigint(20) NOT NULL,
  `code` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `content` text COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `module` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `message_code`
--

INSERT INTO `message_code` (`id`, `code`, `title`, `content`, `description`, `module`, `deleted`, `created_at`) VALUES
(1, '10001', 'API Slow Response', 'API %%apiName%% exceeded allowed processing time of %%apiTime%% second(s). \nTime taken: %%seconds%% second(s)', 'Triggers in webservices.php when API takes too long to process.', 'Standard Platform', 0, '0000-00-00 00:00:00'),
(2, '10002', 'API Too Many Queries', 'API %%apiName%% exceeded allowed number of queries. \nAllowed: %%apiAllowed%% \nCurrent: %%apiCurrent%%', 'Triggers in webservices.php when API runs too many queries.', 'Standard Platform', 0, '0000-00-00 00:00:00'),
(3, '10003', 'Invalid API Request', 'Invalid call to API %%apiName%%. Command not found.', 'Triggers when someone sends an invalid API command.', 'Standard Platform', 0, '0000-00-00 00:00:00'),
(4, '10004', 'Process Down', 'Process %%processName%% is down. \n Server: %%serverName%% \nServer IP: %%serverIP%%.', 'Triggers when any PHP background process is dead/not responding.', 'Standard Platform', 0, '0000-00-00 00:00:00'),
(5, '10005', 'Acc Closing Problem', 'Acc Closing %%closingDate%% balance is not tally. \nDiff: %%amountDiff%%', 'Triggers when checking the balance after running cronjob acc credit closing.', 'Standard Platform', 0, '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `message_error`
--

CREATE TABLE `message_error` (
  `id` bigint(20) NOT NULL,
  `message_id` bigint(20) NOT NULL,
  `processor` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `error_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `error_description` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_in`
--

CREATE TABLE `message_in` (
  `id` bigint(20) NOT NULL,
  `provider_id` bigint(20) NOT NULL,
  `content` text COLLATE utf8_unicode_ci NOT NULL,
  `sender` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `processed` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `message_out`
--

CREATE TABLE `message_out` (
  `id` bigint(20) NOT NULL,
  `recipient` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `content` text COLLATE utf8_unicode_ci NOT NULL,
  `sent` tinyint(1) NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `sent_at` datetime NOT NULL,
  `processor` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `priority` tinyint(1) NOT NULL,
  `error_count` int(10) NOT NULL,
  `reference_id` bigint(20) NOT NULL,
  `provider_id` bigint(20) NOT NULL,
  `sent_history_id` bigint(20) NOT NULL,
  `sent_history_table` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `new_id`
--

CREATE TABLE `new_id` (
  `id` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `new_id`
--

INSERT INTO `new_id` (`id`, `created_at`) VALUES
(1000000, '2017-08-16 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `parent_id` bigint(20) NOT NULL,
  `file_path` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `priority` int(10) NOT NULL,
  `icon_class_name` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `site` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `description`, `type`, `parent_id`, `file_path`, `priority`, `icon_class_name`, `disabled`, `site`, `created_at`, `updated_at`) VALUES
(1, 'Web Services', 'Check webservices stuff', 'Menu', 0, '', 1, 'zmdi-collection-text', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(2, 'Web Services Log', 'Check Web Services Log', 'Sub Menu', 1, 'webServices.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(3, 'API List', 'Check API List', 'Sub Menu', 1, 'apiList.php', 2, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(4, 'Test API', 'Engineer Stuff', 'Sub Menu', 1, 'testApi.php', 3, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(5, 'Messages', 'Check Messages', 'Menu', 0, '', 3, 'zmdi-email', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(6, 'Message Code', 'Check Message Code', 'Sub Menu', 5, 'messageCode.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(7, 'Message Assigned', 'Check Message Assigned', 'Sub Menu', 5, 'messageAssigned.php', 2, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(8, 'Message Sent', 'Check Message Sent', 'Sub Menu', 5, 'messageSent.php', 4, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(9, 'Message Error', 'Check Message Error', 'Sub Menu', 5, 'messageError.php', 6, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(10, 'Test Message', 'Test Send Message', 'Sub Menu', 5, 'TestMessage.php', 7, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(11, 'Users', 'Super Admin User', 'Menu', 0, '', 6, 'zmdi-accounts-alt', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(12, 'User List', 'All Super Admin User List', 'Sub Menu', 11, 'user.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(13, 'Modules', 'All Modules', 'Menu', 0, '', 11, 'zmdi-format-align-justify', 1, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(14, 'Super Admin', 'Modules', 'Sub Menu', 13, 'modulesSuperAdmin.php', 1, '', 1, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(15, 'Settings', 'Page Settings', 'Menu', 0, '', 12, 'zmdi-settings', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(16, 'Upgrades', 'Check Upgrades stuff', 'Menu', 0, '', 10, 'zmdi-dock', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(17, 'New Upgrades', 'Check for new upgrades', 'Sub Menu', 16, 'upgradeNew.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(18, 'Upgrades History', 'Upgrades history list', 'Sub Menu', 16, 'upgradeHistory.php', 2, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(19, 'Api Parameters', 'Api parameters List', 'Sub Menu', 1, 'apiParamList.php', 4, '', 1, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(20, 'Providers', 'All Providers', 'Menu', 0, '', 9, 'zmdi-account-box-phone', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(21, 'Providers List', 'Providers List', 'Sub Menu', 20, 'providers.php', 0, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(22, 'Roles', 'Roles', 'Menu', 0, '', 7, 'zmdi-assignment-account', 1, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(23, 'Roles List', 'Roles List', 'Sub Menu', 11, 'role.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(24, 'Role Permissions', 'Role Permissions', 'Sub Menu', 11, 'rolePermissionsList.php', 2, '', 1, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(25, 'Permissions', 'Permissions', 'Sub Menu', 15, 'permission.php', 2, 'zmdi-accounts-list', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(26, 'Languages', 'Languages', 'Menu', 0, '', 8, 'zmdi-translate', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(27, 'Language List', 'Language Listing', 'Sub Menu', 26, 'languageList.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(28, 'Language Translation', 'Check Language Translations', 'Sub Menu', 26, 'languageCode.php', 2, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(29, 'New Role', 'Add new role', 'Page', 23, 'newRole.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(30, 'Edit Role', 'Edit role', 'Page', 23, 'editRole.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(31, 'New User', 'Add new user', 'Page', 12, 'newUser.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(32, 'Edit User', 'Edit user', 'Page', 12, 'editUser.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(33, 'New API', 'Add new api', 'Page', 3, 'newApi.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(34, 'Edit API', 'Edit api', 'Page', 3, 'editApi.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(35, 'New API Parameter', 'Add new API parameters', 'Page', 19, 'newApiParam.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(36, 'Edit API Parameter', 'Edit API parameters', 'Page', 19, 'editApiParam.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(37, 'New Message Code', 'Add new message code', 'Page', 6, 'newMessageCode.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(38, 'Edit Message Code', 'Edit message code', 'Page', 6, 'editMessageCode.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(39, 'New Message Assigned', 'Add new message assigned', 'Page', 7, 'newMessageAssigned.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(40, 'Edit Message Assigned', 'Edit message assigned', 'Page', 7, 'editMessageAssigned.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(41, 'New Language', 'Add new language', 'Page', 27, 'newLanguage.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(42, 'Edit Language', 'Edit language', 'Page', 27, 'editLanguage.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(43, 'New Language Code', 'Add new language code', 'Page', 28, 'newLanguageCode.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(44, 'Edit Language Code', 'Edit language code', 'Page', 28, 'editLanguageCode.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(45, 'New Setting', 'Add new setting', 'Page', 75, 'newSetting.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(46, 'Edit Setting', 'Edit setting', 'Page', 75, 'editSetting.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(47, 'New Provider', 'Add new provider', 'Page', 21, 'newProvider.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(48, 'Edit Provider', 'Edit provider', 'Page', 21, 'editProvider.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(49, 'Edit Role Permission', 'Edit role permission', 'Page', 24, 'editRolePermission.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(50, 'Import Translations', 'Import Translations', 'Sub Menu', 26, 'imports.php', 3, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(51, 'New Permission', 'Add new permission', 'Page', 25, 'newPermission.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(52, 'Edit Permission', 'Edit permission', 'Page', 25, 'editPermission.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(53, 'Clients', 'Clients', 'Menu', 0, '', 4, 'zmdi-account-box', 0, 'SuperAdmin', '2017-07-31 00:00:00', '2017-07-31 00:00:00'),
(54, 'Internal Accounts', 'Internal Accounts list', 'Sub Menu', 53, 'internalAccounts.php', 2, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(55, 'New Internal Account', 'Add New Internal Account', 'Page', 53, 'newInternalAccount.php', 1, '', 0, 'SuperAdmin', '2017-07-31 00:00:00', '2017-07-31 00:00:00'),
(56, 'Edit Interal Account', 'Edit Interal Account', 'Page', 53, 'editInternalAccount.php', 1, '', 0, 'SuperAdmin', '2017-07-31 00:00:00', '2017-07-31 00:00:00'),
(57, 'Journal', 'Journal', 'Menu', 0, '', 13, 'zmdi-folder-person', 0, 'SuperAdmin', '2017-07-31 00:00:00', '2017-07-31 00:00:00'),
(58, 'Journal Tables', 'Journal Tables list', 'Sub Menu', 57, 'journalTables.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(59, 'New Journal Table', 'Add New Journal Table', 'Page', 58, 'newJournalTable.php', 1, '', 0, 'SuperAdmin', '2017-07-31 00:00:00', '2017-07-31 00:00:00'),
(60, 'Edit Journal Table', 'Edit Journal Table', 'Page', 58, 'editJournalTable.php', 1, '', 0, 'SuperAdmin', '2017-07-31 00:00:00', '2017-07-31 00:00:00'),
(61, 'Countries', 'Countries', 'Menu', 0, '', 14, 'zmdi-globe', 0, 'SuperAdmin', '2017-07-31 00:00:00', '2017-07-31 00:00:00'),
(62, 'Countries List', 'Countries list', 'Sub Menu', 61, 'countries.php', 1, '', 0, 'SuperAdmin', '2017-07-28 00:00:00', '2017-07-28 00:00:00'),
(63, 'New Country', 'Add New Country', 'Page', 62, 'newCountry.php', 1, '', 0, 'SuperAdmin', '2017-07-31 00:00:00', '2017-07-31 00:00:00'),
(64, 'Edit Country', 'Edit Country', 'Page', 62, 'editCountry.php', 1, '', 0, 'SuperAdmin', '2017-07-31 00:00:00', '2017-07-31 00:00:00'),
(65, 'Credits', 'Credits menu', 'Menu', 0, '', 15, 'zmdi-money-box', 0, 'SuperAdmin', '2017-08-02 00:00:00', '2017-08-02 00:00:00'),
(66, 'Credits List', 'Credits listing', 'Sub Menu', 65, 'credit.php', 1, '', 0, 'SuperAdmin', '2017-08-02 00:00:00', '2017-08-02 00:00:00'),
(67, 'New Credit', 'Add New Credit', 'Page', 66, 'newCredit.php', 1, '', 0, 'SuperAdmin', '2017-08-02 00:00:00', '2017-08-02 00:00:00'),
(68, 'Edit Credit', 'Edit Credit', 'Page', 66, 'editCredit.php', 1, '', 0, 'SuperAdmin', '2017-08-02 00:00:00', '2017-08-02 00:00:00'),
(69, 'Credits Setting', 'Credits setting listing', 'Sub Menu', 65, 'creditSetting.php', 2, '', 1, 'SuperAdmin', '2017-08-02 00:00:00', '2017-08-02 00:00:00'),
(70, 'Edit Credit Setting', 'Edit Credit', 'Page', 66, 'editCreditSetting.php', 1, '', 0, 'SuperAdmin', '2017-08-02 00:00:00', '2017-08-02 00:00:00'),
(71, 'Clients List', 'Clients listing', 'Sub Menu', 53, 'client.php', 1, '', 0, 'SuperAdmin', '2017-08-07 00:00:00', '2017-08-07 00:00:00'),
(72, 'Client Details', 'Client details', 'Page', 71, 'clientDetails.php', 1, '', 0, 'SuperAdmin', '2017-08-07 00:00:00', '2017-08-07 00:00:00'),
(73, 'Client Setting', 'Client Setting', 'Page', 71, 'clientSetting.php', 1, '', 0, 'SuperAdmin', '2017-08-08 00:00:00', '2017-08-08 00:00:00'),
(74, 'Message Queue', 'Check Message Queue', 'Sub Menu', 5, 'messageQueue.php', 3, '', 0, 'SuperAdmin', '2017-08-14 14:14:47', '0000-00-00 00:00:00'),
(75, 'System Setting', 'System setting listing', 'Sub Menu', 15, 'settingsList.php', 1, '', 0, 'SuperAdmin', '2017-08-16 11:26:00', '0000-00-00 00:00:00'),
(76, 'System', 'System', 'Menu', 0, '', 18, 'fa fa-laptop', 0, 'SuperAdmin', '2017-08-02 00:00:00', '2017-08-02 00:00:00'),
(77, 'System Information', 'System Information', 'Sub Menu', 76, 'systemInformation.php', 1, '', 0, 'SuperAdmin', '2017-08-02 00:00:00', '2017-08-02 00:00:00'),
(78, 'View System Information', 'View System Information', 'Page', 77, 'viewSystemInformation.php', 1, '', 0, 'SuperAdmin', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(79, 'API Parameters List', '', 'Page', 19, 'apiParametersList.php', 1, '', 0, 'SuperAdmin', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(80, 'Admins', 'Admins', 'Menu', 0, '', 5, 'zmdi-account-circle', 0, 'SuperAdmin', '2017-08-18 10:57:15', '0000-00-00 00:00:00'),
(81, 'Admin List', 'Admin listing', 'Sub Menu', 80, 'admin.php', 1, '', 0, 'SuperAdmin', '2017-08-18 10:57:15', '0000-00-00 00:00:00'),
(82, 'New Admin', 'Add new admin', 'Page', 81, 'newAdmin.php', 1, '', 0, 'SuperAdmin', '2017-08-18 10:57:15', '0000-00-00 00:00:00'),
(83, 'Edit Admin', 'Edit admin', 'Page', 81, 'editAdmin.php', 1, '', 0, 'SuperAdmin', '2017-08-18 10:57:15', '0000-00-00 00:00:00'),
(84, 'Client Sponsor Tree', 'Client Sponsor Tree', 'Page', 71, 'sponsorTree.php', 1, '', 0, 'SuperAdmin', '2017-08-24 19:26:32', '0000-00-00 00:00:00'),
(85, 'Message In', 'Message in listing', 'Sub Menu', 5, 'messageIn.php', 5, '', 0, 'SuperAdmin', '2017-11-21 12:53:48', '2017-11-21 12:53:48'),
(86, 'Api Sample', 'Api Sample', 'Page', 3, 'apiSample.php', 1, '', 0, 'SuperAdmin', '2018-01-04 11:45:45', '2018-01-04 11:45:45'),
(87, 'Activity', 'Check activity stuff', 'Menu', 0, '', 2, 'zmdi-view-agenda', 0, 'SuperAdmin', '2018-01-04 11:45:45', '2018-01-04 11:45:45'),
(88, 'Activity Log', 'Check activity log', 'Sub Menu', 87, 'activityLog.php', 1, '', 0, 'SuperAdmin', '2018-01-04 11:45:45', '2018-01-04 11:45:45');

-- --------------------------------------------------------

--
-- Table structure for table `processes`
--

CREATE TABLE `processes` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `output_path` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `process_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `arg1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `arg2` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `arg3` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `arg4` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `arg5` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `processes`
--

INSERT INTO `processes` (`id`, `name`, `file_path`, `output_path`, `process_id`, `disabled`, `arg1`, `arg2`, `arg3`, `arg4`, `arg5`, `created_at`, `updated_at`) VALUES
(1, 'processMessageOut-A', 'processMessageOut.php', 'processMessageOut-A.log', '', 0, 'A', '5', '5', '', '', '2017-11-21 12:24:09', '2017-11-21 12:24:09'),
(2, 'processMessageOut-B', 'processMessageOut.php', 'processMessageOut-B.log', '', 0, 'B', '5', '5', '', '', '2017-11-21 12:24:09', '2017-11-21 12:24:09'),
(3, 'processMessageOut-C', 'processMessageOut.php', 'processMessageOut-C.log', '', 0, 'C', '5', '5', '', '', '2017-11-21 12:24:09', '2017-11-21 12:24:09'),
(4, 'processMessageOut-D', 'processMessageOut.php', 'processMessageOut-D.log', '', 0, 'D', '5', '5', '', '', '2017-11-21 12:24:09', '2017-11-21 12:24:09'),
(5, 'processMessageOut-E', 'processMessageOut.php', 'processMessageOut-E.log', '', 0, 'E', '5', '5', '', '', '2017-11-21 12:24:09', '2017-11-21 12:24:09');

-- --------------------------------------------------------

--
-- Table structure for table `provider`
--

CREATE TABLE `provider` (
  `id` bigint(20) NOT NULL,
  `company` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `api_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `priority` tinyint(1) NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `default_sender` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `url1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `url2` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `remark` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `currency` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `balance` decimal(20,4) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `provider`
--

INSERT INTO `provider` (`id`, `company`, `name`, `username`, `password`, `api_key`, `type`, `priority`, `disabled`, `deleted`, `default_sender`, `url1`, `url2`, `remark`, `currency`, `balance`, `created_at`, `updated_at`) VALUES
(1, 'ekomas', 'xun', '358', '', 'ekomas1267', 'notification', 0, 0, 0, '', 'https://www.xunm.net/webservice.php', '', '', '', '0.0000', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(2, 'T2II Support', 'email', '', '', '', 'notification', 0, 0, 0, '', '', '', '', '', '0.0000', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(3, 'ekomas', 'sms', 'support@superd.com', '8387', '', 'notification', 0, 0, 0, '', 'https://www.sms123.net/xmlgateway.php', '', '', '', '0.0000', '0000-00-00 00:00:00', '0000-00-00 00:00:00'),
(4, 'T2II Support', 'mail', 'root@t2ii', '', '', 'notification', 0, 0, 0, '', '', '', '', '', '0.0000', '0000-00-00 00:00:00', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `provider_setting`
--

CREATE TABLE `provider_setting` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `provider_id` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `queue`
--

CREATE TABLE `queue` (
  `id` bigint(20) NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `json_string` text COLLATE utf8_unicode_ci NOT NULL,
  `processed` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `site` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `disabled`, `site`, `deleted`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'This role will be granted full access into the system', 0, 'SuperAdmin', 0, '0000-00-00 00:00:00', '0000-00-00 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `roles_permission`
--

CREATE TABLE `roles_permission` (
  `id` bigint(20) NOT NULL,
  `role_id` bigint(20) NOT NULL,
  `permission_id` bigint(20) NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `roles_permission`
--

INSERT INTO `roles_permission` (`id`, `role_id`, `permission_id`, `disabled`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(2, 1, 2, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(3, 1, 3, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(4, 1, 4, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(5, 1, 5, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(6, 1, 6, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(7, 1, 7, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(8, 1, 8, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(9, 1, 9, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(10, 1, 10, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(11, 1, 11, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(12, 1, 12, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(13, 1, 13, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(14, 1, 14, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(15, 1, 15, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(16, 1, 16, 0, '2017-06-30 00:00:00', '2017-06-30 00:00:00'),
(17, 1, 17, 0, '2017-07-10 00:00:00', '2017-07-10 00:00:00'),
(18, 1, 18, 0, '2017-07-10 00:00:00', '2017-07-10 00:00:00'),
(19, 1, 19, 0, '2017-07-10 00:00:00', '2017-07-10 00:00:00'),
(20, 1, 86, 0, '2018-01-04 11:45:45', '2018-01-04 11:45:45'),
(21, 1, 87, 0, '2018-01-04 11:45:45', '2018-01-04 11:45:45'),
(22, 1, 88, 0, '2018-01-04 11:45:45', '2018-01-04 11:45:45');

-- --------------------------------------------------------

--
-- Table structure for table `sent_history`
--

CREATE TABLE `sent_history` (
  `id` bigint(20) NOT NULL,
  `recipient` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `subject` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `content` text COLLATE utf8_unicode_ci NOT NULL,
  `scheduled_at` datetime NOT NULL,
  `sent_at` datetime NOT NULL,
  `processor` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `error_count` int(10) NOT NULL,
  `reference_id` bigint(20) NOT NULL,
  `provider_id` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `server_status_data`
--

CREATE TABLE `server_status_data` (
  `id` bigint(20) NOT NULL,
  `server_id` bigint(20) NOT NULL,
  `cpu_load` decimal(20,4) NOT NULL,
  `cpu_idle` decimal(20,4) NOT NULL,
  `memory_used` int(10) NOT NULL,
  `swap_used` int(10) NOT NULL,
  `disk_used` int(10) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `server_status_summary`
--

CREATE TABLE `server_status_summary` (
  `id` bigint(20) NOT NULL,
  `server_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `server_ip` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `is_local_machine` tinyint(1) NOT NULL,
  `release` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `total_cpu` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `total_memory` int(10) NOT NULL,
  `total_swap` int(10) NOT NULL,
  `disk_size` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `disk_available` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `special_characters`
--

CREATE TABLE `special_characters` (
  `id` bigint(20) NOT NULL,
  `value` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `special_characters`
--

INSERT INTO `special_characters` (`id`, `value`, `disabled`) VALUES
(1, '!', 1),
(2, '@', 1),
(3, '#', 1),
(4, '$', 1),
(5, '%', 1),
(6, '^', 1),
(7, '&', 1),
(8, '*', 1),
(9, '(', 1),
(10, ')', 1);

-- --------------------------------------------------------

--
-- Table structure for table `state`
--

CREATE TABLE `state` (
  `id` bigint(20) NOT NULL,
  `country_id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `translation_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` bigint(20) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `value` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `reference` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `description` text COLLATE utf8_unicode_ci NOT NULL,
  `module` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `name`, `value`, `type`, `reference`, `description`, `module`) VALUES
(1, 'processOutGoingEnableFlag', '1', '', '', '', 'Standard Platform'),
(2, 'maxAPIProcessingTime', '5', '', '', 'Default value for API processing time.', 'Standard Platform'),
(3, 'numberOfQueries', '10', '', '', 'Default value for the number of queries that an API can execute.', 'Standard Platform'),
(4, 'superAdminPageLimit', '25', '', '', 'Default setting for super admin area\'s listing page limit.', 'Standard Platform'),
(5, 'checkSystemHealthInterval', '3600', '', '', '', 'Standard Platform'),
(6, 'lowDiskSpacePercentage', '80', '', '', '', 'Standard Platform'),
(7, 'backupPath', '/root/backup/', '', '', 'Default backup path for mysql dump', 'Standard Platform'),
(8, 'superAdminTimeout', '3600', '', '', '', 'Standard Platform'),
(9, 'memberLanguagePath', '', '', '', 'Define the path of the language folder in member area', 'Standard Platform'),
(10, 'adminLanguagePath', '', '', '', 'Define the path of the language folder in admin area', 'Standard Platform'),
(11, 'isLocalhost', '', '', '', 'Set 1 if your machine is running on local, 0 for server', 'Standard Platform'),
(12, 'frontendServerIP', '', '', '', 'Language file will be automatically copied to the frontend server based on this IP.', 'Standard Platform'),
(13, 'superAdminPasswordEncryption', 'bcrypt', '', '', 'mysql/bcrypt password encryption.', 'Standard Platform'),
(15, 'systemMaintenanceFlag', '0', '', '', '', 'Standard Platform'),
(16, 'maxPlacementPositions', '2', '', '', 'Default value for the number of position in placement tree.', 'Standard Platform'),
(17, 'closingPeriod', '3', '', '', 'Closing day difference from the exact day. Set number of days.', 'Standard Platform'),
(18, 'adminTimeout', '3600', '', '', '', 'Standard Platform'),
(19, 'memberTimeout', '3600', '', '', '', 'Standard Platform'),
(20, 'systemDateFormat', 'd/m/Y', '', '', 'Set default date format', 'Standard Platform'),
(21, 'systemDateTimeFormat', 'd/m/Y h:i:s A', '', '', 'Set default date time format', 'Standard Platform'),
(22, 'timezoneUsage', '0', '', '', 'Set 0 to turn off, 1 to turn on for different country timezone', 'Standard Platform'),
(23, 'adminPasswordEncryption', 'bcrypt', '', '', 'mysql/bcrypt password encryption.', 'Standard Platform'),
(24, 'memberPasswordEncryption', 'bcrypt', '', '', 'mysql/bcrypt password encryption.', 'Standard Platform');

-- --------------------------------------------------------

--
-- Table structure for table `system_status`
--

CREATE TABLE `system_status` (
  `id` bigint(20) NOT NULL,
  `process_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `updated_at` datetime NOT NULL,
  `last_notification` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tree_placement`
--

CREATE TABLE `tree_placement` (
  `id` bigint(20) NOT NULL,
  `client_id` bigint(20) NOT NULL,
  `client_unit` int(10) NOT NULL,
  `client_position` tinyint(1) NOT NULL,
  `upline_id` bigint(20) NOT NULL,
  `upline_unit` int(10) NOT NULL,
  `upline_position` tinyint(1) NOT NULL,
  `level` bigint(20) NOT NULL,
  `trace_key` longtext COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `tree_placement`
--

INSERT INTO `tree_placement` (`id`, `client_id`, `client_unit`, `client_position`, `upline_id`, `upline_unit`, `upline_position`, `level`, `trace_key`) VALUES
(1, 1000000, 1, 0, 0, 0, 0, 0, '1000000-1');

-- --------------------------------------------------------

--
-- Table structure for table `tree_sponsor`
--

CREATE TABLE `tree_sponsor` (
  `id` bigint(20) NOT NULL,
  `client_id` bigint(20) NOT NULL,
  `upline_id` bigint(20) NOT NULL,
  `level` bigint(20) NOT NULL,
  `trace_key` longtext COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `tree_sponsor`
--

INSERT INTO `tree_sponsor` (`id`, `client_id`, `upline_id`, `level`, `trace_key`) VALUES
(1, 1000000, 0, 0, '1000000');

-- --------------------------------------------------------

--
-- Table structure for table `uploads`
--

CREATE TABLE `uploads` (
  `id` bigint(20) NOT NULL,
  `data` longblob NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `role_id` bigint(20) NOT NULL,
  `disabled` tinyint(1) NOT NULL,
  `deleted` tinyint(1) NOT NULL,
  `last_login` datetime NOT NULL,
  `session_id` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `name`, `email`, `phone`, `password`, `role_id`, `disabled`, `deleted`, `last_login`, `session_id`, `created_at`, `updated_at`) VALUES
(1, 'superadmin', 'superadmin', 'superadmin', '', '$2y$10$z9bslhjLLMN2sLz7tO9QQeboiIToDbrzwBvHwhQEApLywXzpS67Dm', 1, 0, 0, '2018-03-15 15:36:11', '7f7656e0e36398dc0cbadd5294318018', '2017-07-17 00:00:00', '2017-07-17 21:41:18'),
(2, 'charles', 'charles', 'charles@intermecnetwork.com', '', '$2y$10$.rgtWtC/2yXGUa90n5VD0.VxuOQ59QQez5ZasOPxl56E4rQaSRHV2', 1, 0, 0, '2018-05-15 01:20:22', '03fc8099a9e715f4cbd4e3219aece5f3', '2017-07-18 16:11:40', '2018-05-15 01:20:22'),
(3, 'eng', 'eng', 'eng@ttwoweb.com', '', '$2y$10$vrcvqiSfjzUm1MIOEmFzkOKDTSKbXtzE98FslesheSHcXLkRtCWpu', 1, 0, 0, '2017-11-21 16:22:32', '9bdb7a3772733444f6069cd5995bc43f', '2017-07-18 16:14:12', '2017-07-18 16:14:12'),
(4, 'dennis', 'dennis', 'dennis@ekomas.com', '', '$2y$10$eedciLAa2IXOfAGtbx3zF.Qz6jd8HFSfL3O0wTcRR2Ny0BGegKsSq', 1, 0, 0, '0000-00-00 00:00:00', '', '2017-07-18 16:14:33', '2017-07-18 16:14:33'),
(5, 'jasper', 'jasper', 'jasper@ekomas.com', '', '$2y$10$rDZ4Kmt.cRgXi4NL7e6IEe2Dqh3MIk8CF1RKCp/oldRLLnxMhQn.W', 1, 0, 0, '0000-00-00 00:00:00', '68948467baa5fb9132b56434f4884c8c', '2017-07-18 16:41:19', '2017-07-18 16:41:19'),
(6, 'jinqugan', 'jinqugan', 'jinqugan', '', '$2y$10$LZplti8Oc7zAI4/JjWuyreEMa7mrP77.gPECFmgvk3FNilxsBC6pO', 1, 0, 0, '0000-00-00 00:00:00', '', '2017-08-24 23:02:17', '2017-08-24 23:02:17');

-- --------------------------------------------------------

--
-- Table structure for table `web_services`
--

CREATE TABLE `web_services` (
  `id` bigint(20) NOT NULL,
  `client_id` bigint(20) NOT NULL,
  `client_username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `command` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `data_in` longtext COLLATE utf8_unicode_ci NOT NULL,
  `data_out` longtext COLLATE utf8_unicode_ci NOT NULL,
  `source` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `source_version` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `site` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(25) COLLATE utf8_unicode_ci NOT NULL,
  `completed_at` datetime NOT NULL,
  `duration` int(10) NOT NULL,
  `no_of_queries` int(10) NOT NULL,
  `ip` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `user_agent` text COLLATE utf8_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `acc_closing`
--
ALTER TABLE `acc_closing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cid` (`client_id`);

--
-- Indexes for table `acc_credit`
--
ALTER TABLE `acc_credit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subject` (`subject`),
  ADD KEY `type` (`type`),
  ADD KEY `getBalance` (`account_id`,`subject`(150),`type`(50)),
  ADD KEY `receiverID` (`receiver_id`),
  ADD KEY `belongID` (`belong_id`),
  ADD KEY `referenceID` (`reference_id`),
  ADD KEY `batchID` (`batch_id`),
  ADD KEY `deleted` (`deleted`),
  ADD KEY `createdAt` (`created_at`);

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `title` (`title`),
  ADD KEY `creator_type` (`creator_type`);

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rid` (`role_id`,`username`),
  ADD KEY `username` (`username`),
  ADD KEY `name` (`name`),
  ADD KEY `disabled` (`disabled`),
  ADD KEY `suspended` (`suspended`),
  ADD KEY `roleID` (`role_id`),
  ADD KEY `lastLogin` (`last_login`),
  ADD KEY `lastActivity` (`last_activity`),
  ADD KEY `deleted` (`deleted`),
  ADD KEY `createdAt` (`created_at`);

--
-- Indexes for table `api`
--
ALTER TABLE `api`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cmd` (`command`,`duration`);

--
-- Indexes for table `api_params`
--
ALTER TABLE `api_params`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ap` (`api_id`);

--
-- Indexes for table `api_sample`
--
ALTER TABLE `api_sample`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `audit`
--
ALTER TABLE `audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `history` (`row_id`,`column_name`),
  ADD KEY `method` (`method`),
  ADD KEY `createdBy` (`created_by`),
  ADD KEY `createdType` (`created_type`),
  ADD KEY `createdAt` (`created_at`),
  ADD KEY `updatedAt` (`updated_at`);

--
-- Indexes for table `city`
--
ALTER TABLE `city`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stateID` (`state_id`),
  ADD KEY `name` (`name`),
  ADD KEY `disabled` (`disabled`);

--
-- Indexes for table `cleanup_table`
--
ALTER TABLE `cleanup_table`
  ADD PRIMARY KEY (`id`),
  ADD KEY `table_name` (`table_name`),
  ADD KEY `disabled` (`disabled`),
  ADD KEY `table_type` (`table_type`),
  ADD KEY `backup` (`backup`);

--
-- Indexes for table `client`
--
ALTER TABLE `client`
  ADD PRIMARY KEY (`id`),
  ADD KEY `username` (`username`),
  ADD KEY `name` (`name`),
  ADD KEY `type` (`type`),
  ADD KEY `email` (`email`),
  ADD KEY `phone` (`phone`),
  ADD KEY `countryID` (`country_id`),
  ADD KEY `sponsorID` (`sponsor_id`),
  ADD KEY `placementID` (`placement_id`),
  ADD KEY `activated` (`activated`),
  ADD KEY `disabled` (`disabled`),
  ADD KEY `suspended` (`suspended`),
  ADD KEY `freezed` (`freezed`),
  ADD KEY `createdAt` (`created_at`),
  ADD KEY `lastLogin` (`last_login`),
  ADD KEY `lastActivity` (`last_activity`);

--
-- Indexes for table `client_setting`
--
ALTER TABLE `client_setting`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cs_name` (`name`),
  ADD KEY `clientSetting` (`client_id`,`name`),
  ADD KEY `type` (`type`),
  ADD KEY `reference` (`reference`);

--
-- Indexes for table `country`
--
ALTER TABLE `country`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_country_name` (`name`,`country_code`);

--
-- Indexes for table `county`
--
ALTER TABLE `county`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cityID` (`city_id`),
  ADD KEY `name` (`name`),
  ADD KEY `disabled` (`disabled`);

--
-- Indexes for table `credit`
--
ALTER TABLE `credit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_credit_name` (`name`,`priority`);

--
-- Indexes for table `credit_setting`
--
ALTER TABLE `credit_setting`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cs_id` (`credit_id`,`name`),
  ADD KEY `name` (`name`),
  ADD KEY `type` (`type`),
  ADD KEY `reference` (`reference`);

--
-- Indexes for table `credit_setting_preset`
--
ALTER TABLE `credit_setting_preset`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_csp_name` (`name`);

--
-- Indexes for table `enumerators`
--
ALTER TABLE `enumerators`
  ADD PRIMARY KEY (`id`),
  ADD KEY `enumTypes` (`type`,`priority`),
  ADD KEY `name` (`name`),
  ADD KEY `deleted` (`deleted`),
  ADD KEY `priority` (`priority`);

--
-- Indexes for table `journal_tables`
--
ALTER TABLE `journal_tables`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_jt_tblname` (`table_name`);

--
-- Indexes for table `languages`
--
ALTER TABLE `languages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `language_import`
--
ALTER TABLE `language_import`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `language_translation`
--
ALTER TABLE `language_translation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_lt_code` (`code`);

--
-- Indexes for table `log_upgrade`
--
ALTER TABLE `log_upgrade`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `message_assigned`
--
ALTER TABLE `message_assigned`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient` (`recipient`),
  ADD KEY `type` (`type`),
  ADD KEY `code` (`code`,`recipient`,`type`) USING BTREE,
  ADD KEY `idx_ma_code` (`code`);

--
-- Indexes for table `message_code`
--
ALTER TABLE `message_code`
  ADD PRIMARY KEY (`id`),
  ADD KEY `code` (`code`),
  ADD KEY `title` (`title`),
  ADD KEY `deleted` (`deleted`),
  ADD KEY `idx_mc_code` (`code`);

--
-- Indexes for table `message_error`
--
ALTER TABLE `message_error`
  ADD PRIMARY KEY (`id`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `error_code` (`error_code`),
  ADD KEY `processor` (`processor`),
  ADD KEY `idx_me_code` (`message_id`,`error_code`);

--
-- Indexes for table `message_in`
--
ALTER TABLE `message_in`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mi_code` (`provider_id`,`processed`);

--
-- Indexes for table `message_out`
--
ALTER TABLE `message_out`
  ADD PRIMARY KEY (`id`),
  ADD KEY `scheduled_time` (`scheduled_at`),
  ADD KEY `priority` (`priority`),
  ADD KEY `recipient` (`recipient`),
  ADD KEY `type` (`type`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `error_count` (`error_count`),
  ADD KEY `reference_id` (`reference_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `processor` (`processor`,`sent`,`error_count`) USING BTREE,
  ADD KEY `idx_mo_fields` (`created_at`,`recipient`);

--
-- Indexes for table `new_id`
--
ALTER TABLE `new_id`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pm_name` (`name`),
  ADD KEY `type` (`type`),
  ADD KEY `parentID` (`parent_id`),
  ADD KEY `priority` (`priority`),
  ADD KEY `disabled` (`disabled`),
  ADD KEY `site` (`site`);

--
-- Indexes for table `processes`
--
ALTER TABLE `processes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `provider`
--
ALTER TABLE `provider`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company` (`company`),
  ADD KEY `name` (`name`),
  ADD KEY `username` (`username`),
  ADD KEY `disabled` (`disabled`),
  ADD KEY `deleted` (`deleted`),
  ADD KEY `idx_pr_name` (`name`);

--
-- Indexes for table `provider_setting`
--
ALTER TABLE `provider_setting`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prs_name` (`name`,`provider_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_roles_name` (`name`),
  ADD KEY `site` (`site`),
  ADD KEY `disabled` (`disabled`),
  ADD KEY `deleted` (`deleted`);

--
-- Indexes for table `roles_permission`
--
ALTER TABLE `roles_permission`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rp_rid` (`role_id`),
  ADD KEY `permissionID` (`permission_id`),
  ADD KEY `disabled` (`disabled`),
  ADD KEY `createdAt` (`created_at`);

--
-- Indexes for table `sent_history`
--
ALTER TABLE `sent_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `recipient` (`recipient`),
  ADD KEY `type` (`type`),
  ADD KEY `error_count` (`error_count`),
  ADD KEY `sent_at` (`sent_at`),
  ADD KEY `subject` (`subject`),
  ADD KEY `reference_id` (`reference_id`),
  ADD KEY `provider_id` (`provider_id`),
  ADD KEY `idx_sh_recipient` (`recipient`);

--
-- Indexes for table `server_status_data`
--
ALTER TABLE `server_status_data`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `server_status_summary`
--
ALTER TABLE `server_status_summary`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `special_characters`
--
ALTER TABLE `special_characters`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `state`
--
ALTER TABLE `state`
  ADD PRIMARY KEY (`id`),
  ADD KEY `countryID` (`country_id`),
  ADD KEY `disabled` (`disabled`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ss_name` (`name`),
  ADD KEY `module` (`module`);

--
-- Indexes for table `system_status`
--
ALTER TABLE `system_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `process_name` (`process_name`);

--
-- Indexes for table `tree_placement`
--
ALTER TABLE `tree_placement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `clientID` (`client_id`),
  ADD KEY `uplineID` (`upline_id`),
  ADD KEY `level` (`level`);

--
-- Indexes for table `tree_sponsor`
--
ALTER TABLE `tree_sponsor`
  ADD PRIMARY KEY (`id`),
  ADD KEY `clientID` (`client_id`),
  ADD KEY `uplineID` (`upline_id`),
  ADD KEY `level` (`level`);

--
-- Indexes for table `uploads`
--
ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_us_name` (`name`,`role_id`),
  ADD KEY `username` (`username`),
  ADD KEY `roleID` (`role_id`),
  ADD KEY `disabled` (`disabled`),
  ADD KEY `deleted` (`deleted`),
  ADD KEY `lastLogin` (`last_login`),
  ADD KEY `createdAt` (`created_at`),
  ADD KEY `updatedAt` (`updated_at`);

--
-- Indexes for table `web_services`
--
ALTER TABLE `web_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_wb_cmd` (`command`,`created_at`),
  ADD KEY `clientRequest` (`client_id`,`command`),
  ADD KEY `clientUsername` (`client_username`),
  ADD KEY `source` (`source`),
  ADD KEY `type` (`type`),
  ADD KEY `site` (`site`),
  ADD KEY `status` (`status`),
  ADD KEY `completedAt` (`completed_at`),
  ADD KEY `duration` (`duration`),
  ADD KEY `noOfQueries` (`no_of_queries`),
  ADD KEY `ip` (`ip`),
  ADD KEY `createdAt` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `acc_closing`
--
ALTER TABLE `acc_closing`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `acc_credit`
--
ALTER TABLE `acc_credit`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `api`
--
ALTER TABLE `api`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `api_params`
--
ALTER TABLE `api_params`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT for table `api_sample`
--
ALTER TABLE `api_sample`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit`
--
ALTER TABLE `audit`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `city`
--
ALTER TABLE `city`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cleanup_table`
--
ALTER TABLE `cleanup_table`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `client`
--
ALTER TABLE `client`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000001;

--
-- AUTO_INCREMENT for table `client_setting`
--
ALTER TABLE `client_setting`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `country`
--
ALTER TABLE `country`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=255;

--
-- AUTO_INCREMENT for table `county`
--
ALTER TABLE `county`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `credit`
--
ALTER TABLE `credit`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `credit_setting`
--
ALTER TABLE `credit_setting`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enumerators`
--
ALTER TABLE `enumerators`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_tables`
--
ALTER TABLE `journal_tables`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `languages`
--
ALTER TABLE `languages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `language_import`
--
ALTER TABLE `language_import`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `language_translation`
--
ALTER TABLE `language_translation`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_upgrade`
--
ALTER TABLE `log_upgrade`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_assigned`
--
ALTER TABLE `message_assigned`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `message_code`
--
ALTER TABLE `message_code`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `message_error`
--
ALTER TABLE `message_error`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_in`
--
ALTER TABLE `message_in`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_out`
--
ALTER TABLE `message_out`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `new_id`
--
ALTER TABLE `new_id`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000001;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=89;

--
-- AUTO_INCREMENT for table `processes`
--
ALTER TABLE `processes`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `provider`
--
ALTER TABLE `provider`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roles_permission`
--
ALTER TABLE `roles_permission`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `sent_history`
--
ALTER TABLE `sent_history`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `server_status_data`
--
ALTER TABLE `server_status_data`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `server_status_summary`
--
ALTER TABLE `server_status_summary`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `special_characters`
--
ALTER TABLE `special_characters`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `state`
--
ALTER TABLE `state`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `system_status`
--
ALTER TABLE `system_status`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tree_placement`
--
ALTER TABLE `tree_placement`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tree_sponsor`
--
ALTER TABLE `tree_sponsor`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `web_services`
--
ALTER TABLE `web_services`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
