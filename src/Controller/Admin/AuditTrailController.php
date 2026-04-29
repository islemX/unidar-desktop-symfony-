<?php

namespace App\Controller\Admin;

use App\Bundle\AuditTrail\Service\AuditTrailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/audit-trail', name: 'admin_audit_trail_')]
#[IsGranted('ROLE_ADMIN')]
class AuditTrailController extends AbstractController
{
    public function __construct(
        private readonly AuditTrailService $auditTrail,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $logs = $this->auditTrail->getRecentLogs(200);
        return $this->render('admin/audit_trail/index.html.twig', ['logs' => $logs]);
    }
}
