# SimilarityCalculatorService - Référence Technique

## Description

Service de calcul de similarité entre deux chaînes de texte combinant similarité lexicale (n-grammes) et phonétique (métaphone) avec des poids configurables.

## Hiérarchie / Implémentations

```
SimilarityCalculatorInterface
    └── SimilarityCalculatorService (final)
```

## Rôle principal

Calcule un score de similarité entre 0.0 et 1.0 en comparant deux textes via un algorithme en plusieurs étapes :

1. Normalisation des textes (minuscules, suppression des accents)
2. Normalisation des nombres (2.0.1 → 2 0 1)
3. Extraction et fusion des mots courts
4. Construction d'une matrice de similarité entre toutes les paires de mots
5. Sélection des meilleurs matchs un-à-un
6. Calcul du score moyen
7. Correction par facteur de longueur

## API / Méthodes publiques

### `calculateSimilarity(string $text1, string $text2): float`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$text1` | `string` | Premier texte à comparer |
| `$text2` | `string` | Second texte à comparer |

**Retourne :** `float` - Score de similarité entre 0.0 et 1.0

**Exemple :**
```php
$service = new SimilarityCalculatorService(...);
$score = $service->calculateSimilarity('John Doe', 'Jon Doe');
// Returns ~0.85
```

## Détails de l'algorithme

### 1. Normalisation des nombres

Les nombres avec points sont séparés en tokens individuels :

```
"Version 2.0.1" → "Version 2 0 1"
"v2.0.1" → "v 2 0 1"
```

### 2. Extraction et fusion des mots

Les mots plus courts que la longueur minimale configurée sont fusionnés avec le mot suivant :

```
"a b c" (min_length=2) → "abc"
```

### 3. Similarité entre mots

Chaque paire de mots est comparée via :

- **Similarité lexicale** : Vecteurs de n-grammes pondérés (cosine similarity)
- **Similarité phonétique** : Vecteurs de métaphones pondérés (cosine similarity)
- **Bonus** : Lettres communes et bigrammes communs
- **Bonus Levenshtein** : Distance de Levenshtein lexicale et métaphonique

**Formule :**
```
score = (lexical * textualWeight) + (phonetic * phoneticWeight) + bonus + levenshteinBonus
```

### 4. Sélection des meilleurs matchs

Algorithme glouton qui sélectionne les paires de mots avec les scores les plus élevés sans réutiliser les mêmes lignes ou colonnes.

### 5. Correction de longueur

Pénalise les textes où :
- Un texte est significativement plus court que l'autre (couverture < 70%)
- Le texte le plus court ne couvre pas une proportion suffisante des lettres uniques

## Cas d'utilisation

### Cas 1 : Comparaison de noms avec faute de frappe

```php
$score = $service->calculateSimilarity('John Doe', 'Jon Doe');
// ~0.85 - Les métaphones "JN" pour John et Jon sont identiques
```

### Cas 2 : Comparaison de textes avec accents

```php
$score = $service->calculateSimilarity('Café', 'Cafe');
// > 0.9 - Normalisation des accents
```

### Cas 3 : Comparaison de versions logicielles

```php
$score = $service->calculateSimilarity('Version 2.0.1', 'Version 2.0.2');
// > 0.8 - Normalisation des nombres
```

### Cas 4 : Texte partiel (préfixe)

```php
$score = $service->calculateSimilarity('The quick brown fox', 'The quick brown fox jumps over the lazy dog');
// ~0.83 - Correction de longueur appliquée
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Textes vides | - | Retourne 0.0 (pas d'exception) |
| Erreur de normalisation | - | Dépend de `TextNormalizerInterface` |
| Erreur de génération de n-grammes | - | Dépend de `NGramGeneratorInterface` |

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `calculateSimilarity()` | O(n × m) | n = mots du texte1, m = mots du texte2 |
| `calculateWordSimilarity()` | O(d) | d = dimension du vecteur |
| Cache des vecteurs | O(1) | Vecteurs mis en cache par mot |

**Temps typique :** ~10 ms pour 5x5 mots (25 comparaisons)

**Optimisations :**
- Cache des vecteurs lexicaux et phonétiques
- Timeout configurable (défaut: 0.5s)
- Sampling pour les textes longs (> 2500 paires)

## Configuration

Les paramètres sont configurés via `SimilarityConfigInterface` :

| Paramètre | Défaut | Description |
|-----------|--------|-------------|
| `getGramMinSize()` | 2 | Taille minimale des n-grammes |
| `getGramMaxSize()` | 4 | Taille maximale des n-grammes |
| `getVectorDimension()` | 128 | Dimension des vecteurs |
| `getTextualWeight()` | 0.6 | Poids de la similarité lexicale |
| `getPhoneticWeight()` | 0.4 | Poids de la similarité phonétique |
| `getLetterBonus()` | 0.05 | Bonus par lettre commune |
| `getBigramBonus()` | 0.03 | Bonus par bigramme commun |
| `getMetaphoneBonusValue()` | 0.175 | Bonus métaphone |
| `getLexicalBonusHigh()` | 0.275 | Bonus lexical fort (distance < 2) |
| `getMaxWords()` | 50 | Nombre max de mots avant sampling |
| `getMaxPairs()` | 2500 | Nombre max de paires avant sampling |
| `getTimeoutSeconds()` | 0.5 | Timeout en secondes |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Dépendances

- `TextNormalizerInterface` - Normalisation des textes
- `NGramGeneratorInterface` - Génération des n-grammes
- `WordVectorGeneratorInterface` - Génération et normalisation des vecteurs
- `SimilarityConfigInterface` - Configuration des paramètres

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelHermes\Services\SimilarityCalculatorService;
use AndyDefer\LaravelHermes\Configs\SimilarityConfig;

$config = new SimilarityConfig($app->make(ConfigRepository::class));
$service = new SimilarityCalculatorService(
    normalizer: $app->make(TextNormalizerInterface::class),
    ngramGenerator: $app->make(NGramGeneratorInterface::class),
    vectorGenerator: $app->make(WordVectorGeneratorInterface::class),
    config: $config
);

// Comparaison simple
$score1 = $service->calculateSimilarity('John Doe', 'Jon Doe');
echo "Similarité: " . round($score1, 3) . "\n"; // ~0.85

// Texte avec accents
$score2 = $service->calculateSimilarity('Café', 'Cafe');
echo "Similarité: " . round($score2, 3) . "\n"; // > 0.9

// Version logicielle
$score3 = $service->calculateSimilarity('Version 2.0.1', 'Version 2.0.2');
echo "Similarité: " . round($score3, 3) . "\n"; // > 0.8

// Longs textes avec sampling
$text1 = str_repeat('Lorem ipsum dolor sit amet ', 100);
$text2 = str_repeat('Lorem ipsum dolor sit amet ', 100);
$score4 = $service->calculateSimilarity($text1, $text2);
echo "Similarité: " . round($score4, 3) . "\n"; // 1.0
```

## Voir aussi

- `SimilarityCalculatorInterface` - Interface du service
- `SimilarityConfigInterface` - Interface de configuration
- `TextNormalizerInterface` - Service de normalisation
- `NGramGeneratorInterface` - Service de génération de n-grammes
- `WordVectorGeneratorInterface` - Service de génération de vecteurs
- `Laravel Hermes - Documentation` - Documentation générale du package