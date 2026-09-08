<?php

namespace App\Http\Controllers\Kunjungan;

use App\Http\Controllers\Controller;
use App\Models\Kunjungan\Employee;
use App\Services\Identity\SidewasEmployeeSynchronizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request, SidewasEmployeeSynchronizer $synchronizer): View
    {
        $this->authorizeAdmin($request);
        $synchronizer->syncAll();

        $employees = Employee::query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = '%'.trim($request->string('search')).'%';
                $query->where(fn (Builder $query) => $query->where('employee_number', 'like', $search)
                    ->orWhere('name', 'like', $search)->orWhere('email', 'like', $search)
                    ->orWhere('position', 'like', $search)
                    ->orWhere('organizational_unit', 'like', $search)
                    ->orWhere('directorate', 'like', $search));
            })
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_active', $request->string('status') === 'active'))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('kunjungan.employees.index', compact('employees'));
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->canManageKunjungan(), 403);
    }
}
