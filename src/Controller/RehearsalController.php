<?php

declare(strict_types=1);

namespace Acencyril\RehearsalBundle\Controller;

use Acencyril\RehearsalBundle\Model\Decision;
use Acencyril\RehearsalBundle\Rehearsal;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * The admin section: the proposed changes, their notes and tests, and per change
 * "Validate" (with an optional comment) or "Reject" (with a comment).
 *
 * Dependencies injected explicitly, no AbstractController: the bundle's services are
 * not autoconfigured and its service locator would never be built.
 */
final class RehearsalController
{
    public function __construct(
        private readonly Rehearsal $rehearsal,
        private readonly Environment $twig,
        private readonly AuthorizationCheckerInterface $auth,
        private readonly CsrfTokenManagerInterface $csrf,
        private readonly UrlGeneratorInterface $urls,
        private readonly ?TokenStorageInterface $tokens,
        private readonly string $role,
        private readonly string $parentTemplate,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->guard();
        $this->rehearsal->sync();
        $flashes = $request->hasSession() && method_exists($request->getSession(), 'getFlashBag') ? $request->getSession()->getFlashBag()->get('rehearsal') : [];

        $response = new Response($this->twig->render('@Rehearsal/index.html.twig', [
            'board' => $this->rehearsal->board(),
            'parent_template' => $this->parentTemplate,
            'route_params' => $this->routeParams($request),
            'flashes' => $flashes,
        ]));
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    public function decide(Request $request, string $id): Response
    {
        $this->guard();
        $params = $this->routeParams($request);
        if (!$this->csrf->isTokenValid(new CsrfToken('rehearsal-'.$id, (string) $request->request->get('_token')))) {
            return $this->back($request, $params, 'error.csrf');
        }
        $decision = match ((string) $request->request->get('decision')) {
            'validate' => Decision::VALIDATED,
            'reject' => Decision::REJECTED,
            default => null,
        };
        if (null === $decision) {
            return $this->back($request, $params, 'error.decision');
        }
        try {
            $this->rehearsal->decide($id, $decision, (string) $request->request->get('comment', ''), $this->user());
        } catch (\DomainException $e) {
            return $this->back($request, $params, $e->getMessage(), $id);
        }

        return $this->back($request, $params, 'flash.'.$decision, $id);
    }

    private function guard(): void
    {
        if (!$this->auth->isGranted($this->role)) {
            throw new AccessDeniedException();
        }
    }

    private function user(): string
    {
        return (string) $this->tokens?->getToken()?->getUserIdentifier();
    }

    /** @return array<string, mixed> the route parameters of the section (e.g. _locale), without the change id */
    private function routeParams(Request $request): array
    {
        $params = (array) $request->attributes->get('_route_params', []);
        unset($params['id']);

        return $params;
    }

    /** @param array<string, mixed> $params */
    private function back(Request $request, array $params, string $flash, string $id = ''): RedirectResponse
    {
        if ($request->hasSession() && method_exists($request->getSession(), 'getFlashBag')) {
            $request->getSession()->getFlashBag()->add('rehearsal', $flash);
        }

        return new RedirectResponse($this->urls->generate('rehearsal_index', $params).('' !== $id ? '#change-'.$id : ''));
    }
}
