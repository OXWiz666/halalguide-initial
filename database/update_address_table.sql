-- Update tbl_address table to support Philippine Address Selector
-- Adds columns for proper region, province, city/municipality codes

-- Check if columns exist before adding
ALTER TABLE `tbl_address` 
ADD COLUMN IF NOT EXISTS `region_code` VARCHAR(10) NULL COMMENT 'Region Code (from refregion)' AFTER `address_id`,
ADD COLUMN IF NOT EXISTS `province_code` VARCHAR(10) NULL COMMENT 'Province Code (from refprovince)' AFTER `region_code`,
ADD COLUMN IF NOT EXISTS `citymunCode` VARCHAR(10) NULL COMMENT 'City/Municipality Code (from refcitymun)' AFTER `province_code`,
ADD COLUMN IF NOT EXISTS `latitude` DECIMAL(10, 8) NULL COMMENT 'GPS Latitude' AFTER `other`,
ADD COLUMN IF NOT EXISTS `longitude` DECIMAL(11, 8) NULL COMMENT 'GPS Longitude' AFTER `latitude`;

-- Update existing brgyCode to be after citymunCode (reordering)
-- Note: brgyCode column should already exist

-- Add indexes for better query performance
ALTER TABLE `tbl_address`
ADD INDEX IF NOT EXISTS `idx_region_code` (`region_code`),
ADD INDEX IF NOT EXISTS `idx_province_code` (`province_code`),
ADD INDEX IF NOT EXISTS `idx_citymunCode` (`citymunCode`),
ADD INDEX IF NOT EXISTS `idx_brgyCode` (`brgyCode`);

-- Add comment to table
ALTER TABLE `tbl_address` COMMENT = 'Stores complete Philippine addresses with region, province, city, and barangay codes';
