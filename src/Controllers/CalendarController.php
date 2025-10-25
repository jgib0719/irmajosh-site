<?php
declare(strict_types=1);

namespace App\Controllers;

/**
 * CalendarController
 * 
 * Handles calendar view (Google Calendar integration removed)
 */
class CalendarController extends BaseController
{
    /**
     * Show calendar view
     */
    public function index(): void
    {
        $this->requireAuth();
        
        $user = $this->getCurrentUser();
        
        $this->view('calendar', [
            'pageTitle' => t('calendar') . ' - ' . env('APP_NAME'),
            'user' => $user,
        ]);
    }
    
    /**
     * Get events (API endpoint - stubbed, no external calendar integration)
     */
    public function getEvents(): void
    {
        $this->requireAuth();
        
        // Return empty events array - Google Calendar integration removed
        $this->json([]);
    }
    
    /**
     * Create event - stubbed
     */
    public function createEvent(): void
    {
        $this->requireAuth();
        
        $this->json([
            'error' => 'Calendar integration disabled',
            'message' => 'Google Calendar integration has been removed'
        ], 501);
    }
    
    /**
     * Update event - stubbed
     */
    public function updateEvent(array $params): void
    {
        $this->requireAuth();
        
        $this->json([
            'error' => 'Calendar integration disabled',
            'message' => 'Google Calendar integration has been removed'
        ], 501);
    }
    
    /**
     * Delete event - stubbed
     */
    public function deleteEvent(array $params): void
    {
        $this->requireAuth();
        
        $this->json([
            'error' => 'Calendar integration disabled',
            'message' => 'Google Calendar integration has been removed'
        ], 501);
    }
    
    /**
     * Sync calendar - stubbed
     */
    public function sync(): void
    {
        $this->requireAuth();
        
        $this->json([
            'success' => true,
            'message' => 'Google Calendar integration has been removed'
        ]);
    }
}
