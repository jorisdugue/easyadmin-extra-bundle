<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle;

use JorisDugue\EasyAdminExtraBundle\DependencyInjection\JorisDugueEasyadminExtraExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class JorisDugueEasyAdminExtraBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new JorisDugueEasyadminExtraExtension();
        }

        return $this->extension;
    }
}
