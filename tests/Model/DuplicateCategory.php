<?php

namespace HyperfTest\HyperfExtNestedset\Model;

use Ece2\HyperfExtNestedset\NodeTrait;

class DuplicateCategory extends \Hyperf\Database\Model\Model
{
    use NodeTrait;

    protected string $table = 'categories';

    protected array $fillable = ['name'];

    public bool $timestamps = false;
}
