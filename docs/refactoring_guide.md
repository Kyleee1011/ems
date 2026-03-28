# Refactoring and MVC Migration Guide

This document outlines the new, modernized architecture of the Employee Management System and provides a step-by-step guide for migrating the remaining legacy pages into the new structure.

## 1. Summary of the New Architecture

The core of the application has been refactored to address critical security and maintainability issues. The new structure is based on the **Front Controller** pattern, where all HTTP requests are handled by a single entry point.

### Key Components:

*   **`public/` Directory**: This is the new web server root. Only publicly accessible assets (CSS, JavaScript, images) and the main `index.php` file reside here. This prevents direct web access to sensitive configuration files, scripts, and business logic.
*   **`public/index.php`**: This is the **Front Controller**. Every request goes through this file. It initializes the application, handles routing, and dispatches the request to the appropriate controller.
*   **`.env` File**: All environment-specific configurations, especially sensitive data like database credentials, are now stored in the `.env` file. This file is ignored by Git (`.gitignore`) and should never be committed to version control.
*   **`src/Controllers/`**: This directory contains the controller classes. Controllers are responsible for handling user input, interacting with models (business logic), and selecting a view to render.
*   **`src/Views/`**: This directory contains the view files, which are primarily HTML with PHP tags for displaying data. They should contain minimal business logic.

### Request Lifecycle:

1.  A request arrives at the web server (e.g., `http://localhost:8088/some-page`).
2.  The server directs the request to `public/index.php`.
3.  `index.php` loads the configuration (from `.env`), starts the session, and initializes the router.
4.  The router matches the request URI (`/some-page`) to a specific controller method (e.g., `SomeController@show`).
5.  The controller method is executed. It fetches data from models, performs logic, and then passes the data to a view.
6.  The view is rendered and sent back to the user as an HTML response.

## 2. How to Migrate Legacy Pages

The process for migrating the remaining legacy PHP files (e.g., `leave.php`, `payslip.php`, etc.) follows the pattern established with `home.php`, `login.php`, and `dashboard.php`.

**Let's use `leave.php` as an example:**

### Step 1: Create the Controller

Create a new controller file in `src/Controllers/`, for example, `LeaveController.php`.

```php
<?php

namespace App\Controllers;

class LeaveController
{
    // A constructor can be added to inject dependencies like models
    public function __construct() {
        // require_once BASE_PATH . '/config.php';
        // $this->pdo = $pdo;
        // $this->leaveModel = new LeaveModel($this->pdo);
    }
    
    public function show()
    {
        // 1. Authentication & Authorization check
        // if (!isset($_SESSION['user_id'])) { header('Location: /login'); exit; }

        // 2. Move all data-fetching logic from leave.php here.
        // For example:
        // $pendingLeaves = $this->leaveModel->getLeavesForUser($_SESSION['user_id']);
        
        // 3. Render the view, passing the data.
        $this->render('leave_view', [
            // 'pendingLeaves' => $pendingLeaves,
        ]);
    }

    public function handlePost()
    {
        // 1. Authentication & Authorization check

        // 2. Move POST handling logic from leave.php here
        // For example, logic for submitting a new leave application.

        // 3. Redirect back to the GET route.
        // header('Location: /leave?status=success');
        // exit;
    }

    // You can copy this render method into your new controller for now.
    protected function render(string $view, array $data = [])
    {
        extract($data);
        // You might need to fetch common header data here
        ob_start();
        require_once BASE_PATH . '/partials/_header.php';
        require_once BASE_PATH . "/src/Views/{$view}.php";
        $content = ob_get_clean();
        echo $content;
    }
}
```

### Step 2: Create the View

Create a new view file in `src/Views/`, for example, `leave_view.php`. Copy all the HTML content from the old `leave.php` into this file. Update any form actions and links to use the new absolute URL paths (e.g., `action="/leave"` instead of `action="leave.php"`).

### Step 3: Add the Routes

Open `public/index.php` and add the new routes to the dispatcher.

```php
// In the simpleDispatcher function
$r->addRoute('GET', '/leave', ['App\Controllers\LeaveController', 'show']);
$r->addRoute('POST', '/leave', ['App\Controllers\LeaveController', 'handlePost']);
```

### Step 4: Delete the Old File

Once you have tested that the new `/leave` route works correctly, delete the legacy `leave.php` file from the project root. This is a critical final step.

## 3. Future Development Best Practices

*   **Create a BaseController**: To avoid duplicating the `render` method and authentication checks in every controller, create a `BaseController` that other controllers can `extend`.

    ```php
    // src/Controllers/BaseController.php
    abstract class BaseController {
        public function __construct() {
            if (session_status() === PHP_SESSION_NONE) session_start();
            // Global authentication check can go here
        }

        protected function render(string $view, array $data = []) {
            // ... render logic ...
        }
    }

    // src/Controllers/LeaveController.php
    class LeaveController extends BaseController {
        // ... now you can use $this->render() without defining it.
    }
    ```

*   **Dependency Injection**: The current `DashboardController` has a good constructor that accepts the `$pdo` object. This is a great pattern. Continue to "inject" dependencies like models into your controllers via their constructors instead of creating them inside methods.

*   **AJAX Requests**: For actions that don't require a full page reload (like liking a post), create specific routes (e.g., `POST /api/like-post`). These routes can have controller methods that return JSON data instead of rendering an HTML view.

    ```php
    // In router:
    $r->addRoute('POST', '/api/like', ['App\Controllers\PostController', 'like']);
    
    // In PostController:
    public function like() {
        // ... logic to like a post ...
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'new_like_count' => 123]);
        exit;
    }
    ```

By following this guide, you can systematically and safely modernize the entire application, resulting in a more secure, maintainable, and professional codebase.