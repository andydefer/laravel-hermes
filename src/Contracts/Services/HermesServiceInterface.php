<?php

declare(strict_types=1);

namespace AndyDefer\LaravelHermes\Contracts\Services;

use AndyDefer\LaravelHermes\Collections\CompletionResultRecordCollection;
use AndyDefer\LaravelHermes\Collections\SearchResultRecordCollection;
use AndyDefer\LaravelHermes\Collections\SuggestionResultRecordCollection;
use AndyDefer\LaravelHermes\Records\CompletionRequestRecord;
use AndyDefer\LaravelHermes\Records\SearchRequestRecord;
use AndyDefer\LaravelHermes\Records\SuggestionRequestRecord;

interface HermesInterface
{
    public function complete(CompletionRequestRecord $request): CompletionResultRecordCollection;

    public function suggest(SuggestionRequestRecord $request): SuggestionResultRecordCollection;

    public function search(SearchRequestRecord $request): SearchResultRecordCollection;
}
