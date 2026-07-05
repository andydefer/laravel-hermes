<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Contracts\Services;

use AndyDefer\LaravelHermes\Collections\CompletionResultRecordCollection;
use AndyDefer\LaravelHermes\Collections\SearchResultRecordCollection;
use AndyDefer\LaravelHermes\Collections\SuggestionResultRecordCollection;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelHermes\Records\SuggestionRequestRecord;

/**
 * Interface for the main Hermes service.
 *
 * Provides three core operations:
 * - Completion: Autocomplete partial text
 * - Suggestion: Find alternative words based on similarity
 * - Search: Full-text search with document results
 */
interface HermesInterface
{
    /**
     * Completes a partial text with existing tokens.
     *
     * @param  CompletionRequestRecord  $request  The completion request
     * @return CompletionResultRecordCollection Collection of completion results
     */
    public function complete(CompletionRequestRecord $request): CompletionResultRecordCollection;

    /**
     * Suggests alternative words based on similarity matching.
     *
     * @param  SuggestionRequestRecord  $request  The suggestion request
     * @return SuggestionResultRecordCollection Collection of suggestion results
     */
    public function suggest(SuggestionRequestRecord $request): SuggestionResultRecordCollection;

    /**
     * Performs a full-text search returning complete documents.
     *
     * @param  SearchRequestRecord  $request  The search request
     * @return SearchResultRecordCollection Collection of search results with document data
     */
    public function search(SearchRequestRecord $request): SearchResultRecordCollection;
}
