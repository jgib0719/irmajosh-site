<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\DateIdea;
use App\Models\DateCategory;

class DateNightController extends BaseController
{
    public function index()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        
        $categories = DateCategory::all();
        $ideas = DateIdea::getAll((int)$user['id']);
        $history = DateIdea::getHistory((int)$user['id']);
        $totalPoints = DateIdea::getTotalCouplesPoints();
        
        // Calculate level based on points (e.g., every 1000 points is a level)
        $level = floor($totalPoints / 1000) + 1;
        $nextLevelPoints = $level * 1000;
        $progress = (($totalPoints % 1000) / 1000) * 100;

        $this->view('date-night', [
            'user' => $user,
            'categories' => $categories,
            'ideas' => $ideas,
            'history' => $history,
            'totalPoints' => $totalPoints,
            'level' => $level,
            'nextLevelPoints' => $nextLevelPoints,
            'progress' => $progress
        ]);
    }

    public function complete()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        
        $ideaId = (int)($_POST['date_idea_id'] ?? 0);
        if (!$ideaId) {
            $this->json(['error' => 'Invalid date idea'], 400);
            return;
        }

        $data = [
            'rating' => $_POST['rating'] ?? null,
            'notes' => $_POST['notes'] ?? null,
            'photo_url' => $_POST['photo_url'] ?? null
        ];

        if (DateIdea::complete((int)$user['id'], $ideaId, $data)) {
            $this->json(['success' => true, 'message' => 'Date completed! Points awarded.']);
        } else {
            $this->json(['error' => 'Failed to complete date'], 500);
        }
    }
    
    public function create()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        
        $data = [
            'title' => $_POST['title'] ?? '',
            'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            'description' => $_POST['description'] ?? '',
            'url' => $_POST['url'] ?? '',
            'cost_level' => (int)($_POST['cost_level'] ?? 1),
            'season' => $_POST['season'] ?? 'Any',
            'points_value' => 100
        ];
        
        if (empty($data['title'])) {
            $this->json(['error' => 'Title is required'], 400);
            return;
        }
        
        $id = DateIdea::create($data);
        
        if ($id) {
            $this->json(['success' => true, 'message' => 'Date idea added!', 'id' => $id]);
        } else {
            $this->json(['error' => 'Failed to create date idea'], 500);
        }
    }
}
