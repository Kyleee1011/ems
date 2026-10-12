<?php
namespace App\Services;

class NotificationTypes
{
    // Schedules
    public const SCHEDULE_SUBMITTED = 'SCHEDULE_SUBMITTED';
    public const SCHEDULE_APPROVED = 'SCHEDULE_APPROVED';
    public const SCHEDULE_REJECTED = 'SCHEDULE_REJECTED';

    // Leaves
    public const LEAVE_APPLIED = 'LEAVE_APPLIED';
    public const LEAVE_APPROVED = 'LEAVE_APPROVED';
    public const LEAVE_REJECTED = 'LEAVE_REJECTED';
    public const LEAVE_APPEAL_SUBMITTED = 'LEAVE_APPEAL_SUBMITTED';

    // Overtime
    public const OVERTIME_APPLIED = 'OVERTIME_APPLIED';
    public const OVERTIME_APPROVED = 'OVERTIME_APPROVED';
    public const OVERTIME_REJECTED = 'OVERTIME_REJECTED';

    // Loans
    public const LOAN_CREATED = 'LOAN_CREATED';
    public const LOAN_APPROVED = 'LOAN_APPROVED';
    public const LOAN_REJECTED = 'LOAN_REJECTED';

    // Documents
    public const DOCUMENT_SUBMITTED = 'DOCUMENT_SUBMITTED';
    public const DOCUMENT_APPROVED = 'DOCUMENT_APPROVED';
    public const DOCUMENT_REJECTED = 'DOCUMENT_REJECTED';
    public const DOCUMENT_EXPIRING = 'DOCUMENT_EXPIRING';

    // Payslips
    public const PAYSLIP_READY = 'PAYSLIP_READY';

    // Profile & Org
    public const PROFILE_UPDATED_BY_HR = 'PROFILE_UPDATED_BY_HR';
    public const ORG_CHART_CHANGED = 'ORG_CHART_CHANGED';

    // General Announcement
    public const SYSTEM_ANNOUNCEMENT = 'SYSTEM_ANNOUNCEMENT';
}
