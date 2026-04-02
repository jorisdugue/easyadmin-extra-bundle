<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

final class ExportFieldOption
{
    public const string VISIBLE_FORMATS = 'visibleFormats';
    public const string HIDDEN_FORMATS = 'hiddenFormats';
    public const string FORMAT_LABELS = 'formatLabels';

    public const string VISIBLE_ROLES = 'visibleRoles';
    public const string HIDDEN_ROLES = 'hiddenRoles';
    public const string ROLE_LABELS = 'roleLabels';

    private function __construct() {}
}
