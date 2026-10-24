<?php

namespace App\Http\Middleware;

use App\Exceptions\OperationalConflict;
use App\Exceptions\RejectedOperationalResponse;
use App\Services\OperationalTransactionService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AtomicOperationalMutation
{
    private const CONTROLLERS = [
        'FabaAdjustmentController', 'FabaAdjustmentsController', 'FabaProductionController',
        'FabaProductionMovementsController', 'FabaUtilizationController', 'FabaUtilizationMovementsController',
        'FabaMovementApprovalController', 'FabaMovementApprovalsController', 'FabaApprovalController',
        'FabaMonthlyApprovalsController', 'FabaRecapController', 'FabaRecapsController',
        'WasteRecordController', 'WasteRecordsController', 'WasteHaulingController', 'WasteHaulingsController',
    ];

    public function __construct(private OperationalTransactionService $transactions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $controller = class_basename(explode('@', $request->route()?->getActionName() ?? '')[0]);
        if ($request->isMethodSafe() || ! $request->user() || ! in_array($controller, self::CONTROLLERS, true)) {
            return $next($request);
        }

        $files = app(\App\Services\OperationalFiles::class);
        $flashBefore = $request->hasSession() ? $request->session()->only(['success', 'warning']) : [];
        try {
            $response = $this->transactions->run(function () use ($request, $next): Response {
                foreach ($request->route()->parameters() as $name => $value) {
                    if ($value instanceof Model) {
                        $request->route()->setParameter($name, $value->newQuery()->where($value->getRouteKeyName(), $value->getRouteKey())->firstOrFail());
                    }
                }

                $idempotency = app(\App\Services\OperationalIdempotency::class);
                if ($replay = $idempotency->replay($request)) {
                    return $replay;
                }

                $faba = str_starts_with(class_basename(explode('@', $request->route()->getActionName())[0]), 'Faba');
                $integrity = $faba ? app(\App\Services\FabaIntegrityService::class) : null;
                $before = $integrity?->capture();

                $response = $next($request);
                $failed = $response->getStatusCode() >= 400
                    || ($response->isRedirection() && $request->hasSession() && in_array('error', $request->session()->get('_flash.new', []), true));
                if ($failed) {
                    throw new RejectedOperationalResponse($response);
                }

                $integrity?->validate($before);
                $idempotency->remember($request, $response);

                return $response;
            });
            $files->commit();

            return $response;
        } catch (RejectedOperationalResponse $exception) {
            $files->rollback();
            $this->restoreSuccessFlash($request, $flashBefore);

            return $exception->response;
        } catch (OperationalConflict $exception) {
            $files->rollback();
            $this->restoreSuccessFlash($request, $flashBefore);
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => $exception->getMessage(), 'error_code' => 'CONFLICT', 'errors' => []], 409);
            }

            return redirect()->back()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            $files->rollback();
            $this->restoreSuccessFlash($request, $flashBefore);
            throw $exception;
        }
    }

    private function restoreSuccessFlash(Request $request, array $before): void
    {
        if (! $request->hasSession()) {
            return;
        }
        foreach (['success', 'warning'] as $key) {
            if (array_key_exists($key, $before)) {
                $request->session()->put($key, $before[$key]);
            } else {
                $request->session()->forget($key);
            }
        }
    }
}
