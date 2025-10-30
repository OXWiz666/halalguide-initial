# Company User Table - Documentation

## Overview
A new database table `tbl_company_user` has been created to store company user/manager information separately from the general user account table.

## Table Structure

### `tbl_company_user`
Stores personal information of company users/managers who log in to manage company accounts.

| Column | Type | Description |
|--------|------|-------------|
| `company_user_id` | VARCHAR(25) | Primary key, unique identifier |
| `company_id` | VARCHAR(25) | Foreign key to `tbl_company` |
| `firstname` | VARCHAR(250) | First name |
| `middlename` | VARCHAR(250) | Middle name |
| `lastname` | VARCHAR(250) | Last name |
| `gender` | VARCHAR(15) | Gender |
| `contact_no` | VARCHAR(15) | Contact number |
| `email` | VARCHAR(50) | Email address |
| `position` | VARCHAR(100) | Job position/title (e.g., Manager, Owner, Admin) |
| `usertype_id` | INT(11) | Links to `tbl_usertype` (3=Establishment, 4=Accommodation, 5=Tourist Spot, 6=Prayer Facility) |
| `status_id` | INT(11) | Links to `tbl_status` |
| `date_added` | DATETIME | Registration date |
| `date_updated` | DATETIME | Last update timestamp (auto-updated) |

### Updated: `tbl_useraccount`
Added new column:
- `company_user_id` - Links to `tbl_company_user` instead of just `company_id`

## Key Features

1. **Separate User Profiles**: Company users now have dedicated profiles (similar to `tbl_tourist`)
2. **Multiple Users per Company**: A company can have multiple users/managers
3. **Position Tracking**: Stores job position/title for each user
4. **Better Data Organization**: Separates user personal info from login credentials

## Relationships

```
tbl_company
    ↓
tbl_company_user (can have multiple users)
    ↓
tbl_useraccount (login credentials)
```

## Usage Example

When registering a new company:
1. Create record in `tbl_company`
2. Create record in `tbl_company_user` with user's personal info
3. Create record in `tbl_useraccount` with `company_user_id` linked

## Migration Script

Run `database/run_company_user_migration.php` to:
- Create `tbl_company_user` table
- Add `company_user_id` column to `tbl_useraccount`
- Create necessary indexes

## SQL Files Created

1. `database/create_company_user_table.sql` - Table creation script
2. `database/update_useraccount_for_company_user.sql` - Useraccount update script
3. `database/run_company_user_migration.php` - Automated migration script

