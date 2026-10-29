<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\NotificationService;

class NotificationController {
    private NotificationService $notifService;

    public function __construct() {
        $this->notifService = new NotificationService();
    }

    public function index(Request $request): void {
        try {
            $userId = $request->getUserId();
            $status = $request->getQuery('status');
            $limit = (int)$request->getQuery('limit', 50);
            $notifications = $this->notifService->listNotifications($userId, $status, $limit);
            Response::success($notifications, 'Notifications retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function markAsRead(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $this->notifService->markAsRead($id);
            Response::success(null, 'Notification marked as read');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function markAllAsRead(Request $request): void {
        try {
            $userId = $request->getUserId();
            $this->notifService->markAllAsRead($userId);
            Response::success(null, 'All notifications marked as read');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }
}
