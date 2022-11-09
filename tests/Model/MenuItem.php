<?php

namespace HyperfTest\HyperfExtNestedset\Model;

use Ece2\HyperfExtNestedset\NodeTrait;

class MenuItem extends \Hyperf\Database\Model\Model
{
    use NodeTrait;

    public bool $timestamps = false;

    protected array $fillable = ['menu_id','parent_id'];

    public static function resetActionsPerformed()
    {
        static::$actionsPerformed = 0;
    }

    protected function getScopeAttributes()
    {
        return ['menu_id'];
    }

}
