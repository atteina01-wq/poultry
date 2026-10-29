<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\ReservationService;

class ReservationController {
    private ReservationService $resService;

    public function __construct() {
        $this->resService = new ReservationService();
    }

    public function index(Request $request): void {
        try {
            $filters = [
                'customer_id' => $request->getQuery('customer_id'),
                'status' => $request->getQuery('status'),
                'pickup_date' => $request->getQuery('pickup_date')
            ];
            $reservations = $this->resService->listReservations($filters);
            Response::success($reservations, 'Reservations retrieved successfully');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function show(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $res = $this->resService->getReservation($id);
            Response::success($res, 'Reservation details retrieved');
        } catch (Exception $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function store(Request $request): void {
        try {
            $data = $request->getBody();
            $userId = $request->getUserId();
            $res = $this->resService->createReservation($data, $userId);
            Response::created($res, 'Reservation created successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function convertToOrder(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $itemPrices = $request->getBody('item_prices', []);
            $userId = $request->getUserId();
            $order = $this->resService->convertToOrder($id, $itemPrices, $userId);
            Response::success($order, 'Reservation converted to confirmed order successfully');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }

    public function updateStatus(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $status = (string)$request->getBody('status', '');
            $userId = $request->getUserId();
            $res = $this->resService->updateStatus($id, $status, $userId);
            Response::success($res, 'Reservation status updated');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }
}
