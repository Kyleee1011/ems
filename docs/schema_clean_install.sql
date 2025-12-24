-- =============================================
-- CLEAN INSTALL SCRIPT
-- Employee Management System Database Schema
-- Run this for a fresh installation
-- =============================================

-- Step 1: Drop database if exists (WARNING: This deletes all data!)
USE master;
GO

IF EXISTS (SELECT name FROM sys.databases WHERE name = 'EmployeeManagementSystem')
BEGIN
    ALTER DATABASE EmployeeManagementSystem SET SINGLE_USER WITH ROLLBACK IMMEDIATE;
    DROP DATABASE EmployeeManagementSystem;
    PRINT 'Existing database dropped.';
END
GO

-- Step 2: Create fresh database
CREATE DATABASE EmployeeManagementSystem;
GO

PRINT 'New database created successfully!';
GO

USE EmployeeManagementSystem;
GO

-- =============================================
-- Departments Table
-- =============================================
CREATE TABLE Departments (
    dept_id INT IDENTITY(1,1) PRIMARY KEY,
    dept_name NVARCHAR(100) NOT NULL,
    dept_code NVARCHAR(20),
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE()
);
GO

-- =============================================
-- Supervisors/Managers Table
-- =============================================
CREATE TABLE Supervisors (
    supervisor_id INT IDENTITY(1,1) PRIMARY KEY,
    supervisor_name NVARCHAR(200) NOT NULL,
    position NVARCHAR(100),
    dept_id INT,
    created_at DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (dept_id) REFERENCES Departments(dept_id)
);
GO

-- =============================================
-- Main Employees Table
-- =============================================
CREATE TABLE Employees (
    emp_id INT IDENTITY(1,1) PRIMARY KEY,
    ac_no NVARCHAR(50) UNIQUE NOT NULL,
    
    -- Name Fields (Full name is computed)
    last_name NVARCHAR(100) NOT NULL,
    first_name NVARCHAR(100) NOT NULL,
    middle_name NVARCHAR(100),
    full_name AS (last_name + ', ' + first_name + ' ' + ISNULL(middle_name, '')) PERSISTED,
    
    -- Personal Information
    date_of_birth DATE,
    gender NVARCHAR(20),
    address NVARCHAR(500),
    contact_number NVARCHAR(50),
    email_address NVARCHAR(100),
    civil_status NVARCHAR(50),
    nationality NVARCHAR(100),
    
    -- Emergency Contact
    emergency_contact_name NVARCHAR(200),
    emergency_contact_number NVARCHAR(50),
    emergency_contact_relationship NVARCHAR(100),
    
    -- Job & Employment Details
    dept_id INT,
    job_title NVARCHAR(150),
    employment_status NVARCHAR(50), -- Regular, Probationary, Contractual, Project-Based
    location_assignment NVARCHAR(200),
    supervisor_id INT,
    employment_type NVARCHAR(50), -- Full-time, Part-time
    work_schedule NVARCHAR(200),
    
    -- Compensation & Payroll
    salary_rate DECIMAL(18,2),
    salary_type NVARCHAR(50), -- Daily, Monthly, Hourly
    payroll_group NVARCHAR(50),
    tin_number NVARCHAR(50),
    sss_number NVARCHAR(50),
    philhealth_number NVARCHAR(50),
    pagibig_number NVARCHAR(50),
    bank_account_number NVARCHAR(100),
    
    -- HR & Records
    date_hired DATE,
    date_deployed DATE,
    date_regularized DATE,
    contract_start_date DATE,
    contract_end_date DATE,
    
    -- Status
    employee_status NVARCHAR(50) DEFAULT 'Active', -- Active, Inactive, Resigned, Terminated
    reason_inactive NVARCHAR(500),
    
    -- Audit Fields
    created_at DATETIME DEFAULT GETDATE(),
    updated_at DATETIME DEFAULT GETDATE(),
    created_by NVARCHAR(100),
    updated_by NVARCHAR(100),
    
    FOREIGN KEY (dept_id) REFERENCES Departments(dept_id),
    FOREIGN KEY (supervisor_id) REFERENCES Supervisors(supervisor_id)
);
GO

-- =============================================
-- Training & Certifications Table
-- =============================================
CREATE TABLE TrainingCertifications (
    training_id INT IDENTITY(1,1) PRIMARY KEY,
    emp_id INT NOT NULL,
    training_name NVARCHAR(200) NOT NULL,
    certification_name NVARCHAR(200),
    date_completed DATE,
    expiry_date DATE,
    provider NVARCHAR(200),
    notes NVARCHAR(500),
    created_at DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (emp_id) REFERENCES Employees(emp_id) ON DELETE CASCADE
);
GO

-- =============================================
-- Employee Status History Table
-- =============================================
CREATE TABLE EmployeeStatusHistory (
    history_id INT IDENTITY(1,1) PRIMARY KEY,
    emp_id INT NOT NULL,
    previous_status NVARCHAR(50),
    new_status NVARCHAR(50),
    effective_date DATE,
    remarks NVARCHAR(500),
    changed_by NVARCHAR(100),
    created_at DATETIME DEFAULT GETDATE(),
    FOREIGN KEY (emp_id) REFERENCES Employees(emp_id) ON DELETE CASCADE
);
GO

-- =============================================
-- Users Table for System Access
-- =============================================
CREATE TABLE Users (
    user_id INT IDENTITY(1,1) PRIMARY KEY,
    username NVARCHAR(100) UNIQUE NOT NULL,
    password_hash NVARCHAR(255) NOT NULL,
    full_name NVARCHAR(200) NOT NULL,
    email NVARCHAR(100),
    role NVARCHAR(50) DEFAULT 'HR', -- HR, Admin, Manager
    is_active BIT DEFAULT 1,
    created_at DATETIME DEFAULT GETDATE(),
    last_login DATETIME
);
GO

-- =============================================
-- Insert Sample Departments
-- =============================================
INSERT INTO Departments (dept_name, dept_code) VALUES
('Human Resources', 'HR'),
('Information Technology', 'IT'),
('Finance', 'FIN'),
('Operations', 'OPS'),
('Sales & Marketing', 'SM'),
('Administration', 'ADMIN'),
('Customer Service', 'CS'),
('Accounting', 'ACCT'),
('Legal', 'LEGAL'),
('Quality Assurance', 'QA');
GO

-- =============================================
-- Insert Sample Supervisors
-- =============================================
INSERT INTO Supervisors (supervisor_name, position, dept_id) VALUES
('Juan Dela Cruz', 'HR Manager', 1),
('Maria Santos', 'IT Manager', 2),
('Pedro Reyes', 'Finance Manager', 3),
('Ana Garcia', 'Operations Manager', 4),
('Carlos Mendoza', 'Sales Manager', 5),
('Isabel Ramos', 'Admin Manager', 6),
('Roberto Cruz', 'CS Manager', 7);
GO

-- =============================================
-- Create Indexes for Performance
-- =============================================
CREATE INDEX idx_emp_status ON Employees(employee_status);
CREATE INDEX idx_emp_dept ON Employees(dept_id);
CREATE INDEX idx_emp_ac_no ON Employees(ac_no);
CREATE INDEX idx_emp_date_hired ON Employees(date_hired);
CREATE INDEX idx_emp_supervisor ON Employees(supervisor_id);
CREATE INDEX idx_emp_full_name ON Employees(full_name);
GO

-- =============================================
-- Create View for Employee List
-- =============================================
CREATE VIEW vw_EmployeeList AS
SELECT 
    e.emp_id,
    e.ac_no,
    e.full_name,
    e.first_name,
    e.last_name,
    e.middle_name,
    e.job_title,
    e.employment_status,
    e.employment_type,
    d.dept_name,
    s.supervisor_name,
    e.contact_number,
    e.email_address,
    e.date_hired,
    e.date_deployed,
    e.date_regularized,
    e.employee_status,
    e.location_assignment,
    e.salary_rate,
    e.salary_type,
    e.created_at,
    e.updated_at
FROM Employees e
LEFT JOIN Departments d ON e.dept_id = d.dept_id
LEFT JOIN Supervisors s ON e.supervisor_id = s.supervisor_id;
GO

-- =============================================
-- Create Stored Procedure for Employee Count
-- =============================================
CREATE PROCEDURE sp_GetEmployeeCount
    @status NVARCHAR(50) = NULL
AS
BEGIN
    IF @status IS NULL
        SELECT COUNT(*) AS total_employees FROM Employees
    ELSE
        SELECT COUNT(*) AS total_employees 
        FROM Employees 
        WHERE employee_status = @status
END;
GO

-- =============================================
-- Create Trigger for Update Timestamp
-- =============================================
CREATE TRIGGER trg_UpdateEmployeeTimestamp
ON Employees
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;
    UPDATE Employees
    SET updated_at = GETDATE()
    FROM Employees e
    INNER JOIN inserted i ON e.emp_id = i.emp_id
END;
GO

-- =============================================
-- Verification Queries
-- =============================================
PRINT '';
PRINT '==============================================';
PRINT 'Database setup completed successfully!';
PRINT '==============================================';
PRINT '';

PRINT 'Tables Created:';
SELECT TABLE_NAME, TABLE_TYPE 
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_TYPE = 'BASE TABLE' 
ORDER BY TABLE_NAME;

PRINT '';
PRINT 'Views Created:';
SELECT TABLE_NAME 
FROM INFORMATION_SCHEMA.VIEWS 
ORDER BY TABLE_NAME;

PRINT '';
PRINT 'Sample Data:';
SELECT COUNT(*) AS DepartmentCount FROM Departments;
SELECT COUNT(*) AS SupervisorCount FROM Supervisors;

PRINT '';
PRINT '==============================================';
PRINT 'Setup Complete! You can now use the system.';
PRINT '==============================================';
GO
