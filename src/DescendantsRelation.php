<?php

namespace Ece2\HyperfExtNestedset;

use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Relations\Constraint;

class DescendantsRelation extends BaseRelation
{

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (Constraint::isConstraint()) {
            $this->query
                ->whereDescendantOf($this->parent)
                ->applyNestedSetScope();
        }
    }

    /**
     * @param QueryBuilder $query
     * @param Model $model
     */
    protected function addEagerConstraint($query, $model)
    {
        $query->orWhereDescendantOf($model);
    }

    /**
     * @param Model $model
     * @param $related
     *
     * @return mixed
     */
    protected function matches(Model $model, $related)
    {
        return $related->isDescendantOf($model);
    }

    /**
     * @param $hash
     * @param $table
     * @param $lft
     * @param $rgt
     *
     * @return string
     */
    protected function relationExistenceCondition($hash, $table, $lft, $rgt)
    {
        return "{$hash}.{$lft} between {$table}.{$lft} + 1 and {$table}.{$rgt}";
    }
}
