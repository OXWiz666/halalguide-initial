-- SMS Verification Table
-- Stores verification codes for phone number verification

CREATE TABLE IF NOT EXISTS `tbl_sms_verification` (
  `verification_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `phone_number` VARCHAR(20) NOT NULL,
  `verification_code` VARCHAR(6) NOT NULL,
  `useraccount_id` VARCHAR(25) NULL COMMENT 'Linked user account during registration',
  `verification_type` ENUM('registration', 'login', 'password_reset') DEFAULT 'registration',
  `is_verified` TINYINT(1) DEFAULT 0,
  `attempts` INT(11) DEFAULT 0 COMMENT 'Number of verification attempts',
  `expires_at` DATETIME NOT NULL,
  `verified_at` DATETIME NULL,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_phone_number` (`phone_number`),
  INDEX `idx_verification_code` (`verification_code`),
  INDEX `idx_useraccount_id` (`useraccount_id`),
  INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
