<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reservation;
use App\Models\Batch;
use App\Models\Customer;
use App\Models\House;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $statusFilter = $request->query('status', 'all');
        $batchFilter = (int)$request->query('batch_id', 0);
        $search = trim($request->query('search', ''));

        // Query only reservations that belong to an existing batch
        $query = Reservation::with(['customer', 'batch', 'house', 'creator'])
            ->whereHas('batch');

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($batchFilter > 0) {
            $query->where('batch_id', $batchFilter);
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('reservation_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                         ->orWhere('customer_code', 'like', "%{$search}%");
                  })
                  ->orWhereHas('batch', function($bq) use ($search) {
                      $bq->where('batch_code', 'like', "%{$search}%");
                  });
            });
        }

        $reservations = $query->orderBy('pickup_date', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Active batches for dropdown
        $batches = Batch::where('status', 'active')->orderBy('batch_code')->get();
        $houses = House::with('currentBatch')->orderBy('house_id')->get();
        $customers = Customer::where('is_active', 1)->orderBy('name')->get();

        // KPIs
        $kpiActiveCount = Reservation::whereHas('batch')->where('status', 'active')->count();
        $kpiReservedBirds = Reservation::whereHas('batch')->where('status', 'active')->sum('quantity_birds');

        return view('reservations.index', compact(
            'reservations',
            'batches',
            'houses',
            'customers',
            'statusFilter',
            'batchFilter',
            'search',
            'kpiActiveCount',
            'kpiReservedBirds'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
            'batch_id' => 'required|exists:batches,batch_id',
            'house_id' => 'nullable|exists:houses,house_id',
            'quantity_birds' => 'required|integer|min:1',
            'weight_kg' => 'nullable|numeric|min:0',
            'target_weight_per_bird' => 'required|numeric|min:0.5|max:5.0',
            'unit_price' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'advance_payment' => 'required|numeric|min:0',
            'pickup_date' => 'required|date',
            'notes' => 'nullable|string'
        ]);

        // Generate reservation number
        $prefix = 'RES-' . date('Ymd', strtotime($validated['pickup_date'])) . '-';
        $seq = Reservation::where('reservation_number', 'like', $prefix . '%')->count() + 1;
        $resNumber = $prefix . str_pad((string)$seq, 3, '0', STR_PAD_LEFT);

        $validated['reservation_number'] = $resNumber;
        $validated['status'] = 'active';
        $validated['created_by'] = Auth::id() ?: 1;

        $res = Reservation::create($validated);

        return redirect()->route('reservations.index')
            ->with('success', "Broiler Reservation #{$res->reservation_number} created successfully!");
    }

    public function update(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,customer_id',
            'batch_id' => 'required|exists:batches,batch_id',
            'house_id' => 'nullable|exists:houses,house_id',
            'quantity_birds' => 'required|integer|min:1',
            'weight_kg' => 'nullable|numeric|min:0',
            'unit_price' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0',
            'advance_payment' => 'nullable|numeric|min:0',
            'pickup_date' => 'required|date',
            'status' => 'required|in:active,fulfilled,cancelled',
            'notes' => 'nullable|string'
        ]);

        $reservation->update($validated);

        return redirect()->route('reservations.index')
            ->with('success', "Reservation #{$reservation->reservation_number} updated successfully!");
    }

    public function fulfill(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);

        $validated = $request->validate([
            'actual_pickup_date' => 'required|date',
            'accepted_quantity' => 'required|integer|min:0',
            'rejected_quantity' => 'nullable|integer|min:0',
            'actual_weight_kg' => 'required|numeric|min:0',
            'actual_price' => 'required|numeric|min:0',
            'receipt_number' => 'required|string',
            'rejection_reason' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        $validated['status'] = 'fulfilled';
        $reservation->update($validated);

        return redirect()->route('reservations.index')
            ->with('success', "Flock successfully dispatched and fulfilled for Reservation #{$reservation->reservation_number}!");
    }

    public function cancel($id)
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->update(['status' => 'cancelled']);

        return redirect()->route('reservations.index')
            ->with('success', "Reservation #{$reservation->reservation_number} has been cancelled.");
    }

    public function restore($id)
    {
        $reservation = Reservation::findOrFail($id);
        $reservation->update(['status' => 'active']);

        return redirect()->route('reservations.index')
            ->with('success', "Reservation #{$reservation->reservation_number} restored to Active.");
    }
}
