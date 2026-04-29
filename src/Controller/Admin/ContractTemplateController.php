<?php

namespace App\Controller\Admin;

use App\Entity\ContractTemplate;
use App\Repository\ContractTemplateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/contract-templates', name: 'admin_contract_template_')]
#[IsGranted('ROLE_ADMIN')]
class ContractTemplateController extends AbstractController
{
    public function __construct(
        private readonly ContractTemplateRepository $repo,
        private readonly EntityManagerInterface     $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $templates = $this->repo->findBy([], ['locale' => 'ASC', 'version' => 'DESC']);
        return $this->render('admin/contract_templates/index.html.twig', ['templates' => $templates]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $template = new ContractTemplate();
        return $this->handleForm($request, $template, 'Create');
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ContractTemplate $template): Response
    {
        return $this->handleForm($request, $template, 'Edit');
    }

    #[Route('/{id}/toggle', name: 'toggle', methods: ['POST'])]
    public function toggle(ContractTemplate $template): Response
    {
        $template->setIsActive(!$template->isActive());
        $this->em->flush();
        $this->addFlash('success', 'Template ' . ($template->isActive() ? 'activated' : 'deactivated') . '.');
        return $this->redirectToRoute('admin_contract_template_index');
    }

    #[Route('/{id}/set-default', name: 'set_default', methods: ['POST'])]
    public function setDefault(ContractTemplate $template): Response
    {
        // Unset default for same locale
        $others = $this->repo->findBy(['locale' => $template->getLocale()]);
        foreach ($others as $t) {
            $t->setIsDefault(false);
        }
        $template->setIsDefault(true);
        $this->em->flush();
        $this->addFlash('success', "Template set as default for locale '{$template->getLocale()}'.");
        return $this->redirectToRoute('admin_contract_template_index');
    }

    private function handleForm(Request $request, ContractTemplate $template, string $mode): Response
    {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();
            $template->setTemplateName($data['template_name'] ?? '');
            $template->setTemplateType($data['template_type'] ?? 'residential');
            $template->setLocale($data['locale'] ?? 'fr');
            $template->setVersion((int) ($data['version'] ?? 1));
            $template->setIsActive(isset($data['is_active']));
            $template->setIsDefault(isset($data['is_default']));
            $template->setContractContent($data['contract_content'] ?? '');
            $template->setLegalClauses($data['legal_clauses'] ?? '');
            $template->setTunisiaSpecificClauses($data['tunisia_clauses'] ?? '');

            $this->em->persist($template);
            $this->em->flush();

            $this->addFlash('success', "Template \"{$template->getTemplateName()}\" saved.");
            return $this->redirectToRoute('admin_contract_template_index');
        }

        return $this->render('admin/contract_templates/edit.html.twig', [
            'template' => $template,
            'mode'     => $mode,
        ]);
    }
}
