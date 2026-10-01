<?php

namespace App\Handler\UseCase\Dashboard\AboutSections;

use App\Entity\Tenants\Others\AboutSections;
use App\Exception\GenericException;
use App\Form\Dashboard\AboutSectionsType;
use App\Handler\Shared\AbstractEditHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Dashboard\AboutSections\EditAboutSectionInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Others\AboutSectionsRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditAboutSectionUseCase extends AbstractEditHandler implements EditAboutSectionInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly S3ManagerInterface $s3Manager,
        private readonly ParameterBagInterface $parameters,
        private readonly AboutSectionsRepository $aboutSectionsRepository,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(AboutSections $section): FormReturn
    {
        return $this->process($section);
    }

    protected function formType(): string
    {
        return AboutSectionsType::class;
    }

    protected function formName(): string
    {
        return 'about_sections';
    }

    protected function successMessage(): string
    {
        return $this->translator->trans('about_section_edited_successfully', [], 'about_sections');
    }

    protected function assembleForm(FormInterface $form, object $entity): FormInterface
    {
        $form->get('title')->setData($entity->getTitle());
        $form->get('text')->setData($entity->getText());
        $form->get('position')->setData($entity->getPosition());

        return $form;
    }

    protected function edit(array $data, object $entity): void
    {
        $file = $this->request->getCurrentRequest()->files->get($this->formName())['image'] ?? null;

        if (!empty($file)) {
            $domain = $entity->getDomain();
            $s3Image = $this->s3Manager->create($file, rtrim($this->parameters->get('upload_about'), '/') . '/' . $domain->getId());
            if (empty($s3Image)) {
                throw new GenericException($this->translator->trans('about_section_image_error', [], 'about_sections'), 500);
            }
            if (!empty($entity->getImage())) {
                $this->s3Manager->delete($entity->getImage());
            }
            $entity->setImage($s3Image);
        }

        $position = $data['position'] ?? null;
        $entity->edit(
            trim((string) ($data['title'] ?? $entity->getTitle())),
            trim((string) ($data['text'] ?? $entity->getText())),
            ($position === null || $position === '') ? null : (int) $position
        );

        $this->customeEntityManager->add($entity, true);
        $this->aboutSectionsRepository->invalidatePublicCache($entity->getDomain());
    }
}
