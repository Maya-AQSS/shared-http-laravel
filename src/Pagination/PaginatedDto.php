<?php

declare(strict_types=1);

namespace Maya\Http\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use JsonSerializable;

/**
 * DTO inmutable que normaliza la salida de paginación cross-ecosystem.
 *
 * Diseñado para envolverse en una `JsonResource` o devolverse directamente
 * desde un controller. La estructura serializada replica el envelope Laravel
 * (`current_page`, `data`, `links`, `meta` en formato flat) para compatibilidad
 * con consumidores existentes.
 *
 * Uso típico:
 *
 *     $page = $this->repository->paginate(15);
 *     $dto  = PaginatedDto::fromPaginator($page, fn ($m) => DocumentDto::fromModel($m));
 *     return response()->json($dto);
 *
 * @template TItem
 */
final readonly class PaginatedDto implements JsonSerializable
{
    /**
     * @param  list<TItem>  $items
     * @param  array<int, array{url: string|null, label: string, active: bool}>  $links
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $perPage,
        public int $total,
        public int $lastPage,
        public ?int $from,
        public ?int $to,
        public ?string $firstPageUrl,
        public ?string $lastPageUrl,
        public ?string $prevPageUrl,
        public ?string $nextPageUrl,
        public string $path,
        public array $links,
    ) {}

    /**
     * Construye el DTO a partir de un paginator Laravel, aplicando `$mapper` a
     * cada item para convertir entidades Eloquent en DTOs de salida.
     *
     * @template TModel
     * @template TMapped
     * @param  LengthAwarePaginator<TModel>  $page
     * @param  callable(TModel): TMapped  $mapper
     * @return self<TMapped>
     */
    public static function fromPaginator(LengthAwarePaginator $page, callable $mapper): self
    {
        return new self(
            items: array_map($mapper, $page->items()),
            currentPage: $page->currentPage(),
            perPage: $page->perPage(),
            total: $page->total(),
            lastPage: $page->lastPage(),
            from: $page->firstItem(),
            to: $page->lastItem(),
            firstPageUrl: $page->url(1),
            lastPageUrl: $page->url($page->lastPage()),
            prevPageUrl: $page->previousPageUrl(),
            nextPageUrl: $page->nextPageUrl(),
            path: $page->path() ?? '',
            links: array_values($page->linkCollection()->toArray()),
        );
    }

    public function jsonSerialize(): array
    {
        return [
            'current_page' => $this->currentPage,
            'data' => $this->items,
            'first_page_url' => $this->firstPageUrl,
            'from' => $this->from,
            'last_page' => $this->lastPage,
            'last_page_url' => $this->lastPageUrl,
            'links' => $this->links,
            'next_page_url' => $this->nextPageUrl,
            'path' => $this->path,
            'per_page' => $this->perPage,
            'prev_page_url' => $this->prevPageUrl,
            'to' => $this->to,
            'total' => $this->total,
        ];
    }
}
