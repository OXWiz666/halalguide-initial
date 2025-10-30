-- =====================================================
-- Company User Table Creation Script
-- =====================================================
-- Purpose: Separate table for company users/managers
-- Similar structure to tbl_tourist for consistency
-- =====================================================

CREATE TABLE `tbl_company_user` (
  `company_user_id` varchar(25) NOT NULL,
  `company_id` varchar(25) DEFAULT NULL,
  `firstname` varchar(250) DEFAULT NULL,
  `middlename` varchar(250) DEFAULT NULL,
  `lastname` varchar(250) DEFAULT NULL,
  `gender` varchar(15) DEFAULT NULL,
  `contact_no` varchar(15) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL COMMENT 'Job position/title (e.g., Manager, Owner, Admin)',
  `usertype_id` int(11) DEFAULT NULL COMMENT 'Links to tbl_usertype (3=Establishment, 4=Accommodation, 5=Tourist Spot, 6=Prayer Facility)',
  `status_id` int(11) DEFAULT NULL COMMENT 'Links to tbl_status',
  `date_added` datetime DEFAULT NULL,
  `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_user_id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_email` (`email`),
  KEY `idx_status_id` (`status_id`),
  KEY `idx_usertype_id` (`usertype_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores company user/manager personal information';

-- =====================================================
-- Notes:
-- - This table stores personal information of company users/managers
-- - Links to tbl_company via company_id
-- - Can have multiple users per company
-- - Similar structure to tbl_tourist for consistency
-- - Includes position field for job title/role
-- =====================================================

