<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Contracts\Services;

interface SimilarityCalculatorInterface
{
    /**
     * Calcule la similarité globale entre deux textes.
     * Combine similarité textuelle (60%) et phonétique (40%).
     *
     * @param  string  $text1  Premier texte (requête)
     * @param  string  $text2  Deuxième texte (cible)
     * @return float Score de similarité entre 0 et 1
     */
    public function calculateSimilarity(string $text1, string $text2): float;
}
