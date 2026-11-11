<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\OperationalAuditRequest;
use App\Services\OperationalAuditViewer;
use Inertia\Inertia;
use Inertia\Response;

class OperationalAuditController extends Controller
{
    public function index(OperationalAuditRequest $request, OperationalAuditViewer $viewer): Response
    {
        $filters = $request->safe()->except('page');
        $query = $viewer->query($request->user());
        foreach (['module', 'action', 'reference_id', 'actor_id'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        $page = $query->orderByDesc('created_at')->orderByDesc('id')->paginate(25);

        return Inertia::render('waste-management/audit/Index', ['entries' => collect($page->items())->map(fn ($log) => $viewer->serialize($log))->values(), 'page' => $page->currentPage(), 'hasMore' => $page->hasMorePages(), 'total' => $page->total(), 'filters' => $filters]);
    }
}
