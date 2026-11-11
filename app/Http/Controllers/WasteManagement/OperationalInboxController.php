<?php

namespace App\Http\Controllers\WasteManagement;

use App\Http\Controllers\Controller;
use App\Http\Requests\WasteManagement\OperationalInboxRequest;
use App\Services\OperationalInbox;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OperationalInboxController extends Controller
{
    public function index(OperationalInboxRequest $request, OperationalInbox $inbox): Response
    {
        $available = $inbox->available();

        return Inertia::render('waste-management/notifications/Index', [
            'available' => $available,
            ...($available ? $inbox->page($request->user(), (int) $request->validated('page', 1)) : [
                'notifications' => [], 'unreadCount' => 0, 'page' => 1, 'hasMore' => false,
            ]),
        ]);
    }

    public function update(OperationalInboxRequest $request, string $notification, OperationalInbox $inbox): RedirectResponse
    {
        abort_unless($inbox->available(), 404);
        $row = $inbox->visibleQuery($request->user())->whereKey($notification)->firstOrFail();
        $row->update(['read_at' => $request->boolean('read') ? ($row->read_at ?? now()) : null]);

        return redirect()->route('waste-management.notifications.index', [
            'organization_id' => $inbox->organization()->id,
            'page' => $request->validated('page', 1),
        ]);
    }
}
