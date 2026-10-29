<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CompanyProfile;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    public function index()
    {
        $company = CompanyProfile::first() ?: new CompanyProfile([
            'company_name' => 'Alquizar Poultry Farm',
            'address' => 'Tampakan, South Cotabato',
            'contact_number' => '09123456789',
            'email' => 'alquizarpoultry@gmail.com'
        ]);

        return view('company.index', compact('company'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'contact_number' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:100',
            'additional_info' => 'nullable|string',
            'logo' => 'nullable|image|max:2048'
        ]);

        $company = CompanyProfile::first();
        if (!$company) {
            $company = new CompanyProfile();
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('uploads/logos', 'public');
            $validated['logo'] = $path;
        }

        $company->fill($validated);
        $company->save();

        AuditLog::create([
            'user_id' => Auth::id() ?: 1,
            'action' => 'update',
            'table_name' => 'company_profile',
            'record_id' => $company->id ?? 1,
            'new_values' => json_encode($validated),
            'ip_address' => request()->ip()
        ]);

        return redirect()->route('company.index')->with('success', 'Farm profile information updated successfully!');
    }
}
