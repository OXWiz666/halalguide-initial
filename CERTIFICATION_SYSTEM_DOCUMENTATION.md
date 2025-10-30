# Certification Application System - Documentation

## Overview
A comprehensive Certification Application System built for the HCB (Halal Certifying Body) portal that manages the complete lifecycle of halal certification applications from submission to final approval/rejection.

## Features Implemented

### 1. Stateful Status Management ✅
- **Status Workflow**: 
  - `Submitted` → `Under Review` → `Scheduled for Visit` → `Final Review` → `Approved/Rejected`
- **Automated Status Transitions**: Status changes trigger:
  - Database updates with timestamps
  - Complete audit trail in `tbl_application_status_history`
  - Automated notifications to applicants
- **Status History**: Complete audit trail showing:
  - Previous and new status
  - Who made the change
  - When it was changed
  - Reason for change

### 2. Document Upload & Validation System ✅
- **Document Checklist**: 
  - Configurable required documents via `tbl_document_checklist`
  - Pre-populated with common documents (Business Permit, DTI/SEC, Halal Policy, etc.)
  - Real-time tracking of uploaded vs. missing documents
- **Document Management**:
  - File type validation
  - File size limits per document type
  - Version control with upload history
  - Document review workflow (Approve/Reject)
- **Visual Indicators**:
  - Green border for uploaded documents
  - Orange border for pending documents
  - Red border for rejected documents

### 3. Site Visit Management ✅
- **Visit Scheduling**:
  - Calendar integration for scheduling
  - Support for Initial, Follow-up, and Final visits
  - Automated status update when visit is scheduled
- **Visit Tracking**:
  - Visit status (Scheduled, In Progress, Completed, Cancelled, Rescheduled)
  - Compliance score tracking (0-100)
  - Visit findings and notes
  - Follow-up visit dependency tracking
- **Notifications**: Automated email/notification when visit is scheduled

### 4. Final Review & Decision Workflow ✅
- **Approval Process**:
  - Certificate number generation (auto or manual)
  - Issue date and expiry date setting
  - Automatic company status update to "Halal-Certified"
  - Certificate expiry tracking
- **Rejection Process**:
  - Reason codes for rejection
  - Comprehensive feedback system
  - Appeal/reapplication process support
- **Notifications**: Automated notifications for both approval and rejection

## Database Tables

### Core Tables

1. **tbl_certification_application**
   - Main application table
   - Tracks status, dates, reviewers, certificate info
   - Links to company and organization

2. **tbl_application_status_history**
   - Complete audit trail
   - Every status change is logged with user, timestamp, and reason

3. **tbl_application_documents**
   - Document storage with metadata
   - Review status tracking
   - Version control

4. **tbl_application_visits**
   - Site visit scheduling and tracking
   - Visit reports and findings
   - Compliance scoring

5. **tbl_document_checklist**
   - Configurable required documents
   - File type and size validation rules
   - Display order management

6. **tbl_application_notifications**
   - Automated notifications system
   - Read/unread tracking
   - Recipient management

## Files Created

1. **database/create_certification_system_tables.sql**
   - SQL script to create all required tables
   - Includes default document checklist

2. **database/run_certification_migration.php**
   - PHP script to run the migration
   - Error handling and reporting

3. **hcb/applications.php**
   - Main applications listing page
   - Status filtering
   - Quick actions (Update Status, Schedule Visit, Final Decision)
   - Modal-based workflows

4. **hcb/application-details.php**
   - Detailed application view
   - Document checklist and review
   - Site visits timeline
   - Status history
   - Application timeline visualization

5. **hcb/includes/sidebar.php**
   - Reusable sidebar component
   - Active page highlighting
   - Consistent navigation

## Setup Instructions

### Step 1: Run Database Migration
```bash
php database/run_certification_migration.php
```

Or manually import:
```sql
SOURCE database/create_certification_system_tables.sql;
```

### Step 2: Verify Tables
Ensure all 6 tables are created:
- tbl_certification_application
- tbl_application_status_history
- tbl_application_documents
- tbl_application_visits
- tbl_document_checklist
- tbl_application_notifications

### Step 3: Access Applications
Navigate to: `hcb/applications.php`

## Status Workflow

```
Submitted
    ↓
Under Review (Manual transition)
    ↓
Scheduled for Visit (Automatic when visit scheduled)
    ↓
Final Review (Manual transition after visit completion)
    ↓
Approved / Rejected
```

## Next Steps (To Be Implemented)

1. **Company-Side Application Submission**
   - Form for companies to submit applications
   - Document upload interface
   - Application tracking dashboard

2. **Enhanced Document Management**
   - Bulk document upload
   - Document preview
   - Document comparison (version control)

3. **Calendar View for Visits**
   - Full calendar integration
   - Drag-and-drop scheduling
   - Visit conflict detection

4. **Advanced Reporting**
   - Application statistics
   - Processing time analytics
   - Compliance score trends

5. **Email Notifications**
   - Automated email sending
   - Notification preferences
   - Email templates

## Security Features

- SQL injection prevention (mysqli_real_escape_string)
- Transaction-based updates for data integrity
- Access control via session management
- Organization-based data isolation
- File upload validation

## UI/UX Features

- Clean, modern interface
- Status badges with color coding
- Filter tabs for quick navigation
- Modal-based workflows
- Responsive design
- Toast notifications (SweetAlert2)
- Timeline visualization
- Real-time document tracking

