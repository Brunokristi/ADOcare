<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/**
 * Simple trait which provides query scopes for filtering models by
 * company_id / branch_id.  Models that store one or both of those
 * foreign keys can include this trait and immediately participate in the
 * automatic "scope-aware" eager loading performed by ApiQuery.
 *
 * Scopes are intentionally short and descriptive so that callers like
 * ApiQuery can call them dynamically with `method_exists` checks.
 */
trait CompanyBranchScopes
{
    /**
     * Limit the query to rows belonging to the given company.
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        // if table has a direct company_id column we can filter easily
        $table = $query->getModel()->getTable();
        if (Schema::hasColumn($table, 'company_id')) {
            return $query->where('company_id', $companyId);
        }

        // fallback: if the model has a `branch` relationship we can
        // traverse it to reach the company
        if (method_exists($query->getModel(), 'branch')) {
            return $query->whereHas('branch', function (Builder $q) use ($companyId) {
                $q->where('company_id', $companyId);
            });
        }

        // nothing we know how to do
        return $query;
    }

    /**
     * Limit the query to rows belonging to the given branch.
     */
    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        $table = $query->getModel()->getTable();
        if (Schema::hasColumn($table, 'branch_id')) {
            return $query->where('branch_id', $branchId);
        }

        if (method_exists($query->getModel(), 'branch')) {
            return $query->whereHas('branch', function (Builder $q) use ($branchId) {
                $q->where('id', $branchId);
            });
        }

        return $query;
    }
}
