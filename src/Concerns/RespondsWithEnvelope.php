<?php

namespace Maya\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Maya\Http\Pagination\PaginatedDto;

/**
 * Estandariza el formato de respuesta JSON `{ data: ... }` que usan todos los
 * microservicios Maya. Reemplaza el patrón disperso de `response()->json(['data' => $x])`.
 *
 * Uso (en cualquier controlador):
 *
 *   class FooController extends Controller {
 *       use \Maya\Http\Concerns\RespondsWithEnvelope;
 *
 *       public function index(): JsonResponse {
 *           return $this->okData($this->service->list());
 *       }
 *   }
 */
trait RespondsWithEnvelope
{
    /**
     * Devuelve `{ data: <payload> }` con el código indicado (200 por defecto).
     *
     * @param  mixed  $data
     */
    protected function okData($data, int $status = 200, array $headers = []): JsonResponse
    {
        if ($data instanceof JsonResource || $data instanceof ResourceCollection) {
            return $data->response()->setStatusCode($status)->withHeaders($headers);
        }

        return response()->json(['data' => $data], $status, $headers);
    }

    /**
     * Devuelve `{ message: <mensaje> }` (útil para confirmaciones sin payload).
     */
    protected function okMessage(string $message, int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * Devuelve `{ data: <payload> }` con código 201 (Created).
     *
     * @param  mixed  $data
     */
    protected function created($data, array $headers = []): JsonResponse
    {
        return $this->okData($data, 201, $headers);
    }

    /**
     * Respuesta 204 No Content (sin body).
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Devuelve `{ message: <error> }` con código de error (400/422/etc.).
     * Para errores con campos de validación, usar `errorWithFields()`.
     */
    protected function errorMessage(string $message, int $status = 400): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * Devuelve `{ message, errors }` típico de errores de validación 422.
     *
     * @param  array<string, list<string>>  $errors
     */
    protected function errorWithFields(string $message, array $errors, int $status = 422): JsonResponse
    {
        return response()->json(['message' => $message, 'errors' => $errors], $status);
    }

    /**
     * Devuelve el envelope plano de paginación cross-ecosystem, serializando
     * los items mediante una `JsonResource` colección.
     *
     * El envelope es el formato nativo de Laravel paginator
     * (`current_page`, `data`, `total`, `links`, ...) — sin anidar en `meta`.
     *
     * Uso:
     *
     *   public function index(Request $request): JsonResponse
     *   {
     *       $page = $this->service->list($request->validated());
     *       return $this->paginated($page, FooResource::class, $request);
     *   }
     *
     * El `$resourceClass` debe ser un `JsonResource` que reciba el DTO/Model
     * tal y como sale de `PaginatedDto::items`.
     *
     * @template TItem
     * @param  PaginatedDto<TItem>  $page
     * @param  class-string<JsonResource>  $resourceClass
     */
    protected function paginated(PaginatedDto $page, string $resourceClass, Request $request, int $status = 200, array $headers = []): JsonResponse
    {
        $items = $resourceClass::collection($page->items)->resolve($request);

        return response()->json([
            ...$page->jsonSerialize(),
            'data' => $items,
        ], $status, $headers);
    }
}
