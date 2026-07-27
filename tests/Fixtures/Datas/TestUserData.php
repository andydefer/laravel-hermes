<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Tests\Fixtures\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class TestUserData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $description = null,
        public readonly ?bool $is_active = true,
        public readonly ?string $fingerprint = null,
        public readonly ?string $created_at = null,
        public readonly ?string $updated_at = null,
    ) {}
}
