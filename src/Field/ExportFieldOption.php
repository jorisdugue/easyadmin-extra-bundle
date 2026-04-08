<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

final class ExportFieldOption
{
    /**
     * @var string
     */
    public const VISIBLE_FORMATS = 'visibleFormats';
    /**
     * @var string
     */
    public const HIDDEN_FORMATS = 'hiddenFormats';
    /**
     * @var string
     */
    public const FORMAT_LABELS = 'formatLabels';

    /**
     * @var string
     */
    public const VISIBLE_ROLES = 'visibleRoles';
    /**
     * @var string
     */
    public const HIDDEN_ROLES = 'hiddenRoles';
    /**
     * @var string
     */
    public const ROLE_LABELS = 'roleLabels';

    private function __construct() {}
}
