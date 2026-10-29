<?php
namespace Poultry\Services;

use Exception;
use Poultry\Repositories\NotificationRepository;

class NotificationService {
    private NotificationRepository $notifRepo;

    public function __construct() {
        $this->notifRepo = new NotificationRepository();
    }

    public function listNotifications(?int $userId = null, ?string $status = null, int $limit = 50): array {
        return $this->notifRepo->getAll($userId, $status, $limit);
    }

    public function createNotification(array $data): array {
        $message = trim($data['message'] ?? '');
        if (empty($message)) {
            throw new Exception("Notification message is required");
        }
        $id = $this->notifRepo->create($data);
        return [
            'notification_id' => $id,
            'message' => $message
        ];
    }

    public function markAsRead(int $id): bool {
        return $this->notifRepo->markAsRead($id);
    }

    public function markAllAsRead(?int $userId = null): bool {
        return $this->notifRepo->markAllAsRead($userId);
    }
}
