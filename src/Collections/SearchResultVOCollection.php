<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\DataCollection;
use AndyDefer\DomainStructures\Collections\Utility\StringTypedCollection;
use AndyDefer\DomainStructures\Utils\Sequential;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\LaravelHermes\ValueObjects\SearchResultVO;
use Illuminate\Database\Eloquent\Collection;

/**
 * Collection spécialisée pour SearchResultVO.
 *
 * @method SearchResultVO|null first()
 * @method SearchResultVO|null last()
 * @method SearchResultVO|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 */
final class SearchResultVOCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SearchResultVO::class);
    }

    /**
     * Récupère les données de tous les résultats.
     */
    public function getDatas(): DataCollection
    {
        $dataClasses = [];
        foreach ($this->items as $vo) {
            $data = $vo->getValue()['data'];
            $dataClasses[] = get_class($data);
        }

        $uniqueClasses = array_unique($dataClasses);

        $collection = new DataCollection(...$uniqueClasses);

        foreach ($this->items as $vo) {
            $collection->add($vo->getValue()['data']);
        }

        return $collection;
    }

    /**
     * Récupère les fingerprints de tous les résultats.
     */
    public function getFingerprints(): StringTypedCollection
    {
        $collection = new StringTypedCollection;
        foreach ($this->items as $vo) {
            $collection->add($vo->getValue()['fingerprint']);
        }

        return $collection;
    }

    /**
     * Récupère les matches de tous les résultats.
     */
    public function getMatches(): MatchRecordCollection
    {
        $collection = new Sequential;
        foreach ($this->items as $vo) {
            foreach ($vo->getValue()['matches'] as $match) {
                $collection = $collection->add($match);
            }
        }

        return MatchRecordCollection::from($collection);
    }

    /**
     * Filtre par similarité minimale.
     */
    public function filterByMinSimilarity(float $minSimilarity): self
    {
        return $this->filter(
            fn (SearchResultVO $vo) => $vo->getValue()['similarity'] >= $minSimilarity
        );
    }

    /**
     * Filtre par namespace.
     */
    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(
            fn (SearchResultVO $vo) => str_starts_with($vo->getValue()['fingerprint'], $namespace.'|')
        );
    }

    /**
     * Filtre par plusieurs namespaces.
     */
    public function filterByNamespaces(array $namespaces): self
    {
        return $this->filter(
            function (SearchResultVO $vo) use ($namespaces) {
                $fingerprint = $vo->getValue()['fingerprint'];
                foreach ($namespaces as $namespace) {
                    if (str_starts_with($fingerprint, $namespace.'|')) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    /**
     * Groupe les résultats par namespace.
     *
     * @return StrictAssociative<string, self>
     */
    public function groupByNamespace(): StrictAssociative
    {
        $groups = [];
        foreach ($this->items as $vo) {
            $fingerprint = $vo->getValue()['fingerprint'];
            $parts = explode('|', $fingerprint);
            if (count($parts) === 2) {
                $namespace = $parts[0];
                if (! isset($groups[$namespace])) {
                    $groups[$namespace] = new self;
                }
                $groups[$namespace]->add($vo);
            }
        }

        return StrictAssociative::from($groups);
    }
}
