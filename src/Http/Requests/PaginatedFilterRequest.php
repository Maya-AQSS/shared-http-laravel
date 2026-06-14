<?php

declare(strict_types=1);

namespace Maya\Http\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $sortByRules = ['nullable', 'string', 'max:50'];

        $allowed = $this->allowedSortFields();
        if ($allowed !== []) {
            // Whitelist de columnas ordenables: un sort_by fuera de la lista
            // recibe 422 (fail-fast) en vez de llegar crudo a un ORDER BY.
            $sortByRules[] = Rule::in($allowed);
        }

        return array_merge([
            'page'     => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'sort_by'  => $sortByRules,
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ], $this->filterRules());
    }

    /**
     * Define las reglas de validación específicas del filtro de dominio.
     *
     * @return array<string, list<mixed>>
     */
    abstract protected function filterRules(): array;

    /**
     * Whitelist de columnas permitidas en `sort_by`. Vacío = sin restricción
     * (retrocompatible), pero las subclases DEBEN declararla para evitar
     * inyección de identificador de columna en cláusulas ORDER BY. El
     * repositorio sigue siendo la segunda línea de defensa.
     *
     * @return list<string>
     */
    protected function allowedSortFields(): array
    {
        return [];
    }

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
        $sortBy = $this->input('sort_by');
        if ($sortBy === null) {
            return null;
        }

        // Defensa en profundidad: aunque rules() ya rechaza valores fuera de
        // la whitelist, nunca devolvemos una columna no permitida al repo.
        $allowed = $this->allowedSortFields();
        if ($allowed !== [] && ! in_array($sortBy, $allowed, true)) {
            return null;
        }

        return $sortBy;
    }

    public function getSortDir(): string
    {
        return $this->input('sort_dir', 'desc');
    }
}
