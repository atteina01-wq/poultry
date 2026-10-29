<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Batch;
use App\Models\House;
use App\Models\Reservation;
use App\Models\Activity;
use Illuminate\Support\Facades\Auth;

class BatchController extends Controller
{
    public function index()
    {
        $batches = Batch::with(['reservations', 'mortalityRecords', 'feedConsumptions', 'healthRecords'])
            ->orderBy('arrival_date', 'desc')
            ->orderBy('batch_id', 'desc')
            ->paginate(10);

        // Decorate batches with stage and growth stats
        foreach ($batches as $b) {
            $ageDays = $b->arrival_date ? max(1, (int)now()->diffInDays(\Carbon\Carbon::parse($b->arrival_date))) : 1;
            $ageWeeks = (int)ceil($ageDays / 7);

            if ($ageDays <= 10) {
                $feedType = 'Pre-Starter';
                $stageLabel = 'Pre-Starter (Day 1-10)';
                $badgeClass = 'bg-primary text-white';
            } elseif ($ageDays <= 20) {
                $feedType = 'Starter';
                $stageLabel = 'Starter (Day 11-20)';
                $badgeClass = 'bg-info text-white';
            } elseif ($ageDays <= 30) {
                $feedType = 'Grower';
                $stageLabel = 'Grower (Day 21-30)';
                $badgeClass = 'bg-warning text-dark';
            } else {
                $feedType = 'Finisher';
                $stageLabel = 'Finisher (Day 31+)';
                $badgeClass = 'bg-success text-white';
            }

            $curW = (float)($b->current_weight_kg ?? 0);
            $tarW = (float)($b->target_weight_kg ?? 1.80);
            $ready = ($curW >= $tarW && $curW > 0);
            $weightPct = $tarW > 0 ? min(100, round(($curW / $tarW) * 100)) : 0;

            $totalMort = abs((int)$b->mortalityRecords->where('quantity_change', '<', 0)->sum('quantity_change'));
            $financialLoss = (float)$b->mortalityRecords->where('quantity_change', '<', 0)->sum('financial_value');
            $survivalRate = $b->initial_quantity > 0 ? round((($b->initial_quantity - $totalMort) / $b->initial_quantity) * 100, 2) : 100;
            $totalFeed = (float)$b->feedConsumptions->sum('quantity_used');

            $b->stage_info = [
                'age_days' => $ageDays,
                'age_weeks' => $ageWeeks,
                'feed_type' => $feedType,
                'status_label' => $stageLabel,
                'badge_class' => $badgeClass,
                'is_ready_pickup' => ($ageDays >= 30)
            ];

            $b->readiness = [
                'ready' => $ready,
                'percent' => $weightPct
            ];

            $b->total_mortality = $totalMort;
            $b->total_financial_loss = $financialLoss;
            $b->survival_rate_pct = $survivalRate;
            $b->total_feed_used = $totalFeed;
        }

        $houses = House::orderBy('house_id')->get();
        $totalRecords = Batch::count();

        // Pending reservations available for stocking
        $pendingReservations = Reservation::with(['customer', 'house'])
            ->where('status', 'active')
            ->whereNull('batch_id')
            ->orderBy('reservation_id', 'desc')
            ->get();

        return view('batches.index', compact('batches', 'houses', 'totalRecords', 'pendingReservations'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'batch_code' => 'required|string|unique:batches,batch_code',
            'arrival_date' => 'required|date',
            'quantity' => 'required|integer|min:1',
            'breed' => 'nullable|string',
            'house_pen' => 'nullable|string',
            'supplier' => 'nullable|string',
            'delivery_receipt_no' => 'nullable|string',
            'chick_unit_cost' => 'required|numeric|min:0',
            'target_weight_kg' => 'required|numeric|min:0.5',
            'expected_harvest_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        $validated['initial_quantity'] = $validated['quantity'];
        $validated['current_quantity'] = $validated['quantity'];
        $validated['status'] = 'active';
        $validated['flock_type'] = 'broiler';
        $validated['production_date'] = $validated['arrival_date'];
        $validated['created_by'] = Auth::id() ?: 1;

        unset($validated['quantity']);

        $batch = Batch::create($validated);

        // Update house to occupied if house_pen specified
        if (!empty($validated['house_pen'])) {
            House::where('house_name', $validated['house_pen'])->update([
                'status' => 'occupied',
                'current_batch_id' => $batch->batch_id,
                'vacant_date' => $validated['expected_harvest_date'] ?? null
            ]);
        }

        // Link pending reservation if requested
        $linkedResId = (int)$request->input('linked_reservation_id', 0);
        if ($linkedResId > 0) {
            Reservation::where('reservation_id', $linkedResId)->update([
                'batch_id' => $batch->batch_id
            ]);
        }

        return redirect()->route('batches.index')->with('success', "Broiler Batch '{$batch->batch_code}' successfully registered!");
    }

    public function update(Request $request, $id)
    {
        $batch = Batch::findOrFail($id);

        $validated = $request->validate([
            'batch_code' => 'required|string|unique:batches,batch_code,' . $id . ',batch_id',
            'initial_quantity' => 'required|integer|min:1',
            'status' => 'required|in:active,harvested,closed',
            'breed' => 'nullable|string',
            'house_pen' => 'nullable|string',
            'supplier' => 'nullable|string',
            'delivery_receipt_no' => 'nullable|string',
            'chick_unit_cost' => 'required|numeric|min:0',
            'target_weight_kg' => 'required|numeric',
            'current_weight_kg' => 'nullable|numeric',
            'expected_harvest_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);

        $batch->update($validated);

        if (in_array($validated['status'], ['harvested', 'closed']) && !empty($validated['house_pen'])) {
            House::where('house_name', $validated['house_pen'])->update([
                'status' => 'cleaning',
                'current_batch_id' => null,
                'vacant_date' => now()->toDateString()
            ]);
        }

        return redirect()->route('batches.index')->with('success', "Broiler Batch '{$batch->batch_code}' updated successfully!");
    }

    public function quickWeigh(Request $request, $id)
    {
        $batch = Batch::findOrFail($id);
        $weight = (float)$request->input('weight_kg', 0);
        $sampleDate = $request->input('sample_date', now()->toDateString());
        $birdsSampled = (int)$request->input('birds_sampled', 50);
        $notes = $request->input('notes', '');

        if ($weight > 0) {
            $batch->update(['current_weight_kg' => $weight]);

            Activity::create([
                'batch_id' => $batch->batch_id,
                'user_id' => Auth::id() ?: 1,
                'type' => 'Weight Sampling',
                'item_name' => "Sample Weighing ({$birdsSampled} birds sampled, avg {$weight} kg/bird)",
                'quantity' => $weight,
                'date' => $sampleDate,
                'remarks' => $notes,
                'status' => 'completed'
            ]);

            return back()->with('success', "Flock weight sample of {$weight} kg recorded successfully! Release readiness updated.");
        }
        return back()->with('error', "Invalid sample weight.");
    }
}
