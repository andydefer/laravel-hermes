# IndexWriter - Référence Technique

## Description

Service d'indexation qui transforme des `IndexableRecord` en documents persistés avec leurs tokens associés (n-grammes lexicaux et métaphones).

## Hiérarchie / Implémentations

```
IndexWriter (final)
    └── Dépendances : IndexedDocumentRepository, IndexedTokenRepository, TextNormalizerInterface, NGramGeneratorInterface, IndexerConfig
```

## Rôle principal

Assure l'intégralité du pipeline d'indexation :

- Persistance des documents
- Génération des tokens (lexicaux et phonétiques)
- Bufferisation pour l'insertion en masse
- Suivi de la fréquence des tokens
- Gestion des textes longs par chunking

## API / Méthodes publiques

### `__construct(IndexedDocumentRepository $documentRepository, IndexedTokenRepository $tokenRepository, TextNormalizerInterface $textNormalizer, NGramGeneratorInterface $ngramGenerator, IndexerConfig $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$documentRepository` | `IndexedDocumentRepository` | Repository des documents |
| `$tokenRepository` | `IndexedTokenRepository` | Repository des tokens |
| `$textNormalizer` | `TextNormalizerInterface` | Service de normalisation des textes |
| `$ngramGenerator` | `NGramGeneratorInterface` | Générateur de n-grammes |
| `$config` | `IndexerConfig` | Configuration de l'indexeur |

**Retourne :** `void`

---

### `index(IndexableRecord $entity): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$entity` | `IndexableRecord` | L'enregistrement à indexer |

**Retourne :** `void`

**Exceptions :** `QueryException` (PDO), `InvalidArgumentException`

**Exemple :**
```php
$record = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
    cluster: new ClusterVO('model:User|tenant:company_abc'),
    data: StrictAssociative::from(['name' => 'John Doe'])
);

$writer->index($record);
```

---

### `indexMany(IndexableRecordCollection $records): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$records` | `IndexableRecordCollection` | Collection d'enregistrements à indexer |

**Retourne :** `void`

**Exceptions :** `QueryException` (PDO)

**Exemple :**
```php
$records = new IndexableRecordCollection();
$records->add($record1);
$records->add($record2);
$records->add($record3);

$writer->indexMany($records);
```

## Cas d'utilisation

### Cas 1 : Indexation d'une entité unique

```php
<?php

$user = User::find(123);
$cluster = new ClusterVO('model:User|tenant:company_abc|env:production');

$record = IndexableRecordFactory::convert($user, $cluster);
$writer->index($record);
```

### Cas 2 : Indexation en masse

```php
<?php

$users = User::where('active', true)->get();
$cluster = new ClusterVO('model:User|tenant:company_abc|env:production');

$records = new IndexableRecordCollection();
foreach ($users as $user) {
    $records->add(IndexableRecordFactory::convert($user, $cluster));
}

$writer->indexMany($records);
```

### Cas 3 : Indexation avec données imbriquées

```php
<?php

$record = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
    cluster: new ClusterVO('model:User'),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'profile' => [
            'bio' => 'Software Developer',
            'social' => [
                'twitter' => '@johndoe'
            ]
        ]
    ])
);

$writer->index($record);
// Les tokens seront générés pour :
// - name: John Doe
// - profile.bio: Software Developer
// - profile.social.twitter: @johndoe
```

### Cas 4 : Textes longs (chunking automatique)

```php
<?php

$longText = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit... (500 caractères)';

$record = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.Product|1'),
    cluster: new ClusterVO('model:Product'),
    data: StrictAssociative::from([
        'description' => $longText
    ])
);

$writer->index($record);
// Le texte sera :
// 1. Tronqué à 100 caractères
// 2. Découpé en chunks de 25 caractères
// 3. Chaque chunk est traité comme un texte court
```

## Flux d'exécution

```
┌─────────────────────────────────────────────────────────────────────────────┐
│ IndexWriter::index(IndexableRecord $entity)                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. resetBuffers()                                                         │
│     → Vider les buffers de tokens                                          │
│                                                                             │
│  2. Créer IndexedDocumentRecord                                            │
│     → fingerprint, cluster, data                                          │
│                                                                             │
│  3. $document = documentRepository->create($documentRecord)                │
│     → Persister le document                                                │
│                                                                             │
│  4. indexDocumentData($document, $data)                                    │
│     → Parcours récursif des données                                        │
│                                                                             │
│     Pour chaque champ :                                                    │
│     ├── Si array associatif → récursion                                    │
│     ├── Si array simple → concaténation et extraction des tokens           │
│     └── Si string → extraction des tokens                                  │
│                                                                             │
│  5. extractAndBufferTokens()                                               │
│     ├── Si texte > 100 caractères → troncature                             │
│     ├── Si texte > 25 caractères → extractAndBufferTokensLong()            │
│     │   └── Découpage en chunks de 25 caractères                           │
│     └── Sinon → extractAndBufferTokensShort()                              │
│                                                                             │
│  6. processWord()                                                          │
│     ├── Génération des n-grammes LEXICAL                                   │
│     └── Génération des n-grammes METAPHONE                                 │
│                                                                             │
│  7. addToBuffer()                                                          │
│     ├── Si token existe → incrémenter la fréquence                         │
│     └── Sinon → ajouter au buffer                                          │
│                                                                             │
│  8. Si buffer plein → flushTokens()                                        │
│                                                                             │
│  9. flushTokens()                                                          │
│     ├── Chunker l'insertion en lots de 1000 tokens                         │
│     ├── Bulk insert des nouveaux tokens                                    │
│     └── Incrémenter la fréquence des tokens existants                      │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Document invalide | `ModelNotFoundException` | Pas d'exception native |
| Token invalide | `QueryException` | Erreur PDO (contraintes) |
| Buffer trop grand | Aucune | Flush automatique |

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `index()` simple | O(n) | n = nombre de tokens générés |
| `indexMany()` | O(n) | n = nombre total de tokens |
| Bufferisation | O(1) | Réduit les requêtes SQL |
| Chunking | O(k) | k = nombre de chunks |

**Optimisations intégrées :**

| Optimisation | Impact |
|--------------|--------|
| Troncature à 100 caractères | Réduit l'explosion de tokens |
| Buffer de 5000 tokens | 1 requête au lieu de 5000 |
| Chunking d'insertion (1000) | Évite `too many placeholders` |
| Chunking des textes longs (25) | Meilleure granularité |

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

use AndyDefer\LaravelHermes\Services\Composants\IndexWriter;
use AndyDefer\LaravelHermes\Records\IndexableRecord;
use AndyDefer\LaravelHermes\ValueObjects\IndexableFingerPrintVO;
use AndyDefer\LaravelHermes\ValueObjects\ClusterVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

// 1. Initialisation
$writer = new IndexWriter(
    new IndexedDocumentRepository(),
    new IndexedTokenRepository(),
    new TextNormalizerService(),
    new NGramGeneratorService(),
    new IndexerConfig()
);

// 2. Création d'un enregistrement
$record = new IndexableRecord(
    finger_print: new IndexableFingerPrintVO('App.Models.User|123'),
    cluster: new ClusterVO('model:User|tenant:company_abc|env:production'),
    data: StrictAssociative::from([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'description' => 'Software Developer with 10 years of experience',
        'profile' => [
            'bio' => 'Passionate about PHP and Laravel',
            'social' => [
                'twitter' => '@johndoe'
            ]
        ],
        'tags' => ['php', 'laravel', 'mysql']
    ])
);

// 3. Indexation
$writer->index($record);

// 4. Indexation multiple
$records = new IndexableRecordCollection();
$records->add($record1);
$records->add($record2);
$writer->indexMany($records);

// 5. Résultat : les tokens sont générés pour tous les champs
// - name: john, ohn, hnd, ndo, doe, ...
// - email: joh, ohn, hn@, n@e, @ex, exa, ...
// - description: sof, oft, ftw, twa, war, are, ...
// - profile.bio: pas, ass, ssi, sio, ion, ...
// - profile.social.twitter: @jo, joh, ohn, hnd, ...
// - tags: php, lar, ara, rav, ave, vel, vue, uej, ejs, ...
```

## Voir aussi

- `IndexedDocumentRepository` - Gestion des documents
- `IndexedTokenRepository` - Gestion des tokens
- `IndexableRecord` - Record d'entrée
- `IndexedDocumentRecord` - Record de document
- `GramType` - Types de tokens (LEXICAL, METAPHONE)
- `NGramGeneratorInterface` - Générateur de n-grammes
- `TextNormalizerInterface` - Normalisation des textes
- `IndexerConfig` - Configuration de l'indexeur