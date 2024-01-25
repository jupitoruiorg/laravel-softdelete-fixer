<?php

namespace BlackPlatinum\Eloquent;

use BlackPlatinum\Exceptions\ModelNotFoundException;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Closure;

class Builder extends EloquentBuilder
{
    private $modelFinder;

    /**
     * Create a new Eloquent query builder instance.
     *
     * @param QueryBuilder $query
     * @return void
     */
    public function __construct(QueryBuilder $query)
    {
        parent::__construct($query);
        $this->modelFinder = new ModelFinder();
    }

    /**
     * Get Model name by table name
     *
     * @param string $table
     * @return string
     */
    protected function getModelName($table)
    {
        if (is_a($table, Expression::class)) {
            $table = $table->getValue(DB::connection()->getQueryGrammar());
        }

        return Str::studly(Str::singular($table));
    }

    /**
     * Get model finder object
     *
     * @return ModelFinder
     */
    public function getModelFinder()
    {
        return $this->modelFinder;
    }

    /**
     * Pass a closure as an other way to find model class
     *
     * @param Closure $closure
     */
    public function addToFinder($closure)
    {
        $this->modelFinder->addToFinder($closure);
    }

    /**
     * Check given table has soft delete or not
     *
     * @param string $table
     * @return bool
     * @throws ModelNotFoundException
     */
    protected function checkSoftDelete($table)
    {
        $model = $this->getModelName($table);
        $model = str_starts_with($model, 'Z') ? ltrim($model, 'Z') : $model;

        $modelClass = $this->modelFinder->getModel($model, $this->model);
        if ($modelClass) {
            return in_array(SoftDeletes::class, class_uses_recursive($modelClass));
        }
        $modelClass = $this->modelFinder->getModel($model, $this->model);
        if ($modelClass) {
            return in_array(SoftDeletes::class, class_uses_recursive($modelClass));
        }

        /**
         * @TODO: log this error
         */
        //throw new ModelNotFoundException($table);
    }

    /**
     * Add a join clause to the query.
     *
     * @param string $table
     * @param Closure|string $first
     * @param string|null $operator
     * @param string|null $second
     * @param string $type
     * @param bool $where
     * @param bool $withTrash
     * @return QueryBuilder|$this|EloquentBuilder
     */
    public function join($table, $first, $operator = null, $second = null, $type = 'inner', $where = false, $withTrash = false)
    {
        if (!$withTrash) {
            if ($this->checkSoftDelete($table)) {

                return parent::join($table, function($query) use ($first, $operator, $second, $table) {
                    $query
                        ->on($first, $operator, $second)
                        ->where("$table.deleted_at", null)
                    ;
                }, null, null, $type);

                //return parent::join($table, $first, $operator, $second, $type, $where)
                //    ->join($table, 'deleted_at', $operator, $second, $type, true);
                //->where("$table.deleted_at", null);
            }
        }
        return parent::join($table, $first, $operator, $second, $type, $where);
    }

    public function leftJoin($table, $first, $operator = null, $second = null, $withTrash = false)
    {
        return $this->join($table, $first, $operator, $second, 'left', false, $withTrash);
    }

    public function rightJoin($table, $first, $operator = null, $second = null, $withTrash = false)
    {
        return $this->join($table, $first, $operator, $second, 'right', false, $withTrash);
    }
}
