<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemoController extends AbstractAppController
{
    /** @var list<string> */
    public const array EVENTS = ['birthday', 'christmas', 'reunion'];

    /** @var list<string> */
    public const array MEMBERS = ['marie', 'pierre', 'sophie', 'lucas', 'emma'];

    /** @var list<string> */
    public const array PARTICIPANTS = ['julien', 'claire', 'thomas'];

    #[Route('/demo', name: 'app_demo', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('demo/index.html.twig', [
            'events' => self::EVENTS,
            'members' => self::MEMBERS,
            'participants' => self::PARTICIPANTS,
        ]);
    }
}
