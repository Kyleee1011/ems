<?php
namespace App\Services;

class AuditActions
{
    // Auth
    public const LOGIN_SUCCESS = 'LOGIN_SUCCESS';
    public const LOGIN_FAILED = 'LOGIN_FAILED';
    public const LOGOUT = 'LOGOUT';

    // Employee
    public const EMPLOYEE_CREATED = 'EMPLOYEE_CREATED';
    public const EMPLOYEE_UPDATED = 'EMPLOYEE_UPDATED';
    public const EMPLOYEE_DELETED = 'EMPLOYEE_DELETED';
    public const EMPLOYEE_RESTORED = 'EMPLOYEE_RESTORED';
    public const EMPLOYEE_HARD_DELETED = 'EMPLOYEE_HARD_DELETED';
    public const EMPLOYEE_PHOTO_UPDATED = 'EMPLOYEE_PHOTO_UPDATED';
    public const PROFILE_SELF_UPDATED = 'PROFILE_SELF_UPDATED';

    // Schedule
    public const SCHEDULE_CREATED = 'SCHEDULE_CREATED';
    public const SCHEDULE_SUBMITTED = 'SCHEDULE_SUBMITTED';
    public const SCHEDULE_APPROVED = 'SCHEDULE_APPROVED';
    public const SCHEDULE_REJECTED = 'SCHEDULE_REJECTED';
    public const SCHEDULE_SIGNED = 'SCHEDULE_SIGNED';
    public const SIGNATURE_VERIFIED = 'SIGNATURE_VERIFIED';
    public const SIGNATURE_VERIFICATION_FAILED = 'SIGNATURE_VERIFICATION_FAILED';

    // Leave
    public const LEAVE_APPLIED = 'LEAVE_APPLIED';
    public const LEAVE_APPROVED = 'LEAVE_APPROVED';
    public const LEAVE_REJECTED = 'LEAVE_REJECTED';

    // Overtime
    public const OVERTIME_APPLIED = 'OVERTIME_APPLIED';
    public const OVERTIME_APPROVED = 'OVERTIME_APPROVED';
    public const OVERTIME_REJECTED = 'OVERTIME_REJECTED';

    // Loan
    public const LOAN_CREATED = 'LOAN_CREATED';
    public const LOAN_HR_APPROVED = 'LOAN_HR_APPROVED';
    public const LOAN_CEO_APPROVED = 'LOAN_CEO_APPROVED';
    public const LOAN_REJECTED = 'LOAN_REJECTED';

    // Document Checklist
    public const DOCUMENT_UPLOADED = 'DOCUMENT_UPLOADED';
    public const DOCUMENT_DELETED = 'DOCUMENT_DELETED';
    public const DOCUMENT_APPROVED = 'DOCUMENT_APPROVED';
    public const DOCUMENT_REJECTED = 'DOCUMENT_REJECTED';
    public const DOCUMENT_DOWNLOADED = 'DOCUMENT_DOWNLOADED';

    // Payslip
    public const PAYSLIP_GENERATED = 'PAYSLIP_GENERATED';
    public const PAYSLIP_BATCH_GENERATED = 'PAYSLIP_BATCH_GENERATED';
    public const PAYSLIP_PUBLISHED = 'PAYSLIP_PUBLISHED';
    public const PAYSLIP_VIEWED = 'PAYSLIP_VIEWED';
    public const PAYSLIP_DOWNLOADED = 'PAYSLIP_DOWNLOADED';

    // Payroll Config
    public const PAYROLL_CONFIG_UPDATED = 'PAYROLL_CONFIG_UPDATED';

    // Org Chart
    public const ORG_CHART_UPDATED = 'ORG_CHART_UPDATED';

    // System
    public const EXPORT_CSV = 'EXPORT_CSV';
}
