<?php

declare(strict_types=1);

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Page\Controller\Admin;

use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Security\Exception\TokenAuthenticationException;
use Thelia\Tools\TokenProvider;

/**
 * Shared checks for the admin actions that change data outside a Symfony form:
 * the session token must come in the POST body, and the administrator must hold
 * the requested access on the Page module.
 */
trait ChecksAdminWriteAccess
{
    private function refuseUnlessAllowed(Request $request, TokenProvider $tokenProvider, string $access): ?Response
    {
        try {
            $tokenProvider->checkToken((string) $request->request->get('_token', ''));
        } catch (TokenAuthenticationException) {
            return $this->errorPage($this->translator->trans("Sorry, you're not allowed to perform this action"), 403);
        }

        return $this->refuseUnlessGranted($access);
    }

    private function refuseUnlessGranted(string $access): ?Response
    {
        return $this->checkAuth([], ['Page'], $access);
    }
}
