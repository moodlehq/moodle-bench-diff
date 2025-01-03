<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ComparisonController extends AbstractController
{
    #[Route('/compare/{before}/{after}', name: 'compare_before_after')]
    public function compare(
        string $before,
        string $after,
    ): Response {
        return $this->render('comparison/compare.html.twig', [
            'before' => $before,
            'after' => $after,
        ]);
    }
}
