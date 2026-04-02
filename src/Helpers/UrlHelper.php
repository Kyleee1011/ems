<?php

if (!function_exists('baseUrl')) {
    /**
     * Get the base URL for the application.
     *
     * @param string $path
     * @return string
     */
    function baseUrl($path = '')
    {
        // Remove leading/trailing slashes from the path
        $path = trim($path, '/');

        return BASE_URL . '/' . $path;
    }
}

if (!function_exists('getDashboardUrl')) {
    /**
     * Get the correct dashboard URL based on user role.
     *
     * @return string
     */
    function getDashboardUrl()
    {
        $role = trim($_SESSION['approval_role'] ?? 'Employee');
        $dept_id = $_SESSION['dept_id'] ?? 0;

        // HR Portal access: If role is HR OR user is in HR Department (dept_id 5)
        if (strcasecmp($role, 'HR') === 0 || $dept_id == 5) {
            return 'dashboard';
        } elseif (strcasecmp($role, 'CEO') === 0) {
            return 'ceodashboard';
        }

        return 'home';
    }
}

if (!function_exists('csrfField')) {
    /**
     * Generate a hidden CSRF input field.
     *
     * @return string
     */
    function csrfField()
    {
        $token = \App\Utils\AppHelpers::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}
