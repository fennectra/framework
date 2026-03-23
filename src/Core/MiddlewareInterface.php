<?php

namespace Fennec\Core;

interface MiddlewareInterface
{
    /**
     * Traite la requête et passe au middleware suivant.
     */
    public function handle(Request $request, callable $next): mixed;
}
