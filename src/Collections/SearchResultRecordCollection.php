<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Collections;

use AndyDefer\DomainStructures\Abstracts\AbstractTypedCollection;
use AndyDefer\DomainStructures\Collections\Core\TypedCollection;
use AndyDefer\LaravelHermes\Records\SearchResultRecord;
use AndyDefer\LaravelIndexer\Contracts\Indexable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @method SearchResultRecord|null first()
 * @method SearchResultRecord|null last()
 * @method SearchResultRecord|null find(callable $callback)
 * @method self filter(callable $callback)
 * @method self mapPreserveType(callable $callback)
 * @method TypedCollection map(callable $callback)
 * @method self merge(TypedCollection $collection)
 * @method self unique(?callable $callback = null)
 */
final class SearchResultRecordCollection extends AbstractTypedCollection
{
    public function __construct()
    {
        parent::__construct(SearchResultRecord::class);
    }

    public function getDocumentIds(): array
    {
        return array_map(fn (SearchResultRecord $r) => $r->document_id, $this->items);
    }

    public function getFingerprints(): array
    {
        return array_map(fn (SearchResultRecord $r) => $r->fingerprint, $this->items);
    }

    public function getNamespaces(): array
    {
        $namespaces = [];
        foreach ($this->items as $record) {
            $parts = explode('|', $record->fingerprint);
            if (isset($parts[0]) && ! in_array($parts[0], $namespaces, true)) {
                $namespaces[] = $parts[0];
            }
        }

        return $namespaces;
    }

    public function getIds(): array
    {
        $ids = [];
        foreach ($this->items as $record) {
            $parts = explode('|', $record->fingerprint);
            if (isset($parts[1])) {
                $ids[] = $parts[1];
            }
        }

        return $ids;
    }

    public function filterByMinSimilarity(float $minSimilarity): self
    {
        return $this->filter(function (SearchResultRecord $r) use ($minSimilarity) {
            return $r->similarity >= $minSimilarity;
        });
    }

    public function filterByField(string $field): self
    {
        return $this->filter(function (SearchResultRecord $r) use ($field) {
            return $r->matches->filterByField($field)->isNotEmpty();
        });
    }

    public function filterByNamespace(string $namespace): self
    {
        return $this->filter(function (SearchResultRecord $r) use ($namespace) {
            return str_starts_with($r->fingerprint, $namespace.'|');
        });
    }

    public function filterByNamespaces(array $namespaces): self
    {
        return $this->filter(function (SearchResultRecord $r) use ($namespaces) {
            foreach ($namespaces as $namespace) {
                if (str_starts_with($r->fingerprint, $namespace.'|')) {
                    return true;
                }
            }

            return false;
        });
    }

    public function getData(): array
    {
        return array_map(fn (SearchResultRecord $r) => $r->data->toArray(), $this->items);
    }

    public function getMatches(): array
    {
        return array_map(fn (SearchResultRecord $r) => $r->matches->toArray(), $this->items);
    }

    /**
     * Récupère les instances des modèles pour tous les résultats en une seule requête par classe.
     * Les modèles non trouvés sont ignorés silencieusement.
     * Les instances sont retournées dans l'ordre de la collection.
     *
     * @param  array<string>  $with  Relations à charger (ex: ['profile', 'profile.specialty'])
     * @return Collection<int, Model&Indexable>
     */
    /**
     * Récupère les instances des modèles pour tous les résultats en une seule requête par classe.
     * Les modèles non trouvés sont ignorés silencieusement.
     * Les instances sont retournées dans l'ordre de la collection.
     *
     * @param  array<string>  $with  Relations à charger (ex: ['profile', 'profile.specialty'])
     * @return Collection<int, Model&Indexable>
     */
    public function getModelInstances(array $with = []): Collection
    {
        // Grouper les IDs par classe
        $groupedIds = [];
        foreach ($this->items as $record) {
            $parts = explode('|', $record->fingerprint);
            if (count($parts) !== 2) {
                continue;
            }

            $class = str_replace('.', '\\', $parts[0]);
            if (! class_exists($class)) {
                continue;
            }

            $groupedIds[$class][] = $parts[1];
        }

        // Charger les modèles (une requête par classe)
        $models = [];
        foreach ($groupedIds as $class => $ids) {
            $query = $class::whereIn('id', $ids);

            // Vérifier que toutes les relations demandées existent sur le modèle
            if (! empty($with)) {
                $invalidRelations = [];
                foreach ($with as $relation) {
                    $mainRelation = explode('.', $relation)[0];
                    if (! method_exists($class, $mainRelation)) {
                        $invalidRelations[] = $relation;
                    }
                }

                if (! empty($invalidRelations)) {
                    throw new \InvalidArgumentException(
                        sprintf(
                            'Relations [%s] do not exist on model [%s]',
                            implode(', ', $invalidRelations),
                            $class
                        )
                    );
                }

                $query->with($with);
            }

            foreach ($query->get() as $model) {
                $key = get_class($model).'|'.$model->getKey();
                $models[$key] = $model;
            }
        }

        // Retourner dans l'ordre de la collection
        $result = [];
        foreach ($this->items as $record) {
            $parts = explode('|', $record->fingerprint);
            if (count($parts) !== 2) {
                continue;
            }

            $class = str_replace('.', '\\', $parts[0]);
            if (! class_exists($class)) {
                continue;
            }

            $key = $class.'|'.$parts[1];
            if (isset($models[$key])) {
                $result[] = $models[$key];
            }
        }

        return new Collection($result);
    }

    /**
     * Récupère les IDs et les classes de modèles groupés.
     *
     * @return array<string, array<int|string>>
     */
    public function getGroupedIdsByClass(): array
    {
        $grouped = [];
        foreach ($this->items as $record) {
            $parts = explode('|', $record->fingerprint);
            if (count($parts) !== 2) {
                continue;
            }

            $namespace = $parts[0];
            $id = $parts[1];
            $class = str_replace('.', '\\', $namespace);

            if (! class_exists($class)) {
                continue;
            }

            if (! isset($grouped[$class])) {
                $grouped[$class] = [];
            }
            $grouped[$class][] = $id;
        }

        return $grouped;
    }

    /**
     * Vérifie si un résultat appartient à un namespace.
     */
    public function belongsToNamespace(string $namespace): bool
    {
        foreach ($this->items as $record) {
            if (str_starts_with($record->fingerprint, $namespace.'|')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si un résultat appartient à l'un des namespaces.
     */
    public function belongsToAnyNamespace(array $namespaces): bool
    {
        foreach ($this->items as $record) {
            foreach ($namespaces as $namespace) {
                if (str_starts_with($record->fingerprint, $namespace.'|')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Groupe les résultats par namespace.
     *
     * @return array<string, self>
     */
    public function groupByNamespace(): array
    {
        $groups = [];
        foreach ($this->items as $record) {
            $parts = explode('|', $record->fingerprint);
            if (count($parts) !== 2) {
                continue;
            }

            $namespace = $parts[0];

            if (! isset($groups[$namespace])) {
                $groups[$namespace] = new self;
            }
            $groups[$namespace]->add($record);
        }

        return $groups;
    }
}
