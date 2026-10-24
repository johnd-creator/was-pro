<?php

namespace App\Concerns;

use App\Services\OperationalTransactionService;

trait RestoresOperationalWrites
{
    public function restore(): bool
    {
        return app(OperationalTransactionService::class)->run(function (): bool {
            $current = $this->newQuery()->withTrashed()->findOrFail($this->getKey());
            $this->setRawAttributes($current->getAttributes(), true);
            $this->unsetRelations();
            $this->operationalRestoring = true;
            try {
                return $this->restoreWithSoftDeletes();
            } finally {
                $this->operationalRestoring = false;
            }
        });
    }
}
