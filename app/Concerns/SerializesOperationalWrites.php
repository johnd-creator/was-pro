<?php

namespace App\Concerns;

use App\Exceptions\OperationalConflict;
use App\Services\OperationalTransactionService;

trait SerializesOperationalWrites
{
    private bool $operationalRestoring = false;

    protected function transitionOperationally(string $predicate, array $attributes): bool
    {
        return app(OperationalTransactionService::class)->run(function () use ($predicate, $attributes): bool {
            $current = $this->newQuery()->find($this->getKey());
            if (! $current || ! $current->{$predicate}()) {
                return false;
            }
            $this->setRawAttributes($current->getAttributes(), true);
            $this->unsetRelations();
            $this->fill($attributes);

            return $this->save();
        });
    }

    public function save(array $options = []): bool
    {
        return app(OperationalTransactionService::class)->run(function () use ($options): bool {
            $current = null;
            if ($this->exists) {
                $query = $this->newQuery();
                if ($this->operationalRestoring) {
                    $query->withTrashed();
                }
                $current = $query->findOrFail($this->getKey());
                foreach (['status', 'approval_status'] as $column) {
                    if ($this->isDirty($column) && $current->getRawOriginal($column) !== $this->getRawOriginal($column)) {
                        throw new OperationalConflict('Status transaksi telah berubah. Muat ulang data terlebih dahulu.');
                    }
                }
            }

            $guard = app(\App\Services\OperationalModelGuard::class);
            $changed = $current === null || $this->isDirty();
            $before = $changed ? $guard->before($this, $current) : null;
            $saved = parent::save($options);
            $guard->after($before);
            if ($saved && $changed) {
                app(\App\Services\OperationalAuditService::class)->record($this, $current?->getAttributes() ?? [], $current ? 'update' : 'create');
            }

            return $saved;
        });
    }

    public function delete(): ?bool
    {
        if (! $this->exists) {
            return null;
        }

        return app(OperationalTransactionService::class)->run(function (): ?bool {
            $query = $this->newQuery();
            $forceDeleting = method_exists($this, 'isForceDeleting') && $this->isForceDeleting();
            if ($forceDeleting) {
                $query->withTrashed();
            }
            $current = $query->findOrFail($this->getKey());
            foreach (['status', 'approval_status'] as $column) {
                if ($current->getRawOriginal($column) !== $this->getRawOriginal($column)) {
                    throw new OperationalConflict('Status transaksi telah berubah. Muat ulang data terlebih dahulu.');
                }
            }

            $guard = app(\App\Services\OperationalModelGuard::class);
            $before = $guard->before($this, $current);
            $deleted = parent::delete();
            $guard->after($before);
            if ($deleted) {
                app(\App\Services\OperationalAuditService::class)->record($this, $current->getAttributes(), $forceDeleting ? 'force_delete' : 'delete');
            }

            return $deleted;
        });
    }
}
