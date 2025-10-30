-- Certification Application System Tables
-- Run this SQL script to create all necessary tables

-- 1. Certification Applications Table
CREATE TABLE IF NOT EXISTS `tbl_certification_application` (
  `application_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `company_id` VARCHAR(25) NOT NULL,
  `organization_id` VARCHAR(25) NOT NULL,
  `application_number` VARCHAR(50) NOT NULL UNIQUE,
  `application_type` ENUM('New', 'Renewal', 'Amendment') DEFAULT 'New',
  `current_status` VARCHAR(50) DEFAULT 'Submitted',
  `submitted_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `reviewed_by` VARCHAR(25) NULL,
  `reviewed_date` DATETIME NULL,
  `approved_by` VARCHAR(25) NULL,
  `approved_date` DATETIME NULL,
  `rejected_by` VARCHAR(25) NULL,
  `rejected_date` DATETIME NULL,
  `rejection_reason` TEXT NULL,
  `certificate_number` VARCHAR(100) NULL,
  `certificate_issue_date` DATE NULL,
  `certificate_expiry_date` DATE NULL,
  `status_id` INT(11) DEFAULT 1,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `date_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`company_id`) REFERENCES `tbl_company`(`company_id`) ON DELETE CASCADE,
  FOREIGN KEY (`organization_id`) REFERENCES `tbl_organization`(`organization_id`),
  FOREIGN KEY (`status_id`) REFERENCES `tbl_status`(`status_id`),
  INDEX `idx_company_id` (`company_id`),
  INDEX `idx_organization_id` (`organization_id`),
  INDEX `idx_current_status` (`current_status`),
  INDEX `idx_submitted_date` (`submitted_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Application Status History (Audit Trail)
CREATE TABLE IF NOT EXISTS `tbl_application_status_history` (
  `history_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `application_id` VARCHAR(25) NOT NULL,
  `previous_status` VARCHAR(50) NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `changed_by` VARCHAR(25) NOT NULL,
  `changed_by_type` ENUM('Admin', 'Certifying Body', 'System') DEFAULT 'Admin',
  `change_reason` TEXT NULL,
  `notes` TEXT NULL,
  `date_changed` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `tbl_certification_application`(`application_id`) ON DELETE CASCADE,
  INDEX `idx_application_id` (`application_id`),
  INDEX `idx_date_changed` (`date_changed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Application Documents
CREATE TABLE IF NOT EXISTS `tbl_application_documents` (
  `document_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `application_id` VARCHAR(25) NOT NULL,
  `document_type` VARCHAR(100) NOT NULL,
  `document_name` VARCHAR(255) NOT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `file_size` INT(11) NULL,
  `file_type` VARCHAR(50) NULL,
  `is_required` TINYINT(1) DEFAULT 1,
  `upload_status` ENUM('Uploaded', 'Pending', 'Rejected') DEFAULT 'Uploaded',
  `rejection_reason` TEXT NULL,
  `reviewed_by` VARCHAR(25) NULL,
  `reviewed_date` DATETIME NULL,
  `version_number` INT(11) DEFAULT 1,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `tbl_certification_application`(`application_id`) ON DELETE CASCADE,
  INDEX `idx_application_id` (`application_id`),
  INDEX `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Site Visit Management
CREATE TABLE IF NOT EXISTS `tbl_application_visits` (
  `visit_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `application_id` VARCHAR(25) NOT NULL,
  `visit_type` ENUM('Initial', 'Follow-up', 'Final') DEFAULT 'Initial',
  `scheduled_date` DATETIME NOT NULL,
  `scheduled_by` VARCHAR(25) NOT NULL,
  `assigned_to` VARCHAR(25) NULL,
  `visit_status` ENUM('Scheduled', 'In Progress', 'Completed', 'Cancelled', 'Rescheduled') DEFAULT 'Scheduled',
  `actual_visit_date` DATETIME NULL,
  `visit_report_path` VARCHAR(500) NULL,
  `visit_findings` TEXT NULL,
  `compliance_score` INT(3) NULL COMMENT 'Score out of 100',
  `needs_followup` TINYINT(1) DEFAULT 0,
  `followup_visit_id` VARCHAR(25) NULL,
  `notes` TEXT NULL,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `date_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `tbl_certification_application`(`application_id`) ON DELETE CASCADE,
  FOREIGN KEY (`followup_visit_id`) REFERENCES `tbl_application_visits`(`visit_id`) ON DELETE SET NULL,
  INDEX `idx_application_id` (`application_id`),
  INDEX `idx_scheduled_date` (`scheduled_date`),
  INDEX `idx_visit_status` (`visit_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Required Documents Checklist (Configuration)
CREATE TABLE IF NOT EXISTS `tbl_document_checklist` (
  `checklist_id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `document_type` VARCHAR(100) NOT NULL UNIQUE,
  `document_name` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `is_required` TINYINT(1) DEFAULT 1,
  `file_types_allowed` VARCHAR(255) NULL COMMENT 'Comma-separated: pdf,doc,docx,jpg',
  `max_file_size_mb` INT(11) DEFAULT 10,
  `category` VARCHAR(50) NULL COMMENT 'Legal, Financial, Operational, etc.',
  `display_order` INT(11) DEFAULT 0,
  `status_id` INT(11) DEFAULT 1,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_category` (`category`),
  INDEX `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Default Document Checklist
INSERT INTO `tbl_document_checklist` (`document_type`, `document_name`, `description`, `is_required`, `file_types_allowed`, `max_file_size_mb`, `category`, `display_order`) VALUES
('business_permit', 'Business Permit', 'Valid business registration permit', 1, 'pdf,jpg,png', 5, 'Legal', 1),
('dti_sec_registration', 'DTI/SEC Registration', 'Department of Trade and Industry or SEC registration certificate', 1, 'pdf,jpg,png', 5, 'Legal', 2),
('halal_compliance_policy', 'Halal Compliance Policy', 'Company halal compliance policy document', 1, 'pdf,doc,docx', 10, 'Operational', 3),
('product_list', 'Product/Service List', 'Complete list of products or services to be certified', 1, 'pdf,doc,docx,xls,xlsx', 10, 'Operational', 4),
('ingredient_specification', 'Ingredient Specification Sheet', 'Detailed specification of all ingredients', 1, 'pdf,doc,docx,xls,xlsx', 10, 'Operational', 5),
('supplier_list', 'Supplier List', 'List of all suppliers with halal certificates', 1, 'pdf,doc,docx,xls,xlsx', 5, 'Operational', 6),
('floor_plan', 'Floor Plan', 'Layout of premises showing production/service areas', 1, 'pdf,jpg,png', 10, 'Operational', 7),
('previous_certificate', 'Previous Halal Certificate (if renewal)', 'Copy of previous halal certification', 0, 'pdf,jpg,png', 5, 'Legal', 8),
('other_documents', 'Other Supporting Documents', 'Any other relevant documents', 0, 'pdf,doc,docx,jpg,png', 10, 'Legal', 9);

-- 6.1. Application Form (details filled by company upon applying)
CREATE TABLE IF NOT EXISTS `tbl_application_form` (
  `application_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `application_date` DATE NULL,
  `business_address` TEXT NULL,
  `landline` VARCHAR(50) NULL,
  `fax_no` VARCHAR(50) NULL,
  `application_email` VARCHAR(255) NULL,
  `application_contact` VARCHAR(50) NULL,
  `contact_person` VARCHAR(255) NULL,
  `contact_position` VARCHAR(100) NULL,
  `legal_personality` VARCHAR(50) NULL,
  `category` VARCHAR(50) NULL,
  `business_food` VARCHAR(255) NULL,
  `business_nonfood` VARCHAR(255) NULL,
  `product_a` VARCHAR(255) NULL,
  `product_b` VARCHAR(255) NULL,
  `product_c` VARCHAR(255) NULL,
  `product_porkfree` TINYINT(1) DEFAULT 0,
  `product_meatfree` TINYINT(1) DEFAULT 0,
  `product_alcoholfree` TINYINT(1) DEFAULT 0,
  `applicant_position` VARCHAR(100) NULL,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `tbl_certification_application`(`application_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Applicant Evaluation Checklist
CREATE TABLE IF NOT EXISTS `tbl_application_evaluation` (
  `evaluation_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `application_id` VARCHAR(25) NOT NULL,
  `evaluated_by` VARCHAR(25) NOT NULL,
  `evaluation_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `company_name` VARCHAR(255) NULL,
  `company_address` TEXT NULL,
  `nature_of_business` TEXT NULL,
  `product_lines` TEXT NULL,
  `scope` TEXT NULL,
  `comments_recommendation` TEXT NULL,
  `evaluated_by_name` VARCHAR(255) NULL,
  `evaluated_by_position` VARCHAR(100) NULL,
  `reviewed_by_name` VARCHAR(255) NULL,
  `reviewed_by_position` VARCHAR(100) NULL,
  `noted_by_name` VARCHAR(255) NULL,
  `noted_by_position` VARCHAR(100) NULL,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `date_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `tbl_certification_application`(`application_id`) ON DELETE CASCADE,
  INDEX `idx_application_id` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Evaluation Checklist Items (Particulars)
CREATE TABLE IF NOT EXISTS `tbl_evaluation_checklist_items` (
  `checklist_item_id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `evaluation_id` VARCHAR(25) NOT NULL,
  `item_number` INT(11) NOT NULL,
  `particular` TEXT NOT NULL,
  `sub_items` TEXT NULL COMMENT 'JSON array of sub-items',
  `answer` ENUM('Yes', 'No', 'N/A') NULL,
  `remarks` TEXT NULL,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`evaluation_id`) REFERENCES `tbl_application_evaluation`(`evaluation_id`) ON DELETE CASCADE,
  INDEX `idx_evaluation_id` (`evaluation_id`),
  INDEX `idx_item_number` (`item_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Application Notifications
CREATE TABLE IF NOT EXISTS `tbl_application_notifications` (
  `notification_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `application_id` VARCHAR(25) NOT NULL,
  `notification_type` VARCHAR(50) NOT NULL COMMENT 'Status Change, Visit Scheduled, Document Required, etc.',
  `recipient_type` ENUM('Company', 'Certifying Body') DEFAULT 'Company',
  `recipient_id` VARCHAR(25) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) DEFAULT 0,
  `read_date` DATETIME NULL,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`application_id`) REFERENCES `tbl_certification_application`(`application_id`) ON DELETE CASCADE,
  INDEX `idx_application_id` (`application_id`),
  INDEX `idx_recipient` (`recipient_type`, `recipient_id`),
  INDEX `idx_is_read` (`is_read`),
  INDEX `idx_date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

