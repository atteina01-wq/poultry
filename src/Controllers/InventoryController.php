<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\InventoryService;

class InventoryController {
    private InventoryService $invService;

    public function __construct() {
        $this->invService = new InventoryService();
    }

    public function index(Request $request): void {
        try {
            $category = $request->getQuery('category');
            $onlyActive = $request->getQuery('all') !== '1';
            $items = $this->invService->listItems($category, $onlyActive);
            Response::success($items, 'Inventory items retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function show(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $item = $this->invService->getItem($id);
            Response::success($item, 'Inventory item details');
        } catch (Exception $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $item = $this->invService->createItem($data, $userId);
            Response::created($item, 'Inventory item saved successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function update(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $data = $request->getBody();
            $userId = $request->getUserId();
            $item = $this->invService->updateItem($id, $data, $userId);
            Response::success($item, 'Inventory item updated');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function expiring(Request $request): void {
        try {
            $days = $request->getQuery('days') !== null ? (int)$request->getQuery('days') : null;
            $lots = $this->invService->getExpiringLots($days);
            Response::success($lots, 'Expiring inventory lots retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
