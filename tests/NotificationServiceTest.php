<?php

use PHPUnit\Framework\TestCase;
use App\Services\NotificationService;
use App\Services\NotificationTypes;

class NotificationServiceTest extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec("
            CREATE TABLE notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                recipient_user_id INTEGER NOT NULL,
                type VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                body TEXT NULL,
                link VARCHAR(500) NULL,
                is_read INTEGER DEFAULT 0,
                read_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE employees (
                emp_id INTEGER PRIMARY KEY AUTOINCREMENT,
                ac_no VARCHAR(50) NOT NULL,
                approval_role VARCHAR(50) NOT NULL,
                dept_id INTEGER NULL,
                IsActive INTEGER DEFAULT 1,
                employee_status VARCHAR(50) DEFAULT 'Active'
            );
        ");

        NotificationService::init($this->pdo);
    }

    public function testNotifyCreatesSingleNotification(): void
    {
        $ok = NotificationService::notify(
            5,
            NotificationTypes::SCHEDULE_SUBMITTED,
            'Schedule Submitted',
            'Schedule for Dept A has been submitted',
            'schedule'
        );

        $this->assertTrue($ok);
        $this->assertEquals(1, NotificationService::getUnreadCount(5));

        $res = NotificationService::getForUser(5);
        $this->assertEquals(1, $res['total']);
        $this->assertEquals('Schedule Submitted', $res['items'][0]['title']);
        $this->assertEquals(0, $res['items'][0]['is_read']);
    }

    public function testMarkReadUpdatesStatus(): void
    {
        NotificationService::notify(10, NotificationTypes::LEAVE_APPROVED, 'Leave Approved');
        $res = NotificationService::getForUser(10);
        $notifId = (int)$res['items'][0]['id'];

        $this->assertEquals(1, NotificationService::getUnreadCount(10));

        $marked = NotificationService::markRead($notifId, 10);
        $this->assertTrue($marked);
        $this->assertEquals(0, NotificationService::getUnreadCount(10));
    }

    public function testMarkAllRead(): void
    {
        NotificationService::notify(12, NotificationTypes::LOAN_CREATED, 'Loan 1');
        NotificationService::notify(12, NotificationTypes::LOAN_APPROVED, 'Loan 2');

        $this->assertEquals(2, NotificationService::getUnreadCount(12));

        $count = NotificationService::markAllRead(12);
        $this->assertEquals(2, $count);
        $this->assertEquals(0, NotificationService::getUnreadCount(12));
    }

    public function testNotifyRoleDispatchesToAllActiveMatchingUsers(): void
    {
        $this->pdo->exec("
            INSERT INTO employees (emp_id, ac_no, approval_role, IsActive, employee_status) VALUES
            (101, 'HR01', 'HR', 1, 'Active'),
            (102, 'HR02', 'HR', 1, 'Active'),
            (103, 'EMP01', 'Employee', 1, 'Active'),
            (104, 'HR03', 'HR', 0, 'Inactive')
        ");

        $dispatched = NotificationService::notifyRole(
            'HR',
            NotificationTypes::LEAVE_APPLIED,
            'New Leave Application',
            'EMP01 applied for VL'
        );

        $this->assertEquals(2, $dispatched);
        $this->assertEquals(1, NotificationService::getUnreadCount(101));
        $this->assertEquals(1, NotificationService::getUnreadCount(102));
        $this->assertEquals(0, NotificationService::getUnreadCount(103)); // Not HR
        $this->assertEquals(0, NotificationService::getUnreadCount(104)); // Inactive
    }
}
