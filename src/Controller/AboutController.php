<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AboutController extends AbstractAppController
{
    #[Route('/about', name: 'app_about', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('about/index.html.twig');
    }
}
