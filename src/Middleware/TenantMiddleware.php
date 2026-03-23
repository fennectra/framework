<?php

namespace Fennec\Middleware;

use Fennec\Core\HttpException;
use Fennec\Core\MiddlewareInterface;
use Fennec\Core\Request;
use Fennec\Core\TenantManager;

class TenantMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TenantManager $tenantManager,
    ) {
    }

    public function handle(Request $request, callable $next): mixed
    {
        // Si le multi-tenancy n'est pas configuré, on passe
        if (!$this->tenantManager->isEnabled()) {
            return $next($request);
        }

        $tenantId = $this->tenantManager->resolveFromRequest();

        if ($tenantId === null) {
            throw new HttpException(400, 'Tenant non identifié pour ce domaine ou port.');
        }

        // Stocker le tenant dans les attributs de la requête
        $request = $request->withAttribute('tenant', $tenantId);

        return $next($request);
    }
}
