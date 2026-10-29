<?php
namespace Poultry\Middleware;

use Poultry\Core\Request;
use Poultry\Core\Response;

class RoleMiddleware {
    protected array $allowedRoles;

    public function __construct(array $allowedRoles = ['admin', 'staff']) {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle(Request $request): void {
        $user = $request->getUser();
        if (!$user || !isset($user['role'])) {
            Response::unauthorized('User not authenticated');
        }

        $userRole = $user['role'];
        if (!in_array($userRole, $this->allowedRoles)) {
            Response::forbidden("Access denied. Required role: " . implode(' or ', $this->allowedRoles));
        }
    }
}
