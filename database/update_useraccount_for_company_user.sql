-- =====================================================
-- Update tbl_useraccount to Support Company Users
-- =====================================================
-- Purpose: Add company_user_id field to link useraccount to company_user
-- =====================================================

-- Add company_user_id column to tbl_useraccount
ALTER TABLE `tbl_useraccount` 
ADD COLUMN `company_user_id` varchar(25) DEFAULT NULL AFTER `company_id`,
ADD KEY `idx_company_user_id` (`company_user_id`);

-- =====================================================
-- Notes:
-- - This allows linking useraccount to specific company user
-- - When a company user logs in, they can be identified
-- - company_id remains for backward compatibility
-- - Multiple company users can exist per company
-- =====================================================

