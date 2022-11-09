<?php

namespace HyperfTest\HyperfExtNestedset\Model;

use Ece2\HyperfExtNestedset\NodeTrait;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;
    use NodeTrait;

    protected array $fillable = ['name', 'parent_id'];

    public bool $timestamps = false;

    public static function resetActionsPerformed()
    {
        static::$actionsPerformed = 0;
    }
}
