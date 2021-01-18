<?php
declare(strict_types=1);

namespace HPlus\DevTool;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__
                    ],
                ],
            ]
        ];
    }
}
