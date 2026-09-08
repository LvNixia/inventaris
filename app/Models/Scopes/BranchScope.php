<?php

namespace App\Models\Scopes;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class BranchScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::hasUser()) {
            $user = Auth::user();

            if ($user->role !== Role::AdminPusat && $user->branch_id) {
                $builder->where($model->getTable() . '.branch_id', $user->branch_id);
            }
        }
    }
}
