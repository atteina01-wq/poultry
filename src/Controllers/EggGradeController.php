<?php
namespace Poultry\Controllers;

use Exception;
use Poultry\Core\Request;
use Poultry\Core\Response;
use Poultry\Services\EggGradeService;

class EggGradeController {
    private EggGradeService $gradeService;

    public function __construct() {
        $this->gradeService = new EggGradeService();
    }

    public function index(Request $request): void {
        try {
            $onlyActive = $request->getQuery('all') !== '1';
            $grades = $this->gradeService->listGrades($onlyActive);
            Response::success($grades, 'Egg grades retrieved');
        } catch (Exception $e) {
            Response::error($e->getMessage());
        }
    }

    public function show(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $grade = $this->gradeService->getGrade($id);
            Response::success($grade, 'Egg grade details');
        } catch (Exception $e) {
            Response::notFound($e->getMessage());
        }
    }

    public function update(Request $request): void {
        try {
            $id = (int)$request->getParam('id');
            $data = $request->getBody();
            $grade = $this->gradeService->updateGrade($id, $data);
            Response::success($grade, 'Egg grade updated');
        } catch (Exception $e) {
            Response::unprocessable($e->getMessage());
        }
    }
}
