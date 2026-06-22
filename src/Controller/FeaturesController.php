<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FeaturesController extends AbstractAppController
{
    #[Route('/fonctionnalites', name: 'app_features', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('features/index.html.twig');
    }
}
