<?php

declare(strict_types=1);

namespace Sigmie\Document;

use ArrayAccess;
use Countable;
use Generator;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Document\Actions as DocumentActions;
use Sigmie\Document\Contracts\DocumentCollection;
use Sigmie\Index\Actions as IndexActions;
use Traversable;

class AliveCollection implements ArrayAccess, Countable, DocumentCollection
{
    use DocumentActions;
    use IndexActions;
    use LazyEach;
    use Search;

    protected string $refresh = 'false';

    public function __construct(
        protected string $name,
        ElasticsearchConnection $connection
    ) {
        $this->setElasticsearchConnection($connection);
    }

    public function refresh()
    {
        $this->refresh = 'true';

        return $this;
    }

    public function replace(Document $document): Document
    {
        $doc = $this->updateDocument($this->name, $document, $this->refresh);

        return $doc;
    }

    public function all(): Generator
    {
        return $this->getIterator();
    }

    public function has(string $_id): bool
    {
        return $this->documentExists($this->name, $_id);
    }

    public function add(Document $document): Document
    {
        $document = $this->createDocument($this->name, $document, $this->refresh);

        return $document;
    }

    public function merge(array $docs): AliveCollection
    {
        $collection = $this->upsertDocuments($this->name, $docs, $this->refresh);

        return $this;
    }

    public function toArray(): array
    {
        return iterator_to_array($this->getIterator());
    }

    public function clear(): void
    {
        $this->indexAPICall("{$this->name}/_delete_by_query?refresh={$this->refresh}", 'POST', [
            'query' => ['match_all' => (object) []],
        ]);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function remove(array|string $_id): bool
    {
        if (is_array($_id)) {
            $this->deleteDocuments($this->name, $_id, $this->refresh);
        }

        return $this->deleteDocument($this->name, $_id, $this->refresh);
    }

    public function get(string $_id): ?Document
    {
        return $this->getDocument($this->name, $_id);
    }

    public function count(): int
    {
        $res = $this->countAPICall($this->name);

        return $res->json('count');
    }

    public function getIterator(): Traversable
    {
        return $this->indexGenerator();
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    public function offsetGet(mixed $offset): null|Document
    {
        return $this->get((string) $offset);
    }

    public function offsetSet(mixed $_id, mixed $doc): void
    {
        $this->add($doc);
    }

    public function offsetUnset(mixed $_id): void
    {
        $this->remove((string) $_id);
    }
}
