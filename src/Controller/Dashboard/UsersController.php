<?php

namespace App\Controller\Dashboard;

use App\Entity\Tenants\Tenants;
use App\Entity\Users\Users;
use App\Handler\UseCase\Dashboard\Users\GetDomainsUseCase;
use App\Interface\UseCase\Dashboard\Users\AddUserInterface;
use App\Interface\UseCase\Dashboard\Users\EditUserInterface;
use App\Interface\UseCase\Dashboard\Users\ListUserInterface;
use App\Interface\UseCase\Dashboard\Users\ToggleStatusUserInterface;
use App\Interface\UseCase\Dashboard\Users\ProfileInterface;
use App\Interface\UseCase\Dashboard\Users\ResendWelcomeEmailInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '{_locale<%supported_locales%>}/dashboard/users', name: 'dashboard_users')]
class UsersController extends AbstractController
{
    #[Route('', name: '', methods: ['GET'])]
    public function list(): Response
    {
        return $this->render('dashboard/users/list.html.twig');
    }

    #[Route('/new', name: '_new', methods: ['GET', 'POST'])]
    public function new(AddUserInterface $addUser): Response
    {
        $process = $addUser->handler();
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/users/user.html.twig', [
            'form' => $process->getForm()
        ]);
    }

    #[Route('/{id}/edit', name: '_edit', methods: ['GET', 'POST'])]
    public function edit(Users $user, EditUserInterface $editUser): Response
    {
        $process = $editUser->handler($user);
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/users/user.html.twig', [
            'form' => $process->getForm(),
            'isEdit' => true
        ]);
    }

    #[Route('/{id}/show', name: '_show', methods: ['GET'])]
    public function show(Users $user): Response
    {
        return $this->render('dashboard/users/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/resend/welcome/{id}/email', name: '_resend_welcome_email', methods: ['GET'])]
    public function resendWelcomeEmail(Users $user, ResendWelcomeEmailInterface $resendWelcomeEmail): Response
    {
        $process = $resendWelcomeEmail->handler($user);
        $type = "success";
        if ($process["isError"]) {
            $type = "error";
        }

        $this->addFlash($type, $process["message"]);
        return $this->redirectToRoute('dashboard_users');
    }

    #[Route('/profile', name: '_profile', methods: ['GET', 'POST'])]
    public function profile(ProfileInterface $profile): Response
    {
        $process = $profile->handler();
        if ($process->isProcess()) {
            $type = $process->getFlashType();
            $this->addFlash($type, $process->getMessage());
        }
        return $this->render('dashboard/users/profile.html.twig', [
            'form' => $process->getForm(),
            'image' => $this->getUser()->getProfilePicture()
        ]);
    }

    #[Route('/{id}/toggle-status', name: '_toggle_status', methods: ['POST'])]
    public function toggleStatus(Users $user, ToggleStatusUserInterface $toggleStatus): Response
    {
        return $this->json($toggleStatus->handler($user));
    }

    #[Route('/list', name: '_list', methods: ['POST', 'GET'])]
    public function listUsers(ListUserInterface $listUser): Response
    {
        return $this->json($listUser->handler(), 200);
    }

    #[Route('/domains/{tenant}', name: '_domains', methods: ['POST', 'GET'])]
    public function getDomains(Tenants $tenant, GetDomainsUseCase $getDomainsUseCase): Response
    {
        return $this->json($getDomainsUseCase->handler($tenant), 200);
    }
}
