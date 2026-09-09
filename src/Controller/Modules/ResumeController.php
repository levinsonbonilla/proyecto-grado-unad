<?php

namespace App\Controller\Modules;

use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '{_locale<%supported_locales%>}/resume', name: 'resume')]
class ResumeController extends AbstractController
{
    #[Route('', name: '')]
    public function index(Pdf $pdf): Response
    {

        $html = $this->renderView('resume/emily_resume.html.twig', []);

        $options = [
            'no-outline' => true,
            'enable-local-file-access' => true,
            'margin-top' => 0,
            'margin-right' => 0,
            'margin-bottom' => 0,
            'margin-left' => 0,
            'page-size' => 'A4',
            'no-images' => false,
            'custom-header' => [
                'Accept-Encoding' => 'gzip, deflate',
            ]
        ];

        $pdfOutput = $pdf->getOutputFromHtml($html, $options);

        return new Response(
            $pdfOutput,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="LevinsonBonilla.pdf"',
            ]
        );

    }
}
