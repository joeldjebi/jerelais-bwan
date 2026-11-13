<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use RequestParseBodyException;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * PHP ne lit les envois multipart (champs + fichiers) que pour POST. Pour un PUT ou un PATCH multipart
 * (ex. PUT /identity avec des photos), le corps est lu ici avec request_parse_body() (PHP 8.4).
 */
class ParseMultipartForUpdates
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->getRealMethod(), ['PUT', 'PATCH'], true)
            && str_starts_with(strtolower((string) $request->headers->get('Content-Type')), 'multipart/form-data')
            && $request->request->count() === 0 && $request->files->count() === 0) {
            try {
                [$fields, $files] = request_parse_body();
            } catch (RequestParseBodyException $exception) {
                throw new BadRequestHttpException($exception->getMessage(), $exception);
            }

            $request->request->replace($fields);
            $request->files = new FileBag($files);
        }

        return $next($request);
    }
}
