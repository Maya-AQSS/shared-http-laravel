<?php

declare(strict_types=1);

namespace Maya\Http\Data;

use Maya\Http\Http\Requests\PaginatedFilterRequest;

/**
 * DTO base para criterios de filtrado paginado.
 *
 * Transporta los parámetros de paginación y ordenamiento comunes a todos los
 * listados del ecosistema. Las subclases extienden el constructor con sus
 * propios filtros de dominio y sobrescriben `fromRequest()` para mapearlos.
 *
 * Uso típico:
 *
 *     final class AuditFilterDto extends FilterDto
 *     {
 *         public function __construct(
 *             int $page = 1,
 *             int $perPage = 15,
 *             ?string $sortBy = null,
 *             string $sortDir = 'desc',
 *             ?string $search = null,
 *             public readonly ?string $userId = null,
 *         ) {
 *             parent::__construct($page, $perPage, $sortBy, $sortDir, $search);
 *         }
 *
 *         public static function fromRequest(PaginatedFilterRequest $request): static
 *         {
 *             $base = parent::fromRequest($request);
 *             return new static(..., userId: $request->input('user_id'));
 *         }
 *     }
 */
readonly class FilterDto
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 15,
        public readonly ?string $sortBy = null,
        public readonly string $sortDir = 'desc',
        public readonly ?string $search = null,
    ) {}

    /**
     * Construye un FilterDto a partir de un PaginatedFilterRequest validado.
     * Las subclases deben sobrescribir este método para añadir campos propios.
     */
    public static function fromRequest(PaginatedFilterRequest $request): static
    {
        return new static(
            page: $request->getPage(),
            perPage: $request->getPerPage(),
            sortBy: $request->getSortBy(),
            sortDir: $request->getSortDir(),
            search: $request->input('search'),
        );
    }
}
