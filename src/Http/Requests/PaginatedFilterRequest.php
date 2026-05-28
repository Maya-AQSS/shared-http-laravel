<?php

declare(strict_types=1);

namespace Maya\Http\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest base para todos los listados paginados del ecosistema Maya.
 *
 * Define las reglas de paginación y ordenamiento comunes. Las subclases
 * añaden sus propios filtros de dominio implementando `filterRules()`.
 *
 * Uso típico:
 *
 *     class AuditIndexRequest extends PaginatedFilterRequest
 *     {
 *         protected function filterRules(): array
 *         {
 *             return [
 *                 'user_id'  => ['nullable', 'uuid'],
 *                 'action'   => ['nullable', 'string', 'max:100'],
 *             ];
 *         }
 *     }
 */
abstract class PaginatedFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_merge([
            'page'     => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'sort_by'  => ['nullable', 'string', 'max:50'],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ], $this->filterRules());
    }

    /**
     * Define las reglas de validación específicas del filtro de dominio.
     *
     * @return array<string, list<mixed>>
     */
    abstract protected function filterRules(): array;

    public function getPage(): int
    {
        return (int) $this->input('page', 1);
    }

    public function getPerPage(): int
    {
        return (int) $this->input('per_page', 15);
    }

    public function getSortBy(): ?string
    {
        return $this->input('sort_by');
    }

    public function getSortDir(): string
    {
        return $this->input('sort_dir', 'desc');
    }
}
