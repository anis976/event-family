<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LegalController extends AbstractAppController
{
    #[Route('/ads.txt', name: 'app_ads_txt', methods: ['GET'])]
    public function adsTxt(): Response
    {
        return new Response(
            "google.com, pub-1688607663044702, DIRECT, f08c47fec0942fa0\n",
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }

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
