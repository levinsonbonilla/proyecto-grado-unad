<?php

namespace App\Handler\UseCase\Dashboard\AboutSections;

use App\ArgumentHandler\AboutSectionsArgument;
use App\Entity\Tenants\Others\AboutSections;
use App\Exception\GenericException;
use App\Form\Dashboard\AboutSectionsType;
use App\Handler\Shared\AbstractAddHandler;
use App\Interface\Configuration\CustomeEntityManagerInterface;
use App\Interface\Configuration\GetDomainDataInterface;
use App\Interface\Configuration\S3ManagerInterface;
use App\Interface\UseCase\Dashboard\AboutSections\AddAboutSectionInterface;
use App\Interface\UseCase\Security\LogInterface;
use App\Repository\Tenants\Others\AboutSectionsRepository;
use App\ReturnHandler\FormReturn;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AddAboutSectionUseCase extends AbstractAddHandler implements AddAboutSectionInterface
{
    public function __construct(
        RequestStack $request,
        FormFactoryInterface $formFactory,
        LogInterface $log,
        TranslatorInterface $translator,
        private readonly S3ManagerInterface $s3Manager,
        private readonly CustomeEntityManagerInterface $customeEntityManager,
        private readonly ParameterBagInterface $parameters,
        private readonly GetDomainDataInterface $getDomainData,
        private readonly AboutSectionsRepository $aboutSectionsRepository,
    ) {
        parent::__construct($request, $formFactory, $log, $translator);
    }

    public function handler(): FormReturn
    {
        return $this->process(AboutSectionsType::class);
    }

    protected function successMessage(): string
    {
        return $this->translator->trans('about_section_created_successfully', [], 'about_sections');
    }

    protected function rollbackOnError(): void
    {
        if (!empty($this->s3Image)) {
            $this->s3Manager->delete($this->s3Image);
        }
    }

    protected function add(array $data, array $additionalData): void
    {
        $domain = $this->getDomainData->getDomain();
        $file = $additionalData['image'] ?? null;

        if (!empty($file)) {
            $s3Image = $this->s3Manager->create($file, rtrim($this->parameters->get('upload_about'), '/') . '/' . $domain->getId());
            if (empty($s3Image)) {
                throw new GenericException($this->translator->trans('about_section_image_error', [], 'about_sections'), 500);
            }
            $this->s3Image = $s3Image;
            $data['image'] = $s3Image;
        }

        $argument = new AboutSectionsArgument(
            $data,
            $domain,
            $this->aboutSectionsRepository->getNextPosition($domain)
        );
        $this->customeEntityManager->add((new AboutSections())->add($argument), true);

        $this->aboutSectionsRepository->invalidatePublicCache($domain);
    }
}
