<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FaqController extends AbstractAppController
{
    public const int ITEM_COUNT = 18;

    #[Route('/faq', name: 'app_faq', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('faq/index.html.twig', [
            'faqCount' => self::ITEM_COUNT,
        ]);
    }
}
