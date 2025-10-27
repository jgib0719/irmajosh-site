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
     * Get events (API endpoint - returns empty array, no external calendar integration)
     */
    public function getEvents(): void
    {
        $this->requireAuth();
        
        // Return empty events array since Google Calendar integration was removed
        // In the future, this could return events from a local database table
        $this->json([]);
    }
    
    /**
     * Create event - stubbed (returns success but doesn't persist)
     */
    public function createEvent(): void
    {
        $this->requireAuth();
        
        // Accept the request but don't persist anywhere
        // In the future, could save to local database table
        $this->json([
            'success' => true,
            'message' => 'Event creation not yet implemented'
        ]);
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
     * Sync calendar - stubbed (returns success but does nothing)
     */
    public function sync(): void
    {
        $this->requireAuth();
        
        // Accept the request but don't sync anything
        $this->json([
            'success' => true,
            'message' => 'Calendar sync not yet implemented'
        ]);
    }
}
