<?php
namespace Poultry\Services;

use Poultry\Repositories\DashboardRepository;

class DashboardService {
    private DashboardRepository $dashRepo;

    public function __construct() {
        $this->dashRepo = new DashboardRepository();
    }

    public function getKPIs(): array {
        return $this->dashRepo->getKPIs();
    }

    public function getCharts(): array {
        return $this->dashRepo->getCharts();
    }

    public function getRecentActivities(int $limit = 15): array {
        return $this->dashRepo->getRecentActivities($limit);
    }

    public function getHealthAlerts(?string $asOf = null): array {
        return $this->dashRepo->getHealthAlerts($asOf);
    }
}
