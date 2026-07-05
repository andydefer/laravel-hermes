<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use InvalidArgumentException;

final class ContextFilterVO extends AbstractValueObject
{
    public readonly ?string $namespace;

    public readonly ?string $cluster;

    public function __construct(?string $namespace = null, ?string $cluster = null)
    {
        if ($namespace === null && $cluster === null) {
            throw new InvalidArgumentException('At least one of namespace or cluster must be provided');
        }

        $this->namespace = $namespace;
        $this->cluster = $cluster;
    }

    public function hasNamespace(): bool
    {
        return $this->namespace !== null;
    }

    public function hasCluster(): bool
    {
        return $this->cluster !== null;
    }

    public function getValue(): StrictAssociative
    {
        return StrictAssociative::from(array_filter([
            'namespace' => $this->namespace,
            'cluster' => $this->cluster,
        ]));
    }
}
