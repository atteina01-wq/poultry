<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MortalityRecord;
use App\Models\Batch;
use App\Models\Expense;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class MortalityController extends Controller
{
    public function index(Request $request)
    {
        $batchFilter = (int)$request->query('batch_filter', $request->query('batch_id', 0));
        $month = (int)$request->query('month', date('n'));
        $year = (int)$request->query('year', date('Y'));
        $summaryPeriod = $request->query('summary_period', 'daily');

        // Check count of records in current view
        $viewWhereQuery = MortalityRecord::where('quantity_change', '<', 0)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($batchFilter > 0) {
            $viewWhereQuery->where('batch_id', $batchFilter);
        }
        $viewCount = $viewWhereQuery->count();

        // Latest month with records for fallback link
        $latestRecord = MortalityRecord::where('quantity_change', '<', 0)
            ->orderBy('date', 'desc')
            ->first();
        $latestM = $latestRecord ? (int)date('n', strtotime($latestRecord->date)) : null;
        $latestY = $latestRecord ? (int)date('Y', strtotime($latestRecord->date)) : null;

        // Calendar calculations
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $firstDay = (int)date('w', strtotime(sprintf('%04d-%02d-01', $year, $month)));

        // Preload day records for calendar
        $calendarQuery = MortalityRecord::with('batch')
            ->where('quantity_change', '<', 0)
            ->whereYear('date', $year)
            ->whereMonth('date', $month);

        if ($batchFilter > 0) {
            $calendarQuery->where('batch_id', $batchFilter);
        }

        $allDayRecords = $calendarQuery->get();
        $dayRecordsMap = [];
        foreach ($allDayRecords as $rec) {
            $d = date('Y-m-d', strtotime($rec->date));
            if (!isset($dayRecordsMap[$d])) {
                $dayRecordsMap[$d] = [];
            }
            $dayRecordsMap[$d][] = [
                'quantity_change' => (int)$rec->quantity_change,
                'reason' => $rec->reason,
                'financial_value' => (float)$rec->financial_value,
                'batch_code' => $rec->batch->batch_code ?? 'Batch #' . $rec->batch_id,
            ];
        }

        // Summary grouping
        $summaryQuery = DB::table('mortality_records')
            ->where('quantity_change', '<', 0);
        if ($batchFilter > 0) {
            $summaryQuery->where('batch_id', $batchFilter);
        }

        switch ($summaryPeriod) {
            case 'weekly':
                $summaryQuery->selectRaw("MIN(date) as period_start, YEARWEEK(date, 1) as period_label, ABS(SUM(quantity_change)) as total_loss, COALESCE(SUM(financial_value), 0) as total_financial_loss")
                    ->groupByRaw("YEARWEEK(date, 1)")
                    ->orderByRaw("period_start DESC");
                break;
            case 'monthly':
                $summaryQuery->selectRaw("MIN(date) as period_start, DATE_FORMAT(date, '%Y-%m') as period_label, ABS(SUM(quantity_change)) as total_loss, COALESCE(SUM(financial_value), 0) as total_financial_loss")
                    ->groupByRaw("DATE_FORMAT(date, '%Y-%m')")
                    ->orderByRaw("period_start DESC");
                break;
            case 'yearly':
                $summaryQuery->selectRaw("MIN(date) as period_start, YEAR(date) as period_label, ABS(SUM(quantity_change)) as total_loss, COALESCE(SUM(financial_value), 0) as total_financial_loss")
                    ->groupByRaw("YEAR(date)")
                    ->orderByRaw("period_start DESC");
                break;
            default: // daily
                $summaryQuery->selectRaw("date as period_start, date as period_label, ABS(SUM(quantity_change)) as total_loss, COALESCE(SUM(financial_value), 0) as total_financial_loss")
                    ->groupBy('date')
                    ->orderBy('date', 'desc');
                break;
        }

        $summaryRecords = $summaryQuery->paginate(10);

        // Recent Mortality Log Records (latest 25)
        $recentRecords = MortalityRecord::with(['batch', 'recorder'])
            ->where('quantity_change', '<', 0)
            ->orderBy('date', 'desc')
            ->orderBy('mortality_id', 'desc')
            ->limit(25)
            ->get();

        // Active batches for intake form
        $activeBatches = Batch::where('status', 'active')
            ->orderBy('batch_code')
            ->get();

        // All batches for filter dropdown
        $allBatches = Batch::orderBy('arrival_date', 'desc')->get();

        return view('mortality.index', compact(
            'month',
            'year',
            'batchFilter',
            'viewCount',
            'latestM',
            'latestY',
            'daysInMonth',
            'firstDay',
            'dayRecordsMap',
            'summaryPeriod',
            'summaryRecords',
            'recentRecords',
            'activeBatches',
            'allBatches'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'batch_id' => 'required|exists:batches,batch_id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'date' => 'required|date',
            'financial_value' => 'nullable|numeric|min:0'
        ]);

        $batch = Batch::findOrFail($validated['batch_id']);

        if ($validated['quantity'] > $batch->current_quantity) {
            return back()->withInput()->with('error', "Mortality loss count ({$validated['quantity']}) cannot exceed current batch headcount ({$batch->current_quantity}).");
        }

        $qtyChange = -abs((int)$validated['quantity']);
        $financialValue = (float)($validated['financial_value'] ?? 0);
        
        // Auto-calculate financial loss if 0
        if ($financialValue <= 0) {
            $chickCost = (float)($batch->chick_unit_cost ?: 38.00);
            $arrDate = strtotime($batch->arrival_date ?: $validated['date']);
            $recDate = strtotime($validated['date']);
            $ageDays = max(1, floor(($recDate - $arrDate) / 86400));
            $feedMeds = min(35, $ageDays) * 2.85;
            $costPerBird = $chickCost + $feedMeds;
            $financialValue = round(abs($qtyChange) * $costPerBird, 2);
        }

        $userId = Auth::id() ?: 1;

        DB::transaction(function () use ($batch, $validated, $qtyChange, $financialValue, $userId) {
            $mortality = MortalityRecord::create([
                'batch_id' => $batch->batch_id,
                'date' => $validated['date'],
                'quantity_change' => $qtyChange,
                'reason' => $validated['reason'],
                'financial_value' => $financialValue,
                'recorded_by' => $userId
            ]);

            if ($financialValue > 0) {
                Expense::create([
                    'batch_id' => $batch->batch_id,
                    'category' => 'other',
                    'amount' => $financialValue,
                    'description' => "Mortality Loss: " . abs($qtyChange) . " birds. Reason: " . $validated['reason'],
                    'date' => $validated['date'],
                    'source_type' => 'mortality',
                    'source_id' => $mortality->mortality_id,
                    'recorded_by' => $userId
                ]);
            }

            // Decrement batch current quantity safely
            $batch->update([
                'current_quantity' => max(0, $batch->current_quantity + $qtyChange)
            ]);

            // Record audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'create',
                'table_name' => 'mortality_records',
                'record_id' => $mortality->mortality_id,
                'new_values' => json_encode([
                    'batch_id' => $batch->batch_id,
                    'quantity_loss' => abs($qtyChange),
                    'reason' => $validated['reason'],
                    'date' => $validated['date'],
                    'financial_loss' => $financialValue,
                    'action_type' => 'mortality_deduction'
                ]),
                'ip_address' => request()->ip() ?? '127.0.0.1'
            ]);
        });

        $m = (int)date('n', strtotime($validated['date']));
        $y = (int)date('Y', strtotime($validated['date']));

        return redirect()->route('mortality.index', [
            'batch_filter' => $batch->batch_id,
            'month' => $m,
            'year' => $y
        ])->with('success', "Mortality record saved successfully!");
    }
}
