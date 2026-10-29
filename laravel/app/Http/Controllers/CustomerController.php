<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $customers = Customer::orderBy('is_active', 'desc')
            ->orderBy('name', 'asc')
            ->get();

        return view('customers.index', compact('customers'));
    }

    public function store(Request $request)
    {
        $customerId = $request->input('customer_id');

        $validated = $request->validate([
            'customer_code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'address' => 'nullable|string',
            'customer_type' => 'required|in:retail,wholesale,institutional'
        ]);

        if (!empty($customerId)) {
            // Update
            $customer = Customer::findOrFail($customerId);

            // Check duplicate code
            if (Customer::where('customer_code', $validated['customer_code'])->where('customer_id', '!=', $customerId)->exists()) {
                return back()->withInput()->with('error', 'Retailer code already exists.');
            }

            $customer->update($validated);

            AuditLog::create([
                'user_id' => Auth::id() ?: 1,
                'action' => 'update',
                'table_name' => 'customers',
                'record_id' => $customer->customer_id,
                'new_values' => json_encode($validated),
                'ip_address' => request()->ip() ?? '127.0.0.1'
            ]);

            return redirect()->route('customers.index')->with('success', 'Retailer updated successfully!');
        } else {
            // Create
            if (Customer::where('customer_code', $validated['customer_code'])->exists()) {
                return back()->withInput()->with('error', 'Customer code already exists.');
            }

            $validated['is_active'] = 1;
            $customer = Customer::create($validated);

            AuditLog::create([
                'user_id' => Auth::id() ?: 1,
                'action' => 'create',
                'table_name' => 'customers',
                'record_id' => $customer->customer_id,
                'new_values' => json_encode($validated),
                'ip_address' => request()->ip() ?? '127.0.0.1'
            ]);

            return redirect()->route('customers.index')->with('success', 'Retailer added successfully!');
        }
    }

    public function update(Request $request, $id)
    {
        $request->merge(['customer_id' => $id]);
        return $this->store($request);
    }

    public function deactivate($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->update(['is_active' => 0]);

        AuditLog::create([
            'user_id' => Auth::id() ?: 1,
            'action' => 'update',
            'table_name' => 'customers',
            'record_id' => $customer->customer_id,
            'new_values' => json_encode(['is_active' => 0]),
            'ip_address' => request()->ip() ?? '127.0.0.1'
        ]);

        return redirect()->route('customers.index')->with('success', 'Retailer deactivated successfully!');
    }
}
