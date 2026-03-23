<?php

namespace Fennec\Core;

interface EventHandlerInterface
{
    /**
     * Traite un événement avant ou après l'action du controller.
     *
     * @param Request $request  La requête en cours
     * @param mixed   $result   Null pour Before, valeur de retour du controller pour After
     */
    public function handle(Request $request, mixed $result = null): void;
}
