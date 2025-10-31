<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * BaseController
 * 
 * Base controller with common functionality
 */
abstract class BaseController
{
    /**
     * Render a view
     */
    protected function view(string $template, array $data = []): void
    {
        view($template, $data);
    }
    
    /**
     * Return JSON response
     */
    protected function json(mixed $data, int $status = 200): void
    {
        json($data, $status);
    }
    
    /**
     * Redirect to URL
     */
    protected function redirect(string $url, int $status = 302): void
    {
        redirect($url, $status);
    }
    
    /**
     * Get current user
     */
    protected function getCurrentUser(): ?array
    {
        return currentUser();
    }
    
    /**
     * Require authentication
     */
    protected function requireAuth(): void
    {
        requireAuth();
    }
    
    /**
     * Set flash message
     */
    protected function setFlash(string $type, string $message): void
    {
        setFlash($type, $message);
    }
    
    /**
     * Get POST data
     */
    protected function getPost(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }
    
    /**
     * Get GET data
     */
    protected function getQuery(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
    
    /**
     * Get all POST data
     */
    protected function getAllPost(): array
    {
        return $_POST;
    }
    
    /**
     * Get all GET data
     */
    protected function getAllQuery(): array
    {
        return $_GET;
    }
    
    /**
     * Get JSON input from request body
     */
    protected function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        if (empty($json)) {
            return [];
        }
        
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }
        
        return $data ?? [];
    }
    
    /**
     * Validate required fields
     */
    protected function validateRequired(array $fields, array $data): bool
    {
        foreach ($fields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Send HTMX response with alert
     */
    protected function htmxResponse(string $html, string $alertMessage = '', string $alertType = 'success'): void
    {
        if ($alertMessage) {
            header("X-Alert-Message: {$alertMessage}");
            header("X-Alert-Type: {$alertType}");
        }
        
        echo $html;
        exit;
    }
    
    /**
     * Check if request is HTMX
     */
    protected function isHtmx(): bool
    {
        return isAjaxRequest();
    }
}
