-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 03, 2026 at 12:04 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `unidar`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_actions`
--

CREATE TABLE `admin_actions` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action_type` enum('verify_user','reject_verification','ban_user','suspend_user','resolve_report','remove_listing') NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `target_listing_id` int(11) DEFAULT NULL,
  `target_report_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_actions`
--

INSERT INTO `admin_actions` (`id`, `admin_id`, `action_type`, `target_user_id`, `target_listing_id`, `target_report_id`, `notes`, `created_at`) VALUES
(1, 1, 'ban_user', NULL, NULL, NULL, 'Banned by admin', '2026-01-13 21:35:17'),
(2, 1, 'ban_user', NULL, NULL, NULL, 'PERMANENT DELETE: User removed by admin', '2026-01-13 21:35:23'),
(3, 1, 'verify_user', 2, NULL, NULL, NULL, '2026-03-25 22:45:26'),
(4, 1, 'verify_user', 18, NULL, NULL, NULL, '2026-03-27 11:19:13');

-- --------------------------------------------------------

--
-- Table structure for table `blocked_users`
--

CREATE TABLE `blocked_users` (
  `blocker_id` int(11) NOT NULL,
  `blocked_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `contract_number` varchar(50) NOT NULL,
  `contract_template_id` int(11) NOT NULL,
  `contract_content` text NOT NULL,
  `student_signature_path` varchar(500) DEFAULT NULL,
  `owner_signature_path` varchar(500) DEFAULT NULL,
  `contract_file_path` varchar(500) DEFAULT NULL,
  `status` enum('draft','pending_signature','signed_by_student','signed_by_both','completed','cancelled','active','paid') DEFAULT 'draft',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `monthly_rent` decimal(10,2) NOT NULL,
  `security_deposit` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`id`, `listing_id`, `student_id`, `owner_id`, `contract_number`, `contract_template_id`, `contract_content`, `student_signature_path`, `owner_signature_path`, `contract_file_path`, `status`, `start_date`, `end_date`, `monthly_rent`, `security_deposit`, `created_at`, `updated_at`) VALUES
(78, 23, 18, 3, '', 0, 'CONTRAT DE LOCATION RÉSIDENTIELLE\n\nUNIDAR – Plateforme de Location Étudiante\nContrat N°: 78\nDate: 27/03/2026\n\nENTRE LES SOUSSIGNÉS\n\nLE PROPRIÉTAIRE:\nNom: islem\nEmail: islembenamor1110@gmail.com\nTéléphone: N/A\n\nLE LOCATAIRE (ÉTUDIANT):\nNom: test2\nEmail: test2@university.edu\nTéléphone: N/A\nUniversité: N/A\n\nIL A ÉTÉ CONVENU CE QUI SUIT:\n\nARTICLE 1 – OBJET DU CONTRAT\nLe présent contrat porte sur la location du logement situé à:\nTunis\n\nARTICLE 2 – DURÉE\nLe présent contrat est conclu pour une durée de 9 mois,\ndu 01/03/2026 au 01/12/2026.\n\nARTICLE 3 – LOYER\nLe loyer mensuel est fixé à 1000.00 TND/mois.\nLe dépôt de garantie équivaut à 2 mois de loyer (2000 TND).\n\nARTICLE 4 – OBLIGATIONS DU LOCATAIRE\n- Payer le loyer à la date convenue\n- Entretenir le logement en bon état\n- Ne pas sous-louer sans autorisation écrite\n- Respecter le règlement intérieur de la résidence\n\nARTICLE 5 – OBLIGATIONS DU PROPRIÉTAIRE\n- Assurer la jouissance paisible du logement\n- Effectuer les réparations nécessaires\n- Délivrer un logement en bon état\n\nARTICLE 6 – RÉSILIATION\nLe contrat peut être résilié par l\'une des parties avec un préavis d\'un mois.\n\nFait en double exemplaire,\n\n  ', 'uploads/signatures/student_78_1774613663188.png', NULL, NULL, 'signed_by_student', '2026-03-01', '2026-12-01', 1000.00, 0.00, '2026-03-27 12:14:17', '2026-03-27 12:14:23');

-- --------------------------------------------------------

--
-- Table structure for table `contract_templates`
--

CREATE TABLE `contract_templates` (
  `id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `template_type` enum('standard','studio','apartment','house','shared_room') DEFAULT 'standard',
  `contract_content` text NOT NULL,
  `legal_clauses` text NOT NULL,
  `tunisia_specific_clauses` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contract_templates`
--

INSERT INTO `contract_templates` (`id`, `template_name`, `template_type`, `contract_content`, `legal_clauses`, `tunisia_specific_clauses`, `created_at`, `updated_at`, `is_active`) VALUES
(1, 'Contrat de Location Standard - Tunisie', 'standard', 'CONTRAT DE LOCATION SAISONNIÃˆRE\r\n\r\nEntre les soussignÃ©s :\r\n\r\nPROPRIÃ‰TAIRE : [OWNER_NAME]\r\nDemeurant Ã  : [OWNER_ADDRESS]\r\nTÃ©lÃ©phone : [OWNER_PHONE]\r\nCi-aprÃ¨s dÃ©nommÃ© Â« le PropriÃ©taire Â»\r\n\r\nEt\r\n\r\nLOCATAIRE : [STUDENT_NAME]\r\nÃ‰tudiant(e) Ã  : [UNIVERSITY_NAME]\r\nTÃ©lÃ©phone : [STUDENT_PHONE]\r\nCi-aprÃ¨s dÃ©nommÃ© Â« le Locataire Â»\r\n\r\nIL A Ã‰TÃ‰ CONVENU CE QUI SUIT :\r\n\r\nArticle 1 - Objet du contrat\r\nLe prÃ©sent contrat a pour objet la location saisonniÃ¨re du logement situÃ© Ã  :\r\n[PROPERTY_ADDRESS]\r\nDÃ©signation : [PROPERTY_TITLE]\r\nType : [PROPERTY_TYPE]\r\n\r\nArticle 2 - DurÃ©e de la location\r\nLa prÃ©sente location est consentie pour une durÃ©e de [CONTRACT_DURATION] mois\r\nCommenÃ§ant le : [START_DATE]\r\nEt se terminant le : [END_DATE]\r\n\r\nArticle 3 - Prix et conditions de paiement\r\nLe prix de la location est fixÃ© Ã  [MONTHLY_RENT] TND par mois, payable Ã  la fin de chaque mois.\r\nUne caution de [SECURITY_DEPOSIT] TND est versÃ©e Ã  la signature du prÃ©sent contrat.\r\n\r\nArticle 4 - Charges et obligations\r\n4.1 Le Locataire s\'engage Ã  :\r\n- Occuper les lieux en bon pÃ¨re de famille\r\n- Ne pas sous-louer sans autorisation Ã©crite\r\n- Assurer le logement contre les risques locatifs\r\n- Respecter le rÃ¨glement intÃ©rieur de l\'immeuble\r\n\r\n4.2 Le PropriÃ©taire s\'engage Ã  :\r\n- DÃ©livrer le logement en bon Ã©tat d\'usage et de rÃ©parations\r\n- Assurer la jouissance paisible du logement\r\n- Effectuer les grosses rÃ©parations nÃ©cessaires\r\n\r\nArticle 5 - RÃ©siliation\r\nLe prÃ©sent contrat peut Ãªtre rÃ©siliÃ© par either party avec un prÃ©avis de 30 jours.\r\n\r\nArticle 6 - Droit applicable\r\nLe prÃ©sent contrat est soumis au droit tunisien.\r\n\r\nArticle 7 - Juridiction compÃ©tente\r\nEn cas de litige, les tribunaux de Tunis seront seuls compÃ©tents.\r\n\r\nFait Ã  Tunis, le [CONTRACT_DATE]\r\n\r\nEn deux exemplaires originaux.\r\n\r\nLe PropriÃ©taire                    Le Locataire\r\n\r\n[OWNER_SIGNATURE]                    [STUDENT_SIGNATURE]\r\n\r\nTÃ©moin : _________________\r\nTÃ©moin : _________________', 'Les clauses lÃ©gales tunisiennes s\'appliquent. Le locataire doit Ãªtre Ã©tudiant et fournir une attestation d\'inscription. Le propriÃ©taire doit Ãªtre propriÃ©taire lÃ©gal du bien.', 'Clause Tunisienne 1: Le prÃ©sent contrat est soumis Ã  la loi tunisienne sur la location saisonniÃ¨re.\r\nClause Tunisienne 2: Les Ã©tudiants Ã©trangers doivent fournir un visa Ã©tudiant valide.\r\nClause Tunisienne 3: Le propriÃ©taire doit dÃ©clarer la location aux autoritÃ©s fiscales tunisiennes.\r\nClause Tunisienne 4: Le locataire doit respecter les coutumes et traditions locales.', '2026-01-14 13:34:18', '2026-01-14 13:34:18', 1);

-- --------------------------------------------------------

--
-- Table structure for table `contract_termination_requests`
--

CREATE TABLE `contract_termination_requests` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `requested_by_user_id` int(11) NOT NULL,
  `requested_by_role` enum('student','owner') NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by_user_id` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_archived_user1` tinyint(1) DEFAULT 0,
  `is_archived_user2` tinyint(1) DEFAULT 0,
  `is_deleted_user1` tinyint(1) DEFAULT 0,
  `is_deleted_user2` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `listing_id`, `user1_id`, `user2_id`, `created_at`, `updated_at`, `is_archived_user1`, `is_archived_user2`, `is_deleted_user1`, `is_deleted_user2`) VALUES
(1, 1, 1, 2, '2026-01-10 09:52:57', '2026-01-10 09:52:57', 0, 0, 0, 0),
(2, 3, 1, 2, '2026-01-10 10:12:34', '2026-01-14 20:22:28', 0, 0, 0, 0),
(3, 4, 1, 2, '2026-01-10 10:13:10', '2026-01-10 10:13:10', 0, 0, 0, 0),
(4, 12, 1, 2, '2026-01-10 10:43:23', '2026-01-10 10:43:23', 0, 0, 0, 0),
(5, NULL, 1, 9, '2026-01-11 13:07:14', '2026-01-11 13:17:44', 0, 0, 0, 0),
(6, NULL, 2, 9, '2026-01-11 15:18:48', '2026-01-13 21:23:35', 0, 0, 0, 0),
(7, 6, 1, 2, '2026-01-11 16:06:49', '2026-01-13 21:17:34', 0, 1, 0, 0),
(8, 23, 2, 3, '2026-01-13 21:51:26', '2026-01-13 23:56:23', 0, 0, 0, 0),
(9, 5, 2, 9, '2026-01-15 11:25:16', '2026-01-15 11:25:16', 0, 0, 0, 0),
(10, 12, 1, 14, '2026-01-15 23:55:24', '2026-01-16 00:09:04', 0, 0, 0, 0),
(11, 10, 1, 2, '2026-01-16 01:04:40', '2026-01-16 01:04:40', 0, 0, 0, 0),
(12, NULL, 18, 2, '2026-03-27 12:04:12', '2026-03-27 12:04:15', 0, 0, 0, 0),
(13, NULL, 18, 1, '2026-03-27 12:04:37', '2026-03-27 12:04:37', 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `faculties`
--

CREATE TABLE `faculties` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `city` varchar(100) NOT NULL,
  `governorate` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `faculties`
--

INSERT INTO `faculties` (`id`, `name`, `city`, `governorate`, `address`, `latitude`, `longitude`, `created_at`) VALUES
(1, 'UniversitÃ© de Tunis El Manar', 'Tunis', 'Tunis', 'Campus Universitaire El Manar, 2092 Tunis', 36.84200000, 10.19050000, '2026-03-25 22:43:41'),
(2, 'UniversitÃ© de Carthage', 'Carthage', 'Tunis', '45 Rue de la RÃ©publique, Carthage 2016', 36.85266000, 10.32381000, '2026-03-25 22:43:41'),
(3, 'UniversitÃ© de la Manouba', 'Manouba', 'Manouba', 'Campus Universitaire Manouba, 2010', 36.80800000, 10.09710000, '2026-03-25 22:43:41'),
(4, 'ESPRIT - Ã‰cole SupÃ©rieure PrivÃ©e d\'IngÃ©nierie et de Technologies', 'Ariana', 'Ariana', 'Z.I. Chotrana II, 2083 Ariana', 36.89842000, 10.19351000, '2026-03-25 22:43:41'),
(5, 'Ã‰cole Nationale d\'IngÃ©nieurs de Tunis (ENIT)', 'Tunis', 'Tunis', 'BP 37, Le BelvÃ©dÃ¨re, 1002 Tunis', 36.84130000, 10.19440000, '2026-03-25 22:43:41'),
(6, 'INSAT - Institut National des Sciences AppliquÃ©es et de Technologie', 'Tunis', 'Tunis', 'Centre Urbain Nord BP 676, 1080 Tunis', 36.84560000, 10.19710000, '2026-03-25 22:43:41'),
(7, 'ESSEC Tunis - Ã‰cole SupÃ©rieure des Sciences Ã‰conomiques et Commerciales', 'Tunis', 'Tunis', '4 Rue Abou Zakaria El Hafsi, 1089 Montfleury', 36.84829000, 10.18340000, '2026-03-25 22:43:41'),
(8, 'UniversitÃ© de Tunis - Campus Ariana', 'Ariana', 'Ariana', 'Avenue de la RÃ©publique, Ariana', 36.86064000, 10.19524000, '2026-03-25 22:43:41'),
(9, 'EPT - Ã‰cole Polytechnique de Tunisie', 'La Marsa', 'Tunis', 'BP 743, 2078 La Marsa', 36.87850000, 10.32590000, '2026-03-25 22:43:41'),
(10, 'UniversitÃ© de Tunis - Campus MÃ©grine', 'MÃ©grine', 'Ben Arous', 'Route de MÃ©grine, Ben Arous', 36.74486000, 10.23095000, '2026-03-25 22:43:41'),
(11, 'UniversitÃ© de Sousse', 'Sousse', 'Sousse', 'Rue Kamel Eddine Elkhatmi, 4000 Sousse', 35.82670000, 10.63650000, '2026-03-25 22:43:41'),
(12, 'Ã‰cole Nationale d\'IngÃ©nieurs de Sousse (ENISo)', 'Sousse', 'Sousse', 'UniversitÃ© de Sousse, BP 264, Erriadh 4023', 35.82540000, 10.63210000, '2026-03-25 22:43:41'),
(13, 'Institut SupÃ©rieur d\'Informatique et de MultimÃ©dia de Sfax (ISIMS)', 'Sousse', 'Sousse', 'Route de la Ceinture Sahloul, 4054 Sousse', 35.82389000, 10.61095000, '2026-03-25 22:43:41'),
(14, 'FacultÃ© de MÃ©decine de Sousse', 'Sousse', 'Sousse', 'Avenue Mohamed Karoui, 4002 Sousse', 35.83180000, 10.63890000, '2026-03-25 22:43:41'),
(15, 'UniversitÃ© de Sfax', 'Sfax', 'Sfax', 'Route de l\'AÃ©roport Km 0.5, 3029 Sfax', 34.74010000, 10.76010000, '2026-03-25 22:43:41'),
(16, 'Ã‰cole Nationale d\'IngÃ©nieurs de Sfax (ENIS)', 'Sfax', 'Sfax', 'Route de Soukra Km 3.5, 3038 Sfax', 34.73650000, 10.74230000, '2026-03-25 22:43:41'),
(17, 'FacultÃ© des Sciences de Sfax', 'Sfax', 'Sfax', 'Route de Soukra Km 3.5, BP 1171, 3000 Sfax', 34.72970000, 10.74780000, '2026-03-25 22:43:41'),
(18, 'UniversitÃ© de Nabeul - Campus Nabeul', 'Nabeul', 'Nabeul', 'Avenue Habib Thameur, 8000 Nabeul', 36.45230000, 10.73560000, '2026-03-25 22:43:41'),
(19, 'Institut SupÃ©rieur des Sciences AppliquÃ©es et de Technologie de Mateur', 'Nabeul', 'Nabeul', 'Campus Universitaire Merazka, 8000 Nabeul', 36.44820000, 10.72940000, '2026-03-25 22:43:41'),
(20, 'UniversitÃ© de Monastir', 'Monastir', 'Monastir', 'Avenue de l\'Environnement, 5019 Monastir', 35.77610000, 10.81160000, '2026-03-25 22:43:41'),
(21, 'FacultÃ© de MÃ©decine de Monastir', 'Monastir', 'Monastir', 'Rue Avicenne, 5019 Monastir', 35.78330000, 10.82650000, '2026-03-25 22:43:41'),
(22, 'Ã‰cole Nationale d\'IngÃ©nieurs de Monastir (ENIM)', 'Monastir', 'Monastir', 'Avenue Ibn El Jazzar, 5019 Monastir', 35.77940000, 10.81790000, '2026-03-25 22:43:41'),
(23, 'UniversitÃ© de Bizerte', 'Bizerte', 'Bizerte', 'Route de Tunis, 7021 Zarzouna, Bizerte', 37.27440000, 9.87360000, '2026-03-25 22:43:41'),
(24, 'Ã‰cole Nationale d\'IngÃ©nieurs de Bizerte (ENIB)', 'Bizerte', 'Bizerte', 'Campus Universitaire, 7035 Bizerte', 37.27120000, 9.87590000, '2026-03-25 22:43:41'),
(25, 'UniversitÃ© de GabÃ¨s', 'GabÃ¨s', 'GabÃ¨s', 'Rue Omar Ibn El Khattab, 6029 GabÃ¨s', 33.88140000, 10.09820000, '2026-03-25 22:43:41'),
(26, 'Institut SupÃ©rieur des Sciences AppliquÃ©es et de Technologie de GabÃ¨s', 'GabÃ¨s', 'GabÃ¨s', 'Rue Omar El Khattab, 6072 GabÃ¨s', 33.88450000, 10.09550000, '2026-03-25 22:43:41'),
(27, 'UniversitÃ© de Kairouan', 'Kairouan', 'Kairouan', 'Avenue de la RÃ©publique, 3100 Kairouan', 35.67430000, 10.09670000, '2026-03-25 22:43:41'),
(28, 'UniversitÃ© de Gafsa', 'Gafsa', 'Gafsa', 'Campus Universitaire Sidi Ahmed Zarrouk, 2112 Gafsa', 34.42515000, 8.78420000, '2026-03-25 22:43:41'),
(29, 'UniversitÃ© de Jendouba', 'Jendouba', 'Jendouba', 'Avenue de l\'UMA, 8189 Jendouba', 36.50120000, 8.78050000, '2026-03-25 22:43:41'),
(30, 'Institut SupÃ©rieur des Ã‰tudes AppliquÃ©es en HumanitÃ©s de Sbeitla', 'Kasserine', 'Kasserine', 'Sbeitla, 1200 Kasserine', 35.23650000, 9.11780000, '2026-03-25 22:43:41'),
(31, 'UniversitÃ© Virtuelle de Tunis', 'Tunis', 'Tunis', 'Rue El Khawarezmi, CitÃ© El Khadra, 1003 Tunis', 36.83560000, 10.20480000, '2026-03-25 22:43:41'),
(32, 'Institut des Hautes Ã‰tudes Commerciales (IHEC) Carthage', 'Carthage', 'Tunis', 'UniversitÃ© de Carthage, 2016 Carthage PrÃ©sidence', 36.85950000, 10.32710000, '2026-03-25 22:43:41');

-- --------------------------------------------------------

--
-- Table structure for table `listings`
--

CREATE TABLE `listings` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `address` varchar(500) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `bedrooms` int(11) NOT NULL,
  `beds_count` int(11) DEFAULT 1,
  `capacity` int(11) DEFAULT 1,
  `gender_preference` enum('male','female','any') NOT NULL DEFAULT 'any' COMMENT 'Gender preference for listing occupants',
  `bathrooms` decimal(3,1) NOT NULL,
  `property_type` enum('apartment','house','studio','shared_room') NOT NULL,
  `available_from` date DEFAULT NULL,
  `available_until` date DEFAULT NULL,
  `owner_signature_path` varchar(500) DEFAULT NULL,
  `status` enum('active','rented','removed') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `owner_signature` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `listings`
--

INSERT INTO `listings` (`id`, `owner_id`, `title`, `description`, `address`, `latitude`, `longitude`, `price`, `bedrooms`, `beds_count`, `capacity`, `gender_preference`, `bathrooms`, `property_type`, `available_from`, `available_until`, `owner_signature_path`, `status`, `created_at`, `updated_at`, `owner_signature`) VALUES
(1, 1, 'Studio moderne prÃ¨s du campus - El Manar', 'Listing from demo. Original: ', 'El Manar, Tunis', 36.81550000, 10.17350000, 450.00, 1, 1, 1, 'any', 1.0, 'studio', NULL, NULL, NULL, 'active', '2026-01-04 19:38:33', '2026-01-04 19:38:33', NULL),
(2, 1, 'Chambre en colocation - Ariana', 'Listing from demo. Original: ', 'Ariana, Tunis', 36.86430000, 10.15770000, 300.00, 1, 1, 1, 'any', 1.0, 'shared_room', NULL, NULL, NULL, 'active', '2026-01-04 19:38:33', '2026-01-04 19:38:33', NULL),
(3, 1, 'Bureau H+2 Ã  La Soukra MBL0458', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/maison/ariana/soukra/el-bessatine/bureau-h+2-a-la-soukra-mbl0458/ivrj4l', 'Soukra, Ariana', 36.86080000, 10.17400000, 2000.00, 1, 1, 1, 'any', 1.0, 'house', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(4, 1, 'Appartement S+2 Ã  Ain Zaghouan Nord MAL0475', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/tunis/le-kram/el-bouhaira/appartement-s+2-a-ain-zaghouan-nord-mal0475/k2gwuv', 'Le Kram, Tunis', 36.83930000, 10.30700000, 2000.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(5, 1, 'Appartement S+2 Ã  Ain Zaghouan Nord ZAL1355', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/ariana/soukra/borj-el-ouzir/appartement-s+2-a-ain-zaghouan-nord-zal1355/8la82f', 'Soukra, Ariana', 36.86380000, 10.16100000, 2000.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(6, 1, 'Appartement S+2 aux Jardins de Carthage MAL1352', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/tunis/le-kram/el-bouhaira/appartement-s+2-aux-jardins-de-carthage-mal1352/93uue1', 'Le Kram, Tunis', 36.83830000, 10.30100000, 1550.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(7, 1, 'Appartement S+1 avec jardin aux Jardins de Carthage ZAL1343', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/tunis/le-kram/el-bouhaira/appartement-s+1-avec-jardin-aux-jardins-de-carthag/9q8g6x', 'Le Kram, Tunis', 36.82830000, 10.29100000, 1600.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(8, 1, 'Appartement S+4 aux Jardins de Carthage MAL1327', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/tunis/le-kram/el-bouhaira/appartement-s+4-aux-jardins-de-carthage-mal1327/2kk4fj', 'Le Kram, Tunis', 36.83330000, 10.30500000, 3000.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(9, 1, 'Appartement S+2 Ã  La Soukra  MAL1347', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/ariana/soukra/dar-fadhal/appartement-s+2-a-la-soukra-mal1347/vlxm28', 'Soukra, Ariana', 36.86280000, 10.17000000, 1300.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(10, 1, 'Local commercial Ã  sidi Abdel Aziz La Marsa  ZCV0092', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/tunis/la-marsa/la-marsa-medina/local-commercial-a-sidi-abdel-aziz-la-marsa-zcv00/cszog7', 'La Marsa, Tunis', 36.88460000, 10.32240000, 1225000.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(11, 1, 'Ã‰tage De Villa S+3 Ã€ Lâ€™annÃ©e Proche De La Plage', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/mahdia/mahdia/hiboun/etage-de-villa-s+3-a-lâ€™annee-proche-de-la-plage/jz7kon', 'Mahdia, Mahdia', 35.49970000, 11.05220000, 950.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(12, 1, 'appartement s1 nabeul', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/nabeul/nabeul/niapolis/appartement-s1-nabeul/e1wga9', 'Nabeul, Nabeul', 36.45710000, 10.73260000, 750.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(13, 1, 'Etage de villa centre ville de Gafsa', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/gafsa/gafsa-sud/gafsa-est/etage-de-villa-centre-ville-de-gafsa/ui3kl6', 'Gafsa Sud, Gafsa', 36.81100000, 10.18700000, 600.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(14, 1, 'S+1 MeublÃ© Ã  l\'annÃ©e pour Ã©tranger Ennasr2 ', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/ariana/ariana-ville/ennasr-2/s+1-meuble-a-l-annee-pour-etranger-ennasr2/0p52wj', 'Ariana Ville, Ariana', 36.87430000, 10.15570000, 1200.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(15, 1, 'Dar Bent ALi Ã  louer Ã  djerba', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/maison/mednine/djerba-houmet-souk/erriadh/dar-bent-ali-a-louer-a-djerba/9wggfy', 'Djerba Houmet Souk, Mednine', 36.84500000, 10.20800000, 450.00, 1, 1, 1, 'any', 1.0, 'house', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(16, 1, 'Chambre individuelle pour fille a megrine ', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/ben-arous/megrine/megrine-superieure/chambre-individuelle-pour-fille-a-megrine/v91ebv', 'Megrine, Ben Arous', 36.84700000, 10.15800000, 250.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(17, 1, 'ðŸ˜ðŸ˜ Une bonne affaire pour profiter des vacances ', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/sousse/sousse-medina/el-medina/ðŸ˜ðŸ˜-une-bonne-affaire-pour-profiter-des-vacances/9dutlh', 'Sousse Medina, Sousse', 35.82060000, 10.64190000, 140.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(18, 1, 'une place dans une chambre a 2', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/tunis/el-menzah/el-manar-(1)/une-place-dans-une-chambre-a-2/788szv', 'El Menzah, Tunis', 36.83500000, 10.16100000, 185.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(19, 1, 'S4 au jardin de carthage', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/tunis/le-kram/el-bouhaira/s4-au-jardin-de-carthage/enj49', 'Le Kram, Tunis', 36.83530000, 10.30900000, 2200.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(20, 1, 'Colocation a Hammamet', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/nabeul/hammamet/hammamet-ouest/colocation-a-hammamet/nnx2sv', 'Hammamet, Nabeul', 36.82000000, 10.20800000, 200.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(21, 1, 'Appt 3chamb 3 climati 2 salle de bain chauff centr', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/ben-arous/ben-arous/ben-arous-ouest/appt-3chamb-3-climati-2-salle-de-bain-chauff-centr/mzai7o', 'Ben Arous, Ben Arous', 36.79100000, 10.16600000, 750.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(22, 1, 'A louer chambre individuelle', 'Listing from houni.tn. Original: https://www.houni.tn/annonce/colocation/appartement/ariana/soukra/ettaamir/a-louer-chambre-individuelle/4jidru', 'Soukra, Ariana', 36.84580000, 10.17400000, 280.00, 1, 1, 1, 'any', 1.0, 'apartment', NULL, NULL, NULL, 'active', '2026-01-04 19:41:45', '2026-01-04 19:41:45', NULL),
(23, 3, 'studio S+1', 'studio s+1', 'Tunis', 36.79542969, 10.17944397, 1000.00, 1, 1, 1, 'any', 1.0, 'studio', '2026-01-29', NULL, NULL, 'active', '2026-01-10 15:39:13', '2026-01-16 02:14:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `listing_images`
--

CREATE TABLE `listing_images` (
  `id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `listing_images`
--

INSERT INTO `listing_images` (`id`, `listing_id`, `image_path`, `display_order`, `created_at`) VALUES
(1, 3, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/ec89e5e2-17c5-4aa6-8330-bf5362dccc26/OkysFTn8pe1735558774541.webp', 0, '2026-01-04 19:41:45'),
(2, 3, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/ec89e5e2-17c5-4aa6-8330-bf5362dccc26/RUGF1Iw9hz1735558779052.webp', 1, '2026-01-04 19:41:45'),
(3, 3, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/ec89e5e2-17c5-4aa6-8330-bf5362dccc26/CY7ecSLYHc1735558782480.webp', 2, '2026-01-04 19:41:45'),
(4, 3, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/ec89e5e2-17c5-4aa6-8330-bf5362dccc26/ePyic6B5J71735558783731.webp', 3, '2026-01-04 19:41:45'),
(5, 3, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/ec89e5e2-17c5-4aa6-8330-bf5362dccc26/AtOOK2oPae1735558788259.webp', 4, '2026-01-04 19:41:45'),
(6, 4, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/3cbc9324-21f2-407e-8689-8cb69d4a063e/ewGMui7iz31735551654021.webp', 0, '2026-01-04 19:41:45'),
(7, 4, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/3cbc9324-21f2-407e-8689-8cb69d4a063e/eSgrarO2B71735551654676.webp', 1, '2026-01-04 19:41:45'),
(8, 4, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/3cbc9324-21f2-407e-8689-8cb69d4a063e/iqZeJUn0P51735551655188.webp', 2, '2026-01-04 19:41:45'),
(9, 4, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/3cbc9324-21f2-407e-8689-8cb69d4a063e/qoQKSPhocy1735551655609.webp', 3, '2026-01-04 19:41:45'),
(10, 4, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/3cbc9324-21f2-407e-8689-8cb69d4a063e/E2gbYggPtU1735551656007.webp', 4, '2026-01-04 19:41:45'),
(11, 5, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/2a2e7c7a-bb29-43a6-8f64-ecb4a0689fb3/LcZ3TOuM2Z1735379384123.webp', 0, '2026-01-04 19:41:45'),
(12, 5, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/2a2e7c7a-bb29-43a6-8f64-ecb4a0689fb3/RzKUT7fbHe1735379385041.webp', 1, '2026-01-04 19:41:45'),
(13, 5, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/2a2e7c7a-bb29-43a6-8f64-ecb4a0689fb3/vX5P8HOMEL1735379385640.webp', 2, '2026-01-04 19:41:45'),
(14, 5, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/2a2e7c7a-bb29-43a6-8f64-ecb4a0689fb3/hu2pmsHnV11735379386176.webp', 3, '2026-01-04 19:41:45'),
(15, 5, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/2a2e7c7a-bb29-43a6-8f64-ecb4a0689fb3/Rrlkd53Hw91735379386719.webp', 4, '2026-01-04 19:41:45'),
(16, 6, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/bba4aa7a-fc9c-4954-a530-ca98df3cd67d/c2aEN4WFGE1735293485513.webp', 0, '2026-01-04 19:41:45'),
(17, 6, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/bba4aa7a-fc9c-4954-a530-ca98df3cd67d/ZpTrKoTT2s1735293489591.webp', 1, '2026-01-04 19:41:45'),
(18, 6, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/bba4aa7a-fc9c-4954-a530-ca98df3cd67d/GFRIzVbVJV1735293490011.webp', 2, '2026-01-04 19:41:45'),
(19, 6, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/bba4aa7a-fc9c-4954-a530-ca98df3cd67d/Nr7X3VIERY1735293493839.webp', 3, '2026-01-04 19:41:45'),
(20, 6, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/bba4aa7a-fc9c-4954-a530-ca98df3cd67d/NApEPCBGHQ1735293497502.webp', 4, '2026-01-04 19:41:45'),
(21, 7, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/f6625985-56f4-41b7-b602-0b094fbd28fb/4mC4eCnAcT1735292187701.webp', 0, '2026-01-04 19:41:45'),
(22, 7, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/f6625985-56f4-41b7-b602-0b094fbd28fb/DhrzhPwzNW1735292189297.webp', 1, '2026-01-04 19:41:45'),
(23, 7, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/f6625985-56f4-41b7-b602-0b094fbd28fb/fJb3EDqF3T1735292194183.webp', 2, '2026-01-04 19:41:45'),
(24, 7, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/f6625985-56f4-41b7-b602-0b094fbd28fb/IEgPPaDfIe1735292194598.webp', 3, '2026-01-04 19:41:45'),
(25, 7, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/f6625985-56f4-41b7-b602-0b094fbd28fb/IbtNYzKdGX1735292198539.webp', 4, '2026-01-04 19:41:45'),
(26, 8, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/e403fdb8-1909-4c07-85b6-76cfa0500be8/cRJgpx41hQ1735209000306.webp', 0, '2026-01-04 19:41:45'),
(27, 8, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/e403fdb8-1909-4c07-85b6-76cfa0500be8/eZUT2N6EfX1735209001148.webp', 1, '2026-01-04 19:41:45'),
(28, 8, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/e403fdb8-1909-4c07-85b6-76cfa0500be8/KLFg9EnJZn1735209001966.webp', 2, '2026-01-04 19:41:45'),
(29, 8, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/e403fdb8-1909-4c07-85b6-76cfa0500be8/n51Xo4Vt5z1735209002697.webp', 3, '2026-01-04 19:41:45'),
(30, 8, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/e403fdb8-1909-4c07-85b6-76cfa0500be8/kcn51Cv8Pr1735209004289.webp', 4, '2026-01-04 19:41:45'),
(31, 9, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/11ca19c1-dfaf-4509-bfda-ddca3b904c08/3p8f0oAyjU1735207221935.webp', 0, '2026-01-04 19:41:45'),
(32, 9, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/11ca19c1-dfaf-4509-bfda-ddca3b904c08/04bztcWDbV1735207222603.webp', 1, '2026-01-04 19:41:45'),
(33, 9, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/11ca19c1-dfaf-4509-bfda-ddca3b904c08/nOR7x8N9Wu1735207223025.webp', 2, '2026-01-04 19:41:45'),
(34, 9, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/11ca19c1-dfaf-4509-bfda-ddca3b904c08/RqOGNHjVvs1735207223444.webp', 3, '2026-01-04 19:41:45'),
(35, 9, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/11ca19c1-dfaf-4509-bfda-ddca3b904c08/PfavsVZpq91735207223840.webp', 4, '2026-01-04 19:41:45'),
(36, 10, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/022e0ad6-8eea-4ed2-a64c-7c2499abf366/sm5NcrRVyR1735205904653.webp', 0, '2026-01-04 19:41:45'),
(37, 11, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/44f6f78b-1314-4a92-be29-0f78a3659181/pqVrawNxTw1733407135369.webp', 0, '2026-01-04 19:41:45'),
(38, 11, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/44f6f78b-1314-4a92-be29-0f78a3659181/sSerunhGAD1733407136761.webp', 1, '2026-01-04 19:41:45'),
(39, 11, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/44f6f78b-1314-4a92-be29-0f78a3659181/39YL02BqVW1733407138057.webp', 2, '2026-01-04 19:41:45'),
(40, 11, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/44f6f78b-1314-4a92-be29-0f78a3659181/uej9wlNspH1733407139412.webp', 3, '2026-01-04 19:41:45'),
(41, 11, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/44f6f78b-1314-4a92-be29-0f78a3659181/qLYZWbm99Y1733407140604.webp', 4, '2026-01-04 19:41:45'),
(42, 12, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/f3a74108-dd9e-45b0-a8e9-f83907732ed8/fldSf3nMRY1727168775530.webp', 0, '2026-01-04 19:41:45'),
(43, 12, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/f3a74108-dd9e-45b0-a8e9-f83907732ed8/ss1dEauCyD1727168777749.webp', 1, '2026-01-04 19:41:45'),
(44, 12, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/f3a74108-dd9e-45b0-a8e9-f83907732ed8/y3fyNzy1pU1727168779453.webp', 2, '2026-01-04 19:41:45'),
(45, 12, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/f3a74108-dd9e-45b0-a8e9-f83907732ed8/d2RnR97BGb1727168782083.webp', 3, '2026-01-04 19:41:45'),
(46, 12, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/f3a74108-dd9e-45b0-a8e9-f83907732ed8/tK5IgZFkc31727168784374.webp', 4, '2026-01-04 19:41:45'),
(47, 13, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/a0d21a01-7f22-457a-a255-521ed66cb8d9/bfKNrb9r7d1726054563910.webp', 0, '2026-01-04 19:41:45'),
(48, 13, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/a0d21a01-7f22-457a-a255-521ed66cb8d9/o5B35nEoaK1726054572941.webp', 1, '2026-01-04 19:41:45'),
(49, 13, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/a0d21a01-7f22-457a-a255-521ed66cb8d9/fW7R6UqEMT1726054583765.webp', 2, '2026-01-04 19:41:45'),
(50, 13, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/a0d21a01-7f22-457a-a255-521ed66cb8d9/2O23pvNl2H1726054594268.webp', 3, '2026-01-04 19:41:45'),
(51, 13, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/a0d21a01-7f22-457a-a255-521ed66cb8d9/WNjZx0mEdV1726054603484.webp', 4, '2026-01-04 19:41:45'),
(52, 14, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/8e1a0f2e-4155-4faa-a464-074a8e7b1334/ofybGoexvA1725322965558.webp', 0, '2026-01-04 19:41:45'),
(53, 14, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/8e1a0f2e-4155-4faa-a464-074a8e7b1334/ihcHGWhO6b1725322966186.webp', 1, '2026-01-04 19:41:45'),
(54, 14, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/8e1a0f2e-4155-4faa-a464-074a8e7b1334/d9eB6Ai7IQ1725322966626.webp', 2, '2026-01-04 19:41:45'),
(55, 14, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/8e1a0f2e-4155-4faa-a464-074a8e7b1334/e6HB8vaRqs1725322967446.webp', 3, '2026-01-04 19:41:45'),
(56, 14, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/8e1a0f2e-4155-4faa-a464-074a8e7b1334/kOwDpm4zDd1725323023360.webp', 4, '2026-01-04 19:41:45'),
(57, 15, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/4377d854-10a5-4fac-946b-11658803da74/n6UZ1l2dCa1723543298002.webp', 0, '2026-01-04 19:41:45'),
(58, 15, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/4377d854-10a5-4fac-946b-11658803da74/bcHakSWGSz1723543299033.webp', 1, '2026-01-04 19:41:45'),
(59, 15, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/4377d854-10a5-4fac-946b-11658803da74/tp74jJPMgQ1723543305947.webp', 2, '2026-01-04 19:41:45'),
(60, 15, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/4377d854-10a5-4fac-946b-11658803da74/Pv3UoD9UxL1723543311246.webp', 3, '2026-01-04 19:41:45'),
(61, 15, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/4377d854-10a5-4fac-946b-11658803da74/O5sJJHxQ3M1723543315533.webp', 4, '2026-01-04 19:41:45'),
(62, 16, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/b17ef12d-c6f2-4f4f-a0a3-41a3d0070ac3/7r7Jq3KDjI1710021956830.webp', 0, '2026-01-04 19:41:45'),
(63, 16, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/b17ef12d-c6f2-4f4f-a0a3-41a3d0070ac3/8ITLcJdAne1710021968693.webp', 1, '2026-01-04 19:41:45'),
(64, 16, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/b17ef12d-c6f2-4f4f-a0a3-41a3d0070ac3/m5AY0MW7Rv1710021983141.webp', 2, '2026-01-04 19:41:45'),
(65, 16, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/b17ef12d-c6f2-4f4f-a0a3-41a3d0070ac3/8V2fk4AS6M1710021995849.webp', 3, '2026-01-04 19:41:45'),
(66, 17, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/5f9acc07-d969-408a-bfa9-0ef41a0c780f/QDAdb7IUMC1708795310634.webp', 0, '2026-01-04 19:41:45'),
(67, 17, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/5f9acc07-d969-408a-bfa9-0ef41a0c780f/7hM17jWga11708795323684.webp', 1, '2026-01-04 19:41:45'),
(68, 17, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/5f9acc07-d969-408a-bfa9-0ef41a0c780f/jhsamfo9KN1708795339724.webp', 2, '2026-01-04 19:41:45'),
(69, 17, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/5f9acc07-d969-408a-bfa9-0ef41a0c780f/GGab8Epx6w1708795405040.webp', 3, '2026-01-04 19:41:45'),
(70, 17, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/5f9acc07-d969-408a-bfa9-0ef41a0c780f/MYDsZbKFis1708795430210.webp', 4, '2026-01-04 19:41:45'),
(71, 18, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/a2c336d9-766c-4c80-bd2a-abdfd5efefc2/juepPSd3271706007381487.webp', 0, '2026-01-04 19:41:45'),
(72, 19, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/1998a40e-8049-4338-b3e6-c0b0bf534e18/jdft9E46Fd1702968178491.webp', 0, '2026-01-04 19:41:45'),
(73, 19, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/1998a40e-8049-4338-b3e6-c0b0bf534e18/pgJfm5isoL1702968179087.webp', 1, '2026-01-04 19:41:45'),
(74, 19, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/1998a40e-8049-4338-b3e6-c0b0bf534e18/ZwtE7AhvBw1702968180691.webp', 2, '2026-01-04 19:41:45'),
(75, 19, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/1998a40e-8049-4338-b3e6-c0b0bf534e18/cj7kgv9rFR1702968194546.webp', 3, '2026-01-04 19:41:45'),
(76, 19, 'https://storage.googleapis.com/hounitn_cdn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/1998a40e-8049-4338-b3e6-c0b0bf534e18/LASUjBUDDz1702968210028.webp', 4, '2026-01-04 19:41:45'),
(77, 20, 'hounitn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/15fe2c1a-1850-4b4d-9238-6ac3776c42f6/WcprwBDyhB1693919752079.webp', 0, '2026-01-04 19:41:45'),
(78, 21, 'hounitn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/5125cc58-6eac-436f-a952-b67d730d2938/sM6QOTxVvf1680364485425.webp', 0, '2026-01-04 19:41:45'),
(79, 21, 'hounitn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/5125cc58-6eac-436f-a952-b67d730d2938/Aid1L6zQlr1680364486326.webp', 1, '2026-01-04 19:41:45'),
(80, 21, 'hounitn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/5125cc58-6eac-436f-a952-b67d730d2938/NQWbV7uviq1680364487133.webp', 2, '2026-01-04 19:41:45'),
(81, 21, 'hounitn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/5125cc58-6eac-436f-a952-b67d730d2938/5FHAWZS0X41680364487756.webp', 3, '2026-01-04 19:41:45'),
(82, 21, 'hounitn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/5125cc58-6eac-436f-a952-b67d730d2938/tjUkuLZGWH1680364488488.webp', 4, '2026-01-04 19:41:45'),
(83, 22, 'hounitn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/3a2a7c8f-371d-4865-9f74-f22d886b8e78/ioaPa6CyWv1676817877728.webp', 0, '2026-01-04 19:41:45'),
(84, 22, 'hounitn/SvbVHn_oI8t06079Kd_v55dbzxELY_dfE/3a2a7c8f-371d-4865-9f74-f22d886b8e78/IzjbTXaBCd1676817938314.webp', 1, '2026-01-04 19:41:45'),
(85, 23, 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxITEhUSEhIWFRUXFRUXFxUXGBcXGBYVGBcXFxcXGBYeHiggGBolHRgXITEhJSkrLi4uGB8zODMtNygtLisBCgoKDg0OGhAQGy0lHyUtLS0tLS0tLS0tLS0tLS0tLS0rLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAKMBNgMBIgACEQEDEQH/xAAcAAABBQEBAQAAAAAAAAAAAAAEAAIDBQYBBwj/xABNEAACAAMFBAYFBgsFCAMAAAABAgADEQQFEiExBhNBUSJhcYGRsTJCUqHBFCNygpLRBxUzU2KywtLh8PEWJFSTokNEVWNzg7PjlMPi/8QAGgEAAwEBAQEAAAAAAAAAAAAAAAECAwQFBv/EAC0RAAICAQIFAgYCAwEAAAAAAAABAhEDEiEEEzFBURSRIjJhgaHwBbFCUnEz/', 0, '2026-01-10 15:39:13');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) DEFAULT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `listing_id` int(11) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted_sender` tinyint(1) DEFAULT 0,
  `is_deleted_receiver` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `receiver_id`, `listing_id`, `subject`, `message`, `is_read`, `read_at`, `created_at`, `is_deleted_sender`, `is_deleted_receiver`) VALUES
(1, 2, 2, 1, NULL, NULL, 'helllo', 1, '2026-01-10 11:20:28', '2026-01-10 10:59:57', 0, 0),
(2, 2, 1, 2, NULL, NULL, 'helllllllllllo', 1, '2026-01-10 11:21:28', '2026-01-10 11:21:26', 0, 0),
(3, 5, 9, 1, NULL, NULL, 'hello', 1, '2026-01-11 13:17:50', '2026-01-11 13:17:44', 0, 0),
(4, 6, 2, 9, NULL, NULL, 'hello', 1, '2026-01-11 18:42:24', '2026-01-11 18:10:32', 0, 0),
(5, 8, 2, 3, NULL, NULL, 'hi', 1, '2026-01-14 10:50:46', '2026-01-13 23:56:23', 0, 0),
(6, 10, 14, 1, NULL, NULL, 'Hello! Audit test message.', 0, NULL, '2026-01-16 00:09:04', 0, 0),
(7, 12, 18, 2, NULL, NULL, 'hi', 0, NULL, '2026-03-27 12:04:15', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `payment_method` enum('credit_card','bank_transfer','cash','fake_payment') DEFAULT 'fake_payment',
  `transaction_id` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `platform_fee` decimal(10,2) DEFAULT 0.00,
  `owner_amount` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(3) DEFAULT 'TND',
  `status` enum('pending','processing','completed','failed','refunded') DEFAULT 'pending',
  `payment_type` enum('monthly_rent','security_deposit','partial_payment','full_payment') NOT NULL,
  `due_date` date DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `failure_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_transactions`
--

CREATE TABLE `payment_transactions` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `transaction_type` enum('initiated','processing','completed','failed','refunded') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `gateway_response` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `platform_commissions`
--

CREATE TABLE `platform_commissions` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `contract_id` int(11) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT 5.00,
  `commission_amount` decimal(10,2) NOT NULL,
  `calculated_on` decimal(10,2) NOT NULL,
  `status` enum('pending','collected','paid_out') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) DEFAULT NULL,
  `reported_listing_id` int(11) DEFAULT NULL,
  `report_type` enum('user','listing') NOT NULL,
  `reason` enum('scam_fraud','inappropriate_content','inappropriate_behavior','fake_listing','other') NOT NULL,
  `description` text NOT NULL,
  `status` enum('open','investigating','resolved','dismissed') NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `assigned_to` int(11) DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reports`
--

INSERT INTO `reports` (`id`, `reporter_id`, `reported_user_id`, `reported_listing_id`, `report_type`, `reason`, `description`, `status`, `priority`, `assigned_to`, `resolved_by`, `resolved_at`, `resolution_notes`, `created_at`) VALUES
(1, 2, 9, NULL, 'user', '', 'that it', 'open', 'medium', NULL, NULL, NULL, NULL, '2026-01-13 21:16:15');

-- --------------------------------------------------------

--
-- Table structure for table `roommate_preferences`
--

CREATE TABLE `roommate_preferences` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `budget_min` decimal(10,2) DEFAULT NULL,
  `budget_max` decimal(10,2) DEFAULT NULL,
  `cleanliness_level` enum('very_clean','clean','moderate','relaxed') DEFAULT NULL,
  `smoking_preference` enum('non_smoker','smoker','no_preference') DEFAULT NULL,
  `noise_tolerance` enum('quiet','moderate','social','no_preference') DEFAULT NULL,
  `sleep_schedule` enum('early_riser','normal','night_owl','no_preference') DEFAULT NULL,
  `gender_preference` enum('male','female','no_preference') DEFAULT NULL,
  `age_min` int(11) DEFAULT NULL,
  `age_max` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `gender` varchar(20) DEFAULT NULL,
  `cleanliness` varchar(50) DEFAULT NULL,
  `noise_level` varchar(50) DEFAULT NULL,
  `guests` varchar(50) DEFAULT NULL,
  `smoking` varchar(20) DEFAULT NULL,
  `pets` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roommate_preferences`
--

INSERT INTO `roommate_preferences` (`id`, `user_id`, `budget_min`, `budget_max`, `cleanliness_level`, `smoking_preference`, `noise_tolerance`, `sleep_schedule`, `gender_preference`, `age_min`, `age_max`, `created_at`, `updated_at`, `gender`, `cleanliness`, `noise_level`, `guests`, `smoking`, `pets`) VALUES
(1, 2, 0.00, 900.00, 'very_clean', 'non_smoker', 'moderate', 'normal', '', NULL, NULL, '2026-01-11 12:41:11', '2026-01-11 16:07:23', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 9, 0.00, 1000.00, '', 'no_preference', 'no_preference', 'no_preference', '', NULL, NULL, '2026-01-11 13:04:50', '2026-01-11 13:04:50', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 18, 500.00, 2000.00, 'moderate', 'non_smoker', 'moderate', 'normal', 'male', 20, 30, '2026-03-27 12:01:35', '2026-03-27 12:01:35', NULL, NULL, NULL, 'occasional', NULL, 'no_pets');

-- --------------------------------------------------------

--
-- Table structure for table `saved_listings`
--

CREATE TABLE `saved_listings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `listing_id` int(11) NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `saved_listings`
--

INSERT INTO `saved_listings` (`id`, `user_id`, `listing_id`, `saved_at`) VALUES
(2, 2, 23, '2026-01-13 21:55:22');

-- --------------------------------------------------------

--
-- Table structure for table `subscriptions`
--

CREATE TABLE `subscriptions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plan` enum('monthly','yearly') NOT NULL DEFAULT 'monthly',
  `amount` decimal(10,2) NOT NULL,
  `status` enum('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `starts_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `payment_method` varchar(50) DEFAULT 'card',
  `card_last4` varchar(4) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscriptions`
--

INSERT INTO `subscriptions` (`id`, `user_id`, `plan`, `amount`, `status`, `starts_at`, `expires_at`, `payment_method`, `card_last4`, `created_at`) VALUES
(1, 2, 'monthly', 20.00, 'active', '2026-03-26 00:14:55', '2026-04-26 00:14:55', 'card', '4569', '2026-03-26 00:14:55'),
(2, 18, 'monthly', 25.00, 'active', '2026-03-27 10:29:10', '2026-04-27 10:29:10', 'card', '9653', '2026-03-27 11:29:10');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('student','owner','admin') NOT NULL DEFAULT 'student',
  `university` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL COMMENT 'User gender (required for students)',
  `preferred_lat` decimal(10,8) DEFAULT NULL,
  `preferred_lng` decimal(11,8) DEFAULT NULL,
  `preferred_address` varchar(500) DEFAULT NULL,
  `university_address` varchar(500) DEFAULT NULL,
  `status` enum('active','suspended','banned') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `full_name`, `role`, `university`, `phone`, `gender`, `preferred_lat`, `preferred_lng`, `preferred_address`, `university_address`, `status`, `created_at`, `updated_at`) VALUES
(1, 'admin@unidar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2026-01-04 09:51:23', '2026-01-04 09:51:23'),
(2, 'ahmedismail2321@gmail.com', '$2y$10$w94WttQb4Ai02gRgvXIG5ernjCj3UAnouQXhzXfZnSB9zIllyceM.', 'ahmed ismail', 'student', 'esprit', '', NULL, 36.85558729, 10.18614212, '36.855587291081974, 10.18614212230847', '', 'active', '2026-01-04 10:05:19', '2026-01-04 10:05:19'),
(3, 'islembenamor1110@gmail.com', '$2y$10$68CP32Sfn20aoiF.7tOI2Oww6klJiY9bAKxc9pY8BF/fA7lNdM32S', 'islem', 'owner', '', '', NULL, NULL, NULL, '', '', 'active', '2026-01-10 11:25:59', '2026-01-10 11:25:59'),
(9, 'ihebismail@gmail.com', '$2y$10$aOXp2Z7Fv9EgnCjAh7m/CuUHAMJ8ugrnsPC/lnKLX9eu1hWMGDtEu', 'iheb ismail', 'student', 'esprit', '', NULL, 36.82165312, 10.19800848, '36.821653121434515, 10.198008475990052', '', 'active', '2026-01-11 13:03:01', '2026-01-11 13:03:01'),
(11, 'owner@test.com', '$2y$10$fMaaNfd7oraOklOwk8ZD4e3QUB5OEwWl6Ev74kaSbnJAr5nlP8Y7q', 'Owner Test', 'owner', '', '', NULL, NULL, NULL, '', '', 'active', '2026-01-11 17:32:33', '2026-01-11 17:32:33'),
(12, 'islem_owner@example.com', '$2y$10$/35WOFNoTWvB0Q.yma08K.f7XujAkapfRUaykLfIvanPbcIDdZ33G', 'Islem Owner', 'owner', '', '', NULL, NULL, NULL, '', '', 'active', '2026-01-11 17:49:03', '2026-01-11 17:49:03'),
(13, 'test294@test.com', '$2y$10$la7eeOTZ02p80vKciuw0nuThy9N7br8iuV4Zl51WJiolvtu5qcE26', 'Verification Test', 'student', '', '', NULL, NULL, NULL, '', '', 'active', '2026-01-11 17:53:44', '2026-01-11 17:53:44'),
(14, 'teststudent@unidar.tn', '$2y$10$oGjRq9npjZa9Pn5616mJJuEvX5MIwZoU879pyW0wOXeAVUtdk3.xa', 'Test Student', 'student', '', '12345678', NULL, 36.80650000, 10.18150000, 'Tunis, Tunisia', '', 'active', '2026-01-15 23:54:13', '2026-01-15 23:54:13'),
(15, 'testowner@unidar.tn', '$2y$10$a9EEO1zj9yBRvHAc0Xs.i.nRp8GsFTh3mhK.1ByLXn9qET/rBoGbi', 'Test Owner', 'owner', '', '87654321', NULL, NULL, NULL, '', '', 'active', '2026-01-15 23:58:45', '2026-01-15 23:58:45'),
(16, 'test@university.edu', '$2y$10$lY3RKVB3dHyJgRYTtMKSjeYwFy8mA94OQ0UFVKuCIT8UUq7T8.euW', 'test', 'student', 'esprim', '', 'male', 35.74740397, 10.81402043, 'الحلية 2, Al Munastir, Monastir, 5019, Tunisia', '', 'active', '2026-03-26 19:24:26', '2026-03-26 19:24:26'),
(17, 'test@university.tn', '$2a$10$KSnmDoydr6YItueEUw0rF.g5IpPKG28TSfSuwxp8ZO9/MRroYnhRC', 'test', 'student', NULL, NULL, 'male', NULL, NULL, NULL, NULL, 'active', '2026-03-26 23:48:37', '2026-03-26 23:48:37'),
(18, 'test2@university.edu', '$2a$10$KpyZsTnALXxXDoAkBFUmD.WPqDFsuBt38.YOIaZI4aU6zILnBsDtu', 'test2', 'student', NULL, NULL, 'male', NULL, NULL, NULL, NULL, 'active', '2026-03-27 10:23:45', '2026-03-27 10:23:45'),
(19, 'islem@esprim.tn', '$2a$10$O22J7MmtssVAxYlfaFIyLe8g8vKncjLLlHPtfwVWizVSwxmkAjnDS', 'islem', 'student', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'active', '2026-04-01 20:53:06', '2026-04-01 20:53:06'),
(20, 'yassine@gmail.com', '$2a$10$6fzx39JOQhn21hk3D.uRm.TE5UKxA8kxkzfkpOi15wIX/fpeGyCKy', 'yassine', 'student', NULL, NULL, 'male', NULL, NULL, NULL, NULL, 'active', '2026-04-02 10:43:33', '2026-04-02 10:43:33');

-- --------------------------------------------------------

--
-- Table structure for table `verifications`
--

CREATE TABLE `verifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `student_id_file` varchar(255) DEFAULT NULL,
  `national_id_file` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `verifications`
--

INSERT INTO `verifications` (`id`, `user_id`, `student_id_file`, `national_id_file`, `status`, `reviewed_by`, `reviewed_at`, `rejection_reason`, `submitted_at`) VALUES
(2, 2, 'uploads/verifications/69c4655dedb8b_1774478685.pdf', 'uploads/verifications/69c4655dee20e_1774478685.pdf', 'approved', 1, '2026-03-25 22:45:26', NULL, '2026-03-25 22:44:45'),
(3, 9, 'uploads/verifications/69c585d4617c4_1774552532.pdf', 'uploads/verifications/69c585d461a34_1774552532.pdf', 'pending', NULL, NULL, NULL, '2026-03-26 19:15:32'),
(4, 18, 'uploads/verifications/o4d17eyrgxs47h5xbz5i6ybva.pdf', 'uploads/verifications/ebbjflrmexf30z70vy72ikbnv.pdf', 'approved', 1, '2026-03-27 11:19:13', NULL, '2026-03-27 10:55:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `target_user_id` (`target_user_id`),
  ADD KEY `target_listing_id` (`target_listing_id`),
  ADD KEY `target_report_id` (`target_report_id`),
  ADD KEY `idx_admin` (`admin_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `blocked_users`
--
ALTER TABLE `blocked_users`
  ADD PRIMARY KEY (`blocker_id`,`blocked_id`),
  ADD KEY `blocked_id` (`blocked_id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contract_number` (`contract_number`),
  ADD KEY `idx_listing_id` (`listing_id`),
  ADD KEY `idx_student_id` (`student_id`),
  ADD KEY `idx_owner_id` (`owner_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_contract_number` (`contract_number`);

--
-- Indexes for table `contract_templates`
--
ALTER TABLE `contract_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_template_type` (`template_type`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `contract_termination_requests`
--
ALTER TABLE `contract_termination_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `requested_by_user_id` (`requested_by_user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `listing_id` (`listing_id`),
  ADD KEY `user1_id` (`user1_id`),
  ADD KEY `user2_id` (`user2_id`);

--
-- Indexes for table `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_governorate` (`governorate`);

--
-- Indexes for table `listings`
--
ALTER TABLE `listings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_owner_id` (`owner_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_location` (`latitude`,`longitude`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_gender_preference` (`gender_preference`);

--
-- Indexes for table `listing_images`
--
ALTER TABLE `listing_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_listing_id` (`listing_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_listing` (`listing_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_conversation` (`conversation_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contract_id` (`contract_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_type` (`payment_type`),
  ADD KEY `idx_transaction_id` (`transaction_id`);

--
-- Indexes for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_transaction_type` (`transaction_type`);

--
-- Indexes for table `platform_commissions`
--
ALTER TABLE `platform_commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment_id` (`payment_id`),
  ADD KEY `idx_contract_id` (`contract_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reported_user_id` (`reported_user_id`),
  ADD KEY `reported_listing_id` (`reported_listing_id`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `resolved_by` (`resolved_by`),
  ADD KEY `idx_reporter` (`reporter_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `roommate_preferences`
--
ALTER TABLE `roommate_preferences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `saved_listings`
--
ALTER TABLE `saved_listings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_saved` (`user_id`,`listing_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_listing_id` (`listing_id`);

--
-- Indexes for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_gender` (`gender`);

--
-- Indexes for table `verifications`
--
ALTER TABLE `verifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_actions`
--
ALTER TABLE `admin_actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=79;

--
-- AUTO_INCREMENT for table `contract_templates`
--
ALTER TABLE `contract_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contract_termination_requests`
--
ALTER TABLE `contract_termination_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `faculties`
--
ALTER TABLE `faculties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `listings`
--
ALTER TABLE `listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `listing_images`
--
ALTER TABLE `listing_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platform_commissions`
--
ALTER TABLE `platform_commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roommate_preferences`
--
ALTER TABLE `roommate_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `saved_listings`
--
ALTER TABLE `saved_listings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subscriptions`
--
ALTER TABLE `subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `verifications`
--
ALTER TABLE `verifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD CONSTRAINT `admin_actions_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admin_actions_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admin_actions_ibfk_3` FOREIGN KEY (`target_listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `admin_actions_ibfk_4` FOREIGN KEY (`target_report_id`) REFERENCES `reports` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blocked_users`
--
ALTER TABLE `blocked_users`
  ADD CONSTRAINT `blocked_users_ibfk_1` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `blocked_users_ibfk_2` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_ibfk_3` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contract_termination_requests`
--
ALTER TABLE `contract_termination_requests`
  ADD CONSTRAINT `fk_termination_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_termination_requester` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_3` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `listings`
--
ALTER TABLE `listings`
  ADD CONSTRAINT `listings_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `listing_images`
--
ALTER TABLE `listing_images`
  ADD CONSTRAINT `listing_images_ibfk_1` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payment_transactions`
--
ALTER TABLE `payment_transactions`
  ADD CONSTRAINT `payment_transactions_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `platform_commissions`
--
ALTER TABLE `platform_commissions`
  ADD CONSTRAINT `platform_commissions_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `platform_commissions_ibfk_2` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`reported_listing_id`) REFERENCES `listings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_4` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reports_ibfk_5` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `roommate_preferences`
--
ALTER TABLE `roommate_preferences`
  ADD CONSTRAINT `roommate_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_listings`
--
ALTER TABLE `saved_listings`
  ADD CONSTRAINT `saved_listings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saved_listings_ibfk_2` FOREIGN KEY (`listing_id`) REFERENCES `listings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscriptions`
--
ALTER TABLE `subscriptions`
  ADD CONSTRAINT `subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `verifications`
--
ALTER TABLE `verifications`
  ADD CONSTRAINT `verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `verifications_ibfk_2` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
