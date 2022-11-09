<?php

namespace Ece2\HyperfExtNestedset;

use Hyperf\Database\Model\Builder as ModelBuilder;
use Hyperf\Database\Model\Collection as ModelCollection;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Relations\Relation;
use Hyperf\Database\Query\Builder;
use InvalidArgumentException;

abstract class BaseRelation extends Relation
{
    /**
     * @var QueryBuilder
     */
    protected $query;

    /**
     * @var NodeTrait|Model
     */
    protected $parent;

    /**
     * The count of self joins.
     *
     * @var int
     */
    protected static $selfJoinCount = 0;

    /**
     * AncestorsRelation constructor.
     *
     * @param QueryBuilder $builder
     * @param Model $model
     */
    public function __construct(QueryBuilder $builder, Model $model)
    {
        if ( ! NestedSet::isNode($model)) {
            throw new InvalidArgumentException('Model must be node.');
        }

        parent::__construct($builder, $model);
    }

    /**
     * @param Model $model
     * @param $related
     *
     * @return bool
     */
    abstract protected function matches(Model $model, $related);

    /**
     * @param QueryBuilder $query
     * @param Model $model
     *
     * @return void
     */
    abstract protected function addEagerConstraint($query, $model);

    /**
     * @param $hash
     * @param $table
     * @param $lft
     * @param $rgt
     *
     * @return string
     */
    abstract protected function relationExistenceCondition($hash, $table, $lft, $rgt);

    /**
     * @param ModelBuilder $query
     * @param ModelBuilder $parent
     * @param array $columns
     *
     * @return mixed
     */
    public function getRelationExistenceQuery(ModelBuilder $query, ModelBuilder $parent,
                                                           $columns = [ '*' ]
    ) {
        $query = $this->getParent()->replicate()->newScopedQuery()->select($columns);

        $table = $query->getModel()->getTable();

        $query->from($table.' as '.$hash = $this->getRelationCountHash());

        $query->getModel()->setTable($hash);

        $grammar = $query->getQuery()->getGrammar();

        $condition = $this->relationExistenceCondition(
            $grammar->wrapTable($hash),
            $grammar->wrapTable($table),
            $grammar->wrap($this->parent->getLftName()),
            $grammar->wrap($this->parent->getRgtName()));

        return $query->whereRaw($condition);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array $models
     * @param  string $relation
     *
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        return $models;
    }

    /**
     * Get a relationship join table hash.
     *
     * @param  bool $incrementJoinCount
     * @return string
     */
    public function getRelationCountHash($incrementJoinCount = true)
    {
        return 'nested_set_'.($incrementJoinCount ? static::$selfJoinCount++ : static::$selfJoinCount);
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->get();
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array $models
     *
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        // The first model in the array is always the parent, so add the scope constraints based on that model.
        // @link https://github.com/laravel/framework/pull/25240
        // @link https://github.com/lazychaser/laravel-nestedset/issues/351
        optional(reset($models))->applyNestedSetScope($this->query);

        $this->query->whereNested(function (Builder $inner) use ($models) {
            // We will use this query in order to apply constraints to the
            // base query builder
            $outer = $this->parent->newQuery()->setQuery($inner);

            foreach ($models as $model) {
                $this->addEagerConstraint($outer, $model);
            }
        });
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array $models
     * @param  ModelCollection $results
     * @param  string $relation
     *
     * @return array
     */
    public function match(array $models, ModelCollection $results, $relation)
    {
        foreach ($models as $model) {
            $related = $this->matchForModel($model, $results);

            $model->setRelation($relation, $related);
        }

        return $models;
    }

    /**
     * @param Model $model
     * @param ModelCollection $results
     *
     * @return Collection
     */
    protected function matchForModel(Model $model, ModelCollection $results)
    {
        $result = $this->related->newCollection();

        foreach ($results as $related) {
            if ($this->matches($model, $related)) {
                $result->push($related);
            }
        }

        return $result;
    }
}
