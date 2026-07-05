# HermesRepository - Référence Technique

## Description

Repository pour l'interrogation des tokens indexés avec correspondance par n-grammes et filtrage contextuel.

## Hiérarchie / Implémentations

```
HermesRepositoryInterface
    └── HermesRepository (final)
```

## Rôle principal

Fournit des méthodes pour rechercher des tokens par n-grammes avec support de filtrage par champs, namespace, cluster, chargement des relations document et regroupement par document.

## API / Méthodes publiques

### `findTokensByNgrams(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null, int $limit = 100, bool $withDocument = false): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array<string>` | Les n-grammes à rechercher |
| `$contexts` | `?ContextFilterVOCollection` | Filtres de contexte (logique OU entre les contextes) |
| `$fields` | `?StringTypedCollection` | Noms des champs pour restreindre la recherche |
| `$limit` | `int` | Nombre maximum de résultats |
| `$withDocument` | `bool` | Charger la relation document en eager load |

**Retourne :** `Collection` - Collection de modèles de tokens

**Exemple :**
```php
$ngrams = ['joh', 'ohn', 'john'];
$tokens = $repository->findTokensByNgrams($ngrams, limit: 10);

foreach ($tokens as $token) {
    echo $token->token; // 'joh', 'ohn', ou 'john'
    echo $token->original_text; // 'John'
    echo $token->field; // 'name'
}
```

---

### `getAllTokensByNgrams(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null): Collection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array<string>` | Les n-grammes à rechercher |
| `$contexts` | `?ContextFilterVOCollection` | Filtres de contexte (logique OU entre les contextes) |
| `$fields` | `?StringTypedCollection` | Noms des champs pour restreindre la recherche |

**Retourne :** `Collection` - Collection de tokens distincts

**Exemple :**
```php
$ngrams = ['joh'];
$allTokens = $repository->getAllTokensByNgrams($ngrams);
// Retourne tous les tokens distincts contenant 'joh'
```

---

### `getTokensGroupedByDocument(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null, float $minSimilarity = 0.0): array`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array<string>` | Les n-grammes à rechercher |
| `$contexts` | `?ContextFilterVOCollection` | Filtres de contexte (logique OU entre les contextes) |
| `$fields` | `?StringTypedCollection` | Noms des champs pour restreindre la recherche |
| `$minSimilarity` | `float` | Seuil de similarité minimum (réservé pour usage futur) |

**Retourne :** `array<string, array>` - Tableau groupé par `document_id` avec métadonnées du document et tokens

**Structure de retour :**
```php
[
    'document-id-1' => [
        'document_id' => 'document-id-1',
        'fingerprint' => 'App.Models.User|123',
        'data' => ['name' => 'John Doe', 'email' => 'john@example.com'],
        'tokens' => [
            ['id' => 'token-id', 'token' => 'joh', 'original_text' => 'John', 'field' => 'name']
        ]
    ]
]
```

**Exemple :**
```php
$ngrams = ['joh', 'ohn'];
$grouped = $repository->getTokensGroupedByDocument($ngrams);

foreach ($grouped as $documentId => $data) {
    echo "Document: $documentId\n";
    echo "Fingerprint: {$data['fingerprint']}\n";
    foreach ($data['tokens'] as $token) {
        echo "  Token: {$token['token']} (field: {$token['field']})\n";
    }
}
```

---

### `countTokensByNgrams(array $ngrams, ?ContextFilterVOCollection $contexts = null, ?StringTypedCollection $fields = null): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$ngrams` | `array<string>` | Les n-grammes à rechercher |
| `$contexts` | `?ContextFilterVOCollection` | Filtres de contexte (logique OU entre les contextes) |
| `$fields` | `?StringTypedCollection` | Noms des champs pour restreindre la recherche |

**Retourne :** `int` - Nombre de tokens distincts correspondants

**Exemple :**
```php
$ngrams = ['joh', 'ohn'];
$count = $repository->countTokensByNgrams($ngrams);
echo "Nombre de tokens trouvés: $count";
```

---

## Cas d'utilisation

### Cas 1 : Recherche de tokens pour l'autocomplétion

```php
<?php

// Rechercher des tokens pour un préfixe d'autocomplétion
$prefix = 'joh';
$ngrams = $this->generateNgrams($prefix); // ['joh', 'ohn', 'john']
$tokens = $repository->findTokensByNgrams($ngrams, limit: 10);

$suggestions = $tokens->pluck('original_text')->unique()->values();
// ['John', 'Johanna', 'Johnson']
```

### Cas 2 : Recherche avec filtres de contexte

```php
<?php

// Rechercher des tokens dans un namespace spécifique
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User'));

$ngrams = ['joh', 'ohn'];
$tokens = $repository->findTokensByNgrams($ngrams, contexts: $contexts);

// Résultat: uniquement les tokens des documents User
```

### Cas 3 : Regroupement par document pour recherche

```php
<?php

// Récupérer les tokens groupés par document pour afficher les résultats complets
$ngrams = ['joh', 'ohn', 'john'];
$grouped = $repository->getTokensGroupedByDocument($ngrams);

foreach ($grouped as $documentId => $data) {
    // Afficher les données du document
    echo "Document: {$data['fingerprint']}\n";
    
    // Afficher les tokens correspondants
    foreach ($data['tokens'] as $token) {
        echo "  Match: {$token['original_text']} (field: {$token['field']})\n";
    }
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucun n-gramme fourni | - | Retourne une collection vide |
| Contextes invalides | `InvalidArgumentException` | Dépend de `ContextFilterVO` |
| Erreur de base de données | `QueryException` | Erreur PDO/SQL |

---

## Intégration

Ce repository s'intègre avec :

- **`IndexedTokenRepository`** : Repository de Laravel Indexer pour l'accès aux tokens
- **`ContextFilterVO`** : Value Object pour le filtrage contextuel
- **`StringTypedCollection`** : Collection typée pour les noms de champs
- **`ContextFilterVOCollection`** : Collection typée pour les contextes

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `findTokensByNgrams()` | O(n log n) | n = nombre de tokens correspondants |
| `getAllTokensByNgrams()` | O(n) | Récupère tous les tokens distincts |
| `getTokensGroupedByDocument()` | O(n) | n = nombre de tokens, avec chargement des documents |
| `countTokensByNgrams()` | O(1) | Utilise `COUNT(DISTINCT)` en base de données |

**Optimisations :**
- Utilise `distinct()` pour éviter les doublons
- Utilise `limit()` pour contrôler la taille du résultat
- Filtres appliqués en base de données (pas en mémoire)

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10.x+ | ✅ Complet |
| Laravel Indexer 0.6.1+ | ✅ Requis |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Repositories\HermesRepository;
use AndyDefer\LaravelHermes\Collections\ContextFilterVOCollection;
use AndyDefer\LaravelHermes\ValueObjects\ContextFilterVO;

$repository = app(HermesRepository::class);

// 1. Recherche simple
$ngrams = ['joh', 'ohn', 'john'];
$tokens = $repository->findTokensByNgrams($ngrams, limit: 20);

// 2. Recherche avec filtres
$contexts = new ContextFilterVOCollection();
$contexts->add(new ContextFilterVO('App.Models.User'));
$contexts->add(new ContextFilterVO(null, 'tenant:company_abc'));

$filteredTokens = $repository->findTokensByNgrams(
    $ngrams,
    contexts: $contexts,
    limit: 10
);

// 3. Regroupement par document
$grouped = $repository->getTokensGroupedByDocument(
    $ngrams,
    contexts: $contexts
);

// 4. Compter les tokens
$count = $repository->countTokensByNgrams($ngrams, contexts: $contexts);
echo "Nombre de tokens: $count\n";
```

---

## Voir aussi

- `HermesRepositoryInterface` - Interface du repository
- `ContextFilterVO` - Value Object de filtrage contextuel
- `IndexedTokenRepository` - Repository de Laravel Indexer
- `Laravel Hermes - Documentation` - Documentation générale du package