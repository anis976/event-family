<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\EventRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class HomeController extends AbstractAppController
{
    public function __construct(
        private readonly EventRepository $eventRepository,
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $user = $this->getUser();
        $upcomingPublicEvents = $user instanceof UserInterface
            ? $this->eventRepository->findUpcomingPublic(3)
            : [];

        return $this->render('home/index.html.twig', [
            'upcomingPublicEvents' => $upcomingPublicEvents,
        ]);
    }
}
