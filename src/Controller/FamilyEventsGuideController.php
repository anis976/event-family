<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FamilyEventsGuideController extends AbstractAppController
{
    /** @var list<string> */
    public const array SECTIONS = ['define', 'group', 'plan', 'invite', 'coordinate', 'tips'];

    /** @var list<string> */
    public const array SECTIONS_WITH_ITEMS = ['define', 'plan', 'invite', 'coordinate', 'tips'];

    #[Route('/comment-organiser-evenements-familiaux', name: 'app_family_events_guide', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('guide/index.html.twig', [
            'sections' => self::SECTIONS,
            'sectionsWithItems' => self::SECTIONS_WITH_ITEMS,
        ]);
    }
}
