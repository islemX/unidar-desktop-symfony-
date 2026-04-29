<?php

namespace App\Bundle\AuditTrail\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Config\FileLocator;

class UnidarAuditTrailExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        // All services auto-wired via main services.yaml App:: scan.
        // No extra DI config needed for this bundle.
    }

    public function getAlias(): string
    {
        return 'unidar_audit_trail';
    }
}
