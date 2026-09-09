<?php

namespace App\Handler\UseCase\Dashboard\Users;

use App\ArgumentHandler\UsersArgument;
use App\Entity\Users\UsersDomains;
use App\Entity\Users\Users;
use App\Exception\GenericException;
use App\Form\Dashboard\UsersType;
use App\Handler\Shared\AbstractEditHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\UseCase\Dashboard\Users\EditUserInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Users\UsersRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditUserUseCase extends AbstractEditHandler implements EditUserInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly UsersRepository $usersRepository,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly Security $security,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(Users $user): FormReturn
    {
        $this->assertCanEdit($user);
        return $this->process($user);
    }

    private function assertCanEdit(Users $target): void
    {
        if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
            return;
        }

        $currentUser = $this->security->getUser();
        if (!$currentUser instanceof Users) {
            throw new AccessDeniedException('No autorizado.');
        }

        $currentTenant = $currentUser->getDomain()->getTenant();
        $targetTenant = $target->getDomain()->getTenant();
        if ((string) $currentTenant->getId() !== (string) $targetTenant->getId()) {
            throw new AccessDeniedException('No autorizado para editar usuarios de otro tenant.');
        }
    }

    protected function formType(): string
    {
        return UsersType::class;
    }

    protected function formName(): string
    {
        return 'users';
    }

    protected function successMessage(): string
    {
        return $this->translator->trans("user_edited_success", [], 'users');
    }

    protected function complementForm(FormInterface $form): FormInterface
    {
        $yes = $this->translator->trans("yes", [], "users");
        $no = $this->translator->trans("no", [], "users");

        $form->remove('tenant');
        $form->remove('domain');

        return $form
            ->add('validatedEmail', ChoiceType::class, [
                'choices' => [$yes => true, $no => false],
                'required' => true,
            ])
            ->add('activated', ChoiceType::class, [
                'choices' => [$yes => true, $no => false],
                'required' => true,
            ])
            ->add('email', EmailType::class, [
                'attr' => ['readonly' => true],
            ]);
    }

    protected function assembleForm(FormInterface $form, object $entity): FormInterface
    {
        $form->get('email')->setData($entity->getEmail());
        $form->get('name')->setData($entity->getName());
        $form->get('lastName')->setData($entity->getLastName());
        $form->get('validatedEmail')->setData($entity->isValidatedEmail());
        $form->get('activated')->setData($entity->isActive());
        $form->get('roles')->setData($this->primaryRole($entity));

        return $form;
    }

    protected function edit(array $data, object $entity): void
    {

        $argumentData = $data;
        $argumentData['roles'] = isset($data['roles']) ? (array) $data['roles'] : [];

        $argument = new UsersArgument($argumentData, $entity->getDomain());
        $entity->edit($argument);
        $data["activated"] ? $entity->activate() : $entity->deactivate();

        $this->syncRoleChange($data, $entity);

        $this->customeEntityManager->add($entity, true);
    }

    private function syncRoleChange(array $data, Users $entity): void
    {
        if (!isset($data['roles']) || $data['roles'] === '') {
            return;
        }

        $newRole = $data['roles'];
        $currentRole = $this->primaryRole($entity);
        if ($newRole === $currentRole) {
            return;
        }

        $currentUser = $this->security->getUser();
        $isSelf = $currentUser instanceof Users && (string) $currentUser->getId() === (string) $entity->getId();
        if ($isSelf) {
            throw new GenericException(
                $this->translator->trans('cannot_change_own_role', [], 'users'),
                400
            );
        }

        if ($currentRole === 'ROLE_SUPER_ADMIN' && $newRole !== 'ROLE_SUPER_ADMIN') {
            $remaining = array_filter(
                $this->usersRepository->findActiveSuperAdmins(),
                fn (Users $u): bool => (string) $u->getId() !== (string) $entity->getId()
            );
            if (empty($remaining)) {
                throw new GenericException(
                    $this->translator->trans('cannot_remove_last_super_admin', [], 'users'),
                    400
                );
            }
        }

        $entity->rolesChange([$newRole]);

        $usersDomains = $entity->getUsersDomainsByDomain($entity->getDomain());
        if ($usersDomains instanceof UsersDomains) {
            $usersDomains->rolesChange([$newRole]);
        }
    }

    private function primaryRole(Users $entity): string
    {
        $roles = array_diff($entity->getRoles(), ['ROLE_USER']);
        return !empty($roles) ? (string) reset($roles) : 'ROLE_USER';
    }
}
