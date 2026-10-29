<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\UserService;

class UserController {
    private UserService $userService;

    public function __construct() {
        $this->userService = new UserService();
    }

    public function index(Request $request): void {
        try {
            $role = $request->getQuery('role');
            $isActive = $request->getQuery('is_active') !== null ? (int)$request->getQuery('is_active') : null;
            $users = $this->userService->listUsers($role, $isActive);
            Response::success($users, 'Users retrieved successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function show(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $user = $this->userService->getUser($id);
            Response::success($user, 'User details retrieved');
        } catch (Exception $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $adminId = $request->getUserId();
            $user = $this->userService->createUser($data, $adminId);
            Response::created($user, 'User created successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function update(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $data = $request->getBody();
            $adminId = $request->getUserId();
            $user = $this->userService->updateUser($id, $data, $adminId);
            Response::success($user, 'User updated successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function updateRole(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $role = (string)$request->getBody('role', '');
            $adminId = $request->getUserId();
            $user = $this->userService->updateRole($id, $role, $adminId);
            Response::success($user, 'User role updated');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function updateStatus(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $isActive = (int)$request->getBody('is_active', 1);
            $adminId = $request->getUserId();
            $user = $this->userService->updateStatus($id, $isActive, $adminId);
            Response::success($user, 'User status updated');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }
}
