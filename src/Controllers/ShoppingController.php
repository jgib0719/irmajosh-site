<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\ShoppingItem;

class ShoppingController extends BaseController
{
    public function index()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        
        $activeItems = ShoppingItem::getAllActive();
        $completedItems = ShoppingItem::getRecentCompleted(20);
        
        $this->view('shopping-list', [
            'user' => $user,
            'activeItems' => $activeItems,
            'completedItems' => $completedItems
        ]);
    }
    
    public function create()
    {
        $this->requireAuth();
        $user = $this->getCurrentUser();
        
        // Handle both JSON and Form Data
        $name = '';
        if (isset($_POST['item_name'])) {
            $name = trim($_POST['item_name']);
        } else {
            // Fallback for JSON if middleware didn't catch it or if sent differently
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['item_name'])) {
                $name = trim($input['item_name']);
            }
        }
        
        if (empty($name)) {
            $this->json(['error' => 'Item name is required'], 400);
            return;
        }
        
        try {
            $id = ShoppingItem::create((int)$user['id'], $name);
            
            if ($id > 0) {
                $this->json(['success' => true, 'message' => 'Item added', 'id' => $id]);
            } else {
                \logMessage('Shopping create failed: ID returned was ' . var_export($id, true), 'ERROR');
                $this->json(['error' => 'Failed to add item'], 500);
            }
        } catch (\Throwable $e) {
            \logMessage('Shopping create error: ' . $e->getMessage(), 'ERROR');
            $this->json(['error' => 'Server error: ' . $e->getMessage()], 500);
        }
    }
    
    public function toggle(array $params)
    {
        $this->requireAuth();
        
        $id = (int)$params['id'];
        $completed = (bool)($_POST['completed'] ?? false);
        
        if (ShoppingItem::toggle($id, $completed)) {
            $this->json(['success' => true]);
        } else {
            $this->json(['error' => 'Failed to update item'], 500);
        }
    }
    
    public function delete(array $params)
    {
        $this->requireAuth();
        
        $id = (int)$params['id'];
        
        if (ShoppingItem::delete($id)) {
            $this->json(['success' => true]);
        } else {
            $this->json(['error' => 'Failed to delete item'], 500);
        }
    }
}
