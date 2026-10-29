<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Repositories\AuditLogRepository;

class AuditLogController {
    private AuditLogRepository $auditRepo;

    public function __construct() {
        $this->auditRepo = new AuditLogRepository();
    }

    public function index(Request $request): void {
        try {
            $filters = [
                'user_id' => $request->getQuery('user_id'),
                'table_name' => $request->getQuery('table_name'),
                'action' => $request->getQuery('action'),
                'date_from' => $request->getQuery('date_from'),
                'date_to' => $request->getQuery('date_to')
            ];
            $limit = (int)$request->getQuery('limit', 100);
            $offset = (int)$request->getQuery('offset', 0);
            $logs = $this->auditRepo->getAll($filters, $limit, $offset);
            Response::success($logs, 'System audit logs retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }
}
