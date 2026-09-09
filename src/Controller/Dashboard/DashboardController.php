<?php

namespace App\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard', name: 'dashboard')]
class DashboardController extends AbstractController
{
    #[Route('', name: '')]
    public function index(): Response
    {
        return $this->render('dashboard/statistics/index.html.twig');

    }

    #[Route('/profile', name: '_profile')]
    public function profile(): Response
    {
        return $this->redirectToRoute('dashboard_users_profile');
    }

    #[Route('/help', name: '_help')]
    public function help(): Response
    {
        return $this->render('dashboard/home.html.twig', [
            'controller_name' => 'PrivateController',
        ]);
    }
}
