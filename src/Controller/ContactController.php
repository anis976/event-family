<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractAppController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('contact/index.html.twig');
    }
}
