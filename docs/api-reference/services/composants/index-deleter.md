# IndexDeleter - Référence Technique

## Description

Service dédié à la suppression des documents indexés et de leurs tokens associés depuis la base de données.

## Hiérarchie / Implémentations

```
IndexDeleter (final)
    └── Dépendances : IndexedDocumentRepository, IndexedTokenRepository
```

## Rôle principal

Centralise toutes les opérations de suppression liées à l'index :

- Suppression d'un document unique (avec cascade automatique sur les tokens)
- Suppression multiple par collection de fingerprints
- Vidage complet de l'index (documents + tokens)

## API / Méthodes publiques

### `__construct(IndexedDocumentRepository $documentRepository, IndexedTokenRepository $tokenRepository)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$documentRepository` | `IndexedDocumentRepository` | Repository pour les documents |
| `$tokenRepository` | `IndexedTokenRepository` | Repository pour les tokens |

**Retourne :** `void`

**Exemple :**
```php
$deleter = new IndexDeleter(
    new IndexedDocumentRepository(),
    new IndexedTokenRepository()
);
```

---

### `delete(IndexableFingerPrintVO $fingerPrint): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrint` | `IndexableFingerPrintVO` | Le fingerprint du document à supprimer |

**Retourne :** `void`

**Exceptions :** Aucune (si le document n'existe pas, rien ne se passe)

**Exemple :**
```php
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$deleter->delete($fingerPrint);

// Le document et ses tokens sont supprimés (cascade via clé étrangère)
```

---

### `deleteMany(IndexableFingerPrintVOCollection $fingerPrints): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$fingerPrints` | `IndexableFingerPrintVOCollection` | Collection de fingerprints à supprimer |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$collection = new IndexableFingerPrintVOCollection();
$collection->add(new IndexableFingerPrintVO('App.Models.User|123'));
$collection->add(new IndexableFingerPrintVO('App.Models.User|456'));
$collection->add(new IndexableFingerPrintVO('App.Models.Product|789'));

$deleter->deleteMany($collection);

// Tous les documents correspondants sont supprimés
```

---

### `clear(): void`

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
// Supprime TOUS les documents et TOUS les tokens
$deleter->clear();

// L'index est complètement vidé
```

## Cas d'utilisation

### Cas 1 : Suppression d'un utilisateur (entité unique)

```php
<?php

use AndyDefer\LaravelHermes\Services\Composants\IndexDeleter;
use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;

$userId = 123;
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|' . $userId);

$deleter->delete($fingerPrint);

// Le document de l'utilisateur 123 et tous ses tokens sont supprimés
```

### Cas 2 : Nettoyage d'un tenant complet

```php
<?php

use AndyDefer\LaravelHermes\Services\Composants\IndexDeleter;
use AndyDefer\LaravelHermes\Repositories\IndexedDocumentRepository;

$tenant = 'company_abc';
$documentRepo = new IndexedDocumentRepository();
$deleter = new IndexDeleter($documentRepo, new IndexedTokenRepository());

// 1. Récupérer tous les documents du tenant
$documents = $documentRepo->findByClusterKeyValue('tenant', $tenant);

// 2. Construire la collection de fingerprints
$fingerPrints = new IndexableFingerPrintVOCollection();
foreach ($documents as $doc) {
    $fingerPrints->add($doc->getFingerPrintVO());
}

// 3. Supprimer en masse
$deleter->deleteMany($fingerPrints);
```

### Cas 3 : Réinitialisation complète de l'index (avant un reindexing)

```php
<?php

// Pour un reindexing complet
$deleter->clear();

// Tous les documents et tokens sont supprimés
// L'index est prêt pour une nouvelle indexation
```

### Cas 4 : Suppression avec vérification préalable

```php
<?php

use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;

class UserService
{
    private IndexDeleter $deleter;

    public function deleteUser(int $userId): void
    {
        $fingerPrint = new IndexableFingerPrintVO('App.Models.User|' . $userId);

        // Vérifier si le document existe avant de supprimer
        if ($this->deleter->getDocumentRepository()->existsByFingerPrint($fingerPrint)) {
            $this->deleter->delete($fingerPrint);
            echo "Utilisateur $userId supprimé de l'index\n";
        } else {
            echo "Utilisateur $userId non trouvé dans l'index\n";
        }
    }
}
```

## Flux d'exécution

### Suppression d'un document

```
delete(IndexableFingerPrintVO $fingerPrint)
    ↓
documentRepository->deleteByFingerPrint($fingerPrint)
    ↓
DELETE FROM indexed_documents WHERE fingerprint = ?
    ↓
[DELETE CASCADE] - Les tokens sont automatiquement supprimés
```

### Suppression multiple

```
deleteMany(IndexableFingerPrintVOCollection $fingerPrints)
    ↓
foreach ($fingerPrints as $fingerPrint)
    ↓
documentRepository->deleteByFingerPrint($fingerPrint)
    ↓
DELETE FROM indexed_documents WHERE fingerprint = ?
    (exécuté N fois)
```

### Vidage complet

```
clear()
    ↓
tokenRepository->getModel()->newQuery()->delete()
    ↓
DELETE FROM indexed_tokens (tous)
    ↓
documentRepository->getModel()->newQuery()->delete()
    ↓
DELETE FROM indexed_documents (tous)
```

## Intégration

### Avec `IndexerService`

```php
$indexer = new IndexerService($writer, $deleter, $searcher);
$indexer->delete($fingerPrint);
$indexer->deleteMany($fingerPrints);
$indexer->clear();
```

### Avec `IndexWriter` (pour le refresh)

```php
// Refresh = delete + index
$deleter->delete($fingerPrint);
$writer->index($record);
```

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `delete()` | O(log n) | Suppression par clé unique (fingerprint) |
| `deleteMany()` | O(n log n) | N suppressions individuelles |
| `clear()` | O(1) | Troncature des tables (très rapide) |

**Optimisation :** La suppression des tokens est automatique via `ON DELETE CASCADE` (pas de requête supplémentaire).

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |
| Laravel 12.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Services\Composants\IndexDeleter;
use AndyDefer\LaravelHermes\Repositories\IndexedDocumentRepository;
use AndyDefer\LaravelHermes\Repositories\IndexedTokenRepository;
use AndyDefer\LaravelHermes\Collections\IndexableFingerPrintVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;

// 1. Initialisation
$documentRepo = new IndexedDocumentRepository();
$tokenRepo = new IndexedTokenRepository();
$deleter = new IndexDeleter($documentRepo, $tokenRepo);

// 2. Suppression d'un seul document
$fingerPrint = new IndexableFingerPrintVO('App.Models.User|123');
$deleter->delete($fingerPrint);
echo "Document supprimé\n";

// 3. Suppression multiple
$collection = new IndexableFingerPrintVOCollection();
$collection->add(new IndexableFingerPrintVO('App.Models.User|456'));
$collection->add(new IndexableFingerPrintVO('App.Models.User|789'));
$collection->add(new IndexableFingerPrintVO('App.Models.Product|101'));

$deleter->deleteMany($collection);
echo "3 documents supprimés\n";

// 4. Vérification du nombre de documents restants
$remaining = $documentRepo->getModel()->newQuery()->count();
echo "Documents restants : $remaining\n";

// 5. Vidage complet
$deleter->clear();
echo "Index entièrement vidé\n";

$remaining = $documentRepo->getModel()->newQuery()->count();
echo "Documents restants après clear : $remaining\n";
```

## Voir aussi

- `IndexedDocumentRepository` - Gestion des documents
- `IndexedTokenRepository` - Gestion des tokens
- `IndexWriter` - Service d'indexation
- `IndexerService` - Service principal d'indexation
- `IndexableFingerPrintVO` - Value Object pour les fingerprints
- `IndexableFingerPrintVOCollection` - Collection de fingerprints