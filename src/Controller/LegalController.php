<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractAppController
{
    #[Route('/cgu', name: 'app_cgu', methods: ['GET'])]
    public function cgu(): Response
    {
        return $this->render('legal/cgu.html.twig');
    }

    #[Route('/mentions-legales', name: 'app_mentions', methods: ['GET'])]
    public function mentions(): Response
    {
        return $this->render('legal/mentions.html.twig');
    }
}
