<?php

declare(strict_types=1);

namespace App\Shared\UI\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/', name: 'homepage', methods: ['GET'])]
final class HomepageController extends AbstractController
{
    public function __invoke(): Response
    {
        return $this->render('homepage/index.html.twig');
    }
}
