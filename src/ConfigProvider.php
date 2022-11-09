<?php

declare(strict_types=1);

namespace Ece2\HyperfExtNestedset;

use Hyperf\Database\Schema\Blueprint;

class ConfigProvider
{
    public function __invoke(): array
    {
        Blueprint::macro('nestedSet', function () {
            NestedSet::columns($this);
        });

        Blueprint::macro('dropNestedSet', function () {
            NestedSet::dropColumns($this);
        });

        return [
            'dependencies' => [
            ],
            'commands' => [
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
            ]
        ];
    }
}
