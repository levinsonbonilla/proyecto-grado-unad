<?php

namespace App\Handler\UseCase\Dashboard\Slides;

use App\Entity\Tenants\Others\Slides;
use App\Form\Dashboard\SlidesType;
use App\Handler\Shared\AbstractEditHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Dashboard\Slides\EditSlideInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Others\SlidesRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EditSlideUseCase extends AbstractEditHandler implements EditSlideInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly S3ManagerInterface $s3Manager,
        private readonly ParameterBagInterface $parameters,
        private readonly SlidesRepository $slidesRepository,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(Slides $slide): FormReturn
    {
        return $this->process($slide);
    }

    protected function formType(): string
    {
        return SlidesType::class;
    }

    protected function formName(): string
    {
        return 'slides';
    }

    protected function assembleForm(FormInterface $form, object $entity): FormInterface
    {

        $form->get('name')->setData($entity->getName());
        $form->get('description')->setData($entity->getDescription());
        $form->get('type')->setData($entity->getType());
        $form->get('position')->setData($entity->getPosition());

        return $form;
    }

    protected function edit(array $data, object $entity): void
    {

        $file = $this->request->getCurrentRequest()->files->get('slides')['image'] ?? null;

        if (!empty($file)) {
            $s3Image = $this->s3Manager->create($file, $this->parameters->get('upload_slides'));
            if (!empty($entity->getImage())) {
                $this->s3Manager->delete($entity->getImage());
            }
            $entity->setImage($s3Image);
        }

        $entity->edit(
            $data['name'] ?? $entity->getName(),
            $data['description'] ?? $entity->getDescription(),
            $data['type'] ?? $entity->getType(),
            isset($data['position']) ? (int) $data['position'] : null
        );

        $this->customeEntityManager->add($entity, true);

        if ($entity->getDomain() !== null) {
            $this->slidesRepository->invalidatePublicCache($entity->getDomain());
        }
    }
}
