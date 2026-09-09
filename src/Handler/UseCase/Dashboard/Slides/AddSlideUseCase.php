<?php

namespace App\Handler\UseCase\Dashboard\Slides;

use App\ArgumentHandler\SlidesArgument;
use App\Entity\Tenants\Others\Slides;
use App\Exception\GenericException;
use App\Handler\Configuration\GetDomainData;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Dashboard\Slides\AddSlideInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Others\SlidesRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddSlideUseCase extends AbstractAddHandler implements AddSlideInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly S3ManagerInterface $s3Manager,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly ParameterBagInterface $parameters,
        private readonly GetDomainData $getDomainData,
        private readonly SlidesRepository $slidesRepository,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(string $type, ?array $additionalData = null): FormReturn
    {
        return $this->process($type, $additionalData);
    }

    protected function rollbackOnError(): void
    {
        if (!empty($this->s3Image)) {
            $this->s3Manager->delete($this->s3Image);
        }
    }

    protected function add(array $data, array $additionalData): void
    {
        $file = $additionalData['image'] ?? null;
        $domain = $this->getDomainData->getDomain();

        if (empty($file)) {
            throw new GenericException('Image is required', 400);
        }

        $s3Image = $this->s3Manager->create($file, rtrim($this->parameters->get('upload_slides'), '/') . '/' . $domain->getId());
        if (empty($s3Image)) {
            throw new GenericException('Error uploading image', 500);
        }

        $this->s3Image = $s3Image;
        $data['image'] = $s3Image;

        $argument = new SlidesArgument($data, $domain);
        $slide = new Slides();
        $slide->add($argument);
        $this->customeEntityManager->add($slide, true);

        $this->slidesRepository->invalidatePublicCache($domain);
    }
}
